<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Services;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Enums\Cardinality;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Enums\Source;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentTypeRegistry;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentRecord;
use Illuminate\Support\Facades\Cache;

/**
 * Reads dynamic content records — DB-backed or code-registered.
 *
 * All reads flow through here so the resolver, block bindings, and REST
 * endpoints share a single lazy-loading strategy and cache signature.
 *
 * @since 2.4.0
 */
class DynamicContentAccessor
{
    /**
     * Per-request memoization: `[$typeSlug => list<array<string,mixed>>]`.
     *
     * The accessor is bound as a singleton, so this cache is scoped to a
     * single HTTP request / job. Kills the N+1 that arises when a body
     * has multiple tokens against the same collection
     * (e.g. `{{team[0].name}} {{team[0].role}}`) — the collection now
     * loads once and every token indexes into the in-memory array.
     *
     * Invalidated by {@see forget()}, which fires from the same record
     * save/delete events that bust the resolver signature cache.
     *
     * @var array<string, list<array<string,mixed>>>
     */
    protected array $collectionMemo = [];

    /**
     * Per-request memoization for singleton reads: `[$typeSlug => array<string,mixed>|null]`.
     *
     * `null` is a valid memoized value ("no record persisted"), so we use
     * a sentinel key check (`array_key_exists`) to distinguish memoized
     * misses from cold entries.
     *
     * @var array<string, array<string,mixed>|null>
     */
    protected array $singletonMemo = [];

    public function __construct( protected DynamicContentTypeRegistry $registry )
    {
    }

    /**
     * Clear per-request memoization. Called from the record save/delete
     * event listener registered in {@see \ArtisanPackUI\CMSFramework\Modules\DynamicContent\Providers\DynamicContentServiceProvider}.
     */
    public function forget( ?string $typeSlug = null ): void
    {
        if ( null === $typeSlug ) {
            $this->collectionMemo = [];
            $this->singletonMemo  = [];

            return;
        }

        unset( $this->collectionMemo[ $typeSlug ], $this->singletonMemo[ $typeSlug ] );
    }

    /**
     * Fetch a singleton record's field values by type slug.
     *
     * Returns null when the type doesn't exist. Returns [] when the type
     * exists but has no persisted record yet.
     *
     * @return array<string, mixed>|null
     */
    public function singleton( string $typeSlug ): ?array
    {
        if ( array_key_exists( $typeSlug, $this->singletonMemo ) ) {
            return $this->singletonMemo[ $typeSlug ];
        }

        $type = $this->registry->get( $typeSlug );

        if ( null === $type ) {
            return $this->singletonMemo[ $typeSlug ] = null;
        }

        if ( Cardinality::Singleton !== $type['cardinality'] ) {
            return $this->singletonMemo[ $typeSlug ] = null;
        }

        return $this->singletonMemo[ $typeSlug ] = $this->firstRecord( $type ) ?? $this->defaultsFromFields( $type );
    }

    /**
     * Fetch a specific collection record by index (0-based).
     *
     * @return array<string, mixed>|null
     */
    public function collectionItem( string $typeSlug, int $index ): ?array
    {
        // Delegate through the memoized collection load so N tokens against
        // the same collection (`{{team[0].x}} {{team[0].y}}`) share one query.
        $records = $this->collection( $typeSlug );

        return $records[ $index ] ?? null;
    }

    /**
     * Return every record for a collection type as an array of field-value maps.
     *
     * Used by the visual-editor loop block and by REST list endpoints.
     *
     * @return list<array<string, mixed>>
     */
    public function collection( string $typeSlug ): array
    {
        if ( array_key_exists( $typeSlug, $this->collectionMemo ) ) {
            return $this->collectionMemo[ $typeSlug ];
        }

        $type = $this->registry->get( $typeSlug );

        if ( null === $type || Cardinality::Collection !== $type['cardinality'] ) {
            return $this->collectionMemo[ $typeSlug ] = [];
        }

        if ( Source::Code === $type['source'] ) {
            return $this->collectionMemo[ $typeSlug ] = $this->resolveCodeRecords( $type );
        }

        return $this->collectionMemo[ $typeSlug ] = DynamicContentRecord::query()
            ->with( 'values.field', 'type.fields' )
            ->where( 'dynamic_content_type_id', $type['db_id'] )
            ->orderBy( 'order' )
            ->orderBy( 'id' )
            ->get()
            ->map( fn ( DynamicContentRecord $r ) => $r->fieldValues() )
            ->values()
            ->all();
    }

    /**
     * Return the cache signature for a type — used to key downstream caches.
     */
    public function signatureFor( string $typeSlug ): string
    {
        $type = $this->registry->get( $typeSlug );

        if ( null === $type ) {
            return "dc:{$typeSlug}:missing";
        }

        if ( Source::Code === $type['source'] ) {
            return "dc:{$typeSlug}:code";
        }

        $stamp = Cache::rememberForever(
            "dc.sig.{$typeSlug}",
            fn () => (string) DynamicContentRecord::query()
                ->where( 'dynamic_content_type_id', $type['db_id'] )
                ->max( 'updated_at' ),
        );

        return "dc:{$typeSlug}:{$stamp}";
    }

    protected function firstRecord( array $type ): ?array
    {
        if ( Source::Code === $type['source'] ) {
            $records = $this->resolveCodeRecords( $type );

            return $records[0] ?? null;
        }

        $record = DynamicContentRecord::query()
            ->with( 'values.field', 'type.fields' )
            ->where( 'dynamic_content_type_id', $type['db_id'] )
            ->orderBy( 'order' )
            ->orderBy( 'id' )
            ->first();

        return null === $record ? null : $record->fieldValues();
    }

    protected function defaultsFromFields( array $type ): array
    {
        $out = [];

        foreach ( $type['fields'] as $field ) {
            $out[ $field['slug'] ] = $field['default'] ?? null;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function resolveCodeRecords( array $type ): array
    {
        $records = $type['records'] ?? null;

        if ( is_callable( $records ) ) {
            $records = $records();
        }

        if ( null === $records ) {
            return [];
        }

        $out = [];

        foreach ( $records as $record ) {
            if ( is_array( $record ) ) {
                $out[] = $record;
            }
        }

        return $out;
    }
}
