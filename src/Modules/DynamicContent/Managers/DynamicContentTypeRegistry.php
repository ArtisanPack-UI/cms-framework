<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Enums\Cardinality;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Enums\Source;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Models\DynamicContentType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * In-memory registry of dynamic content types.
 *
 * Merges admin-created types (from the DB) with code-registered types.
 * Code-registered types win on slug conflict; a warning is logged.
 *
 * @since 2.4.0
 */
class DynamicContentTypeRegistry
{
    /**
     * Code-registered type definitions keyed by slug.
     *
     * Each definition is an array:
     *   [
     *     'slug' => string,
     *     'name' => string,
     *     'cardinality' => Cardinality,
     *     'description' => ?string,
     *     'icon' => ?string,
     *     'fields' => list<array{slug:string, label:string, type:string, options?:array, default?:mixed, required?:bool}>,
     *     'records' => callable():iterable<array<string,mixed>>|iterable<array<string,mixed>>|null,
     *   ]
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $codeTypes = [];

    protected ?array $mergedCache = null;

    /**
     * Register a type in code.
     *
     * @param  array<string, mixed>  $definition
     */
    public function registerCodeType( string $slug, array $definition ): void
    {
        $definition['slug']        = $slug;
        $definition['source']      = Source::Code;
        $definition['cardinality'] = $definition['cardinality'] ?? Cardinality::Singleton;

        $this->codeTypes[ $slug ] = $definition;
        $this->mergedCache        = null;
    }

    /**
     * All registered types keyed by slug.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        if ( null !== $this->mergedCache ) {
            return $this->mergedCache;
        }

        $out = [];

        // Pull DB types only if the table exists (guards early-boot / migrations).
        if ( Schema::hasTable( 'dynamic_content_types' ) ) {
            foreach ( DynamicContentType::with( 'fields' )->get() as $dbType ) {
                $out[ $dbType->slug ] = $this->normalizeDbType( $dbType );
            }
        }

        // Merge in code-registered — invoke filter first so packages can contribute.
        $codeTypes = $this->codeTypes;

        if ( function_exists( 'applyFilters' ) ) {
            try {
                $filtered = applyFilters( 'ap.dynamic_content.register-types', $codeTypes );

                if ( is_array( $filtered ) ) {
                    $codeTypes = $filtered;
                }
            } catch ( Throwable $e ) {
                // A third-party filter callback threw. Fall back to the pre-filter
                // set so one buggy plugin can't take down every token resolution.
                Log::channel( $this->logChannel() )->warning(
                    'Dynamic content: register-types filter threw; falling back to unfiltered set',
                    [ 'exception' => $e->getMessage() ],
                );
            }
        }

        foreach ( $codeTypes as $slug => $definition ) {
            if ( isset( $out[ $slug ] ) ) {
                Log::channel( $this->logChannel() )->warning(
                    'Dynamic content: code-registered type shadows admin-created type',
                    [ 'slug' => $slug ],
                );
            }

            $out[ $slug ] = $this->normalizeCodeType( $slug, $definition );
        }

        return $this->mergedCache = $out;
    }

    public function get( string $slug ): ?array
    {
        return $this->all()[ $slug ] ?? null;
    }

    public function forget(): void
    {
        $this->mergedCache = null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeDbType( DynamicContentType $type ): array
    {
        return [
            'slug'        => $type->slug,
            'name'        => $type->name,
            'cardinality' => $type->cardinality,
            'source'      => Source::Db,
            'description' => $type->description,
            'icon'        => $type->icon,
            'fields'      => $type->fields->map( fn ( $f ) => [
                'slug'     => $f->slug,
                'label'    => $f->label,
                'type'     => $f->type,
                'options'  => $f->options ?? [],
                'default'  => $f->default_value,
                'required' => $f->required,
            ] )->all(),
            'db_id'   => $type->id,
            'records' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     *
     * @return array<string, mixed>
     */
    protected function normalizeCodeType( string $slug, array $definition ): array
    {
        $cardinality = $definition['cardinality'] ?? Cardinality::Singleton;

        if ( is_string( $cardinality ) ) {
            $cardinality = Cardinality::from( $cardinality );
        }

        $fields = [];

        foreach ( $definition['fields'] ?? [] as $field ) {
            $fields[] = [
                'slug'     => $field['slug'],
                'label'    => $field['label'] ?? $field['slug'],
                'type'     => $field['type'] ?? 'text',
                'options'  => $field['options'] ?? [],
                'default'  => $field['default'] ?? null,
                'required' => (bool) ( $field['required'] ?? false ),
            ];
        }

        return [
            'slug'        => $slug,
            'name'        => $definition['name'] ?? $slug,
            'cardinality' => $cardinality,
            'source'      => Source::Code,
            'description' => $definition['description'] ?? null,
            'icon'        => $definition['icon'] ?? null,
            'fields'      => $fields,
            'records'     => $definition['records'] ?? null,
        ];
    }

    protected function logChannel(): string
    {
        return config( 'logging.channels.dynamic-content' ) ? 'dynamic-content' : 'stack';
    }
}
