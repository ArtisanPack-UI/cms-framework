<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\DynamicContent\Services;

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Enums\Cardinality;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentTypeRegistry;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\FieldTypeRegistry;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\ModifierRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Resolves `{{source.field|modifier:arg}}` tokens in a string.
 *
 * Returns **HTML-safe** output: values are routed through their field
 * type's `render()` which either escapes (text-shaped types) or emits
 * intentional HTML (rich text). If a token can't be routed to a known
 * field type the value is escaped defensively before returning.
 *
 * Missing sources / fields render as an empty string and log a warning to
 * the `dynamic-content` channel. Resolution does NOT recurse — resolved
 * output is not re-scanned for tokens.
 *
 * @since 2.4.0
 */
class DynamicContentResolver
{
    /**
     * Matches `{{ source.field[.subfield|[index].field][|modifier:arg:arg]* }}`.
     * The outer non-greedy body is deliberately narrow — no braces inside a token.
     */
    public const TOKEN_PATTERN = '/\{\{\s*([^{}]+?)\s*\}\}/';

    public function __construct(
        protected DynamicContentTypeRegistry $registry,
        protected DynamicContentAccessor $accessor,
        protected FieldTypeRegistry $fieldTypes,
        protected ModifierRegistry $modifiers,
    ) {
    }

    public function render( string $content, array $context = [] ): string
    {
        if ( '' === $content || ! str_contains( $content, '{{' ) ) {
            return $content;
        }

        return (string) preg_replace_callback(
            self::TOKEN_PATTERN,
            fn ( array $match ) => $this->resolveOne( $match[0], $match[1], $context ),
            $content,
        );
    }

    /**
     * Return a signature that summarizes the resolver state for the given content.
     *
     * Callers include this in their own cache keys so a token change invalidates
     * their cached HTML.
     */
    public function signatureFor( string $content ): string
    {
        if ( ! preg_match_all( self::TOKEN_PATTERN, $content, $matches ) ) {
            return 'dc:none';
        }

        $signatures = [];

        foreach ( $matches[1] as $body ) {
            $parsed = $this->parseToken( $body );

            if ( null === $parsed ) {
                continue;
            }

            $signatures[] = $this->accessor->signatureFor( $parsed['source'] );
        }

        return md5( implode( '|', $signatures ) );
    }

    protected function resolveOne( string $original, string $body, array $context ): string
    {
        $parsed = $this->parseToken( $body );

        if ( null === $parsed ) {
            $this->logMissing( $original, 'malformed', $context );

            return '';
        }

        $value = $this->readValue( $parsed );

        if ( null === $value && ! $this->hasDefaultModifier( $parsed['modifiers'] ) ) {
            $this->logMissing( $original, 'unresolved', $context );
        }

        $value    = $this->applyModifiers( $value, $parsed['modifiers'] );
        $rendered = $this->renderFieldValue( $parsed, $value );

        return (string) $rendered;
    }

    /**
     * @return array{source:string, path:array<int,int|string>, modifiers:array<int,array{slug:string,args:array<int,string>}>}|null
     */
    protected function parseToken( string $body ): ?array
    {
        $parts     = explode( '|', $body );
        $accessor  = trim( array_shift( $parts ) );
        $modifiers = [];

        foreach ( $parts as $modifier ) {
            $modifier = trim( $modifier );

            if ( '' === $modifier ) {
                continue;
            }

            $segments = explode( ':', $modifier );
            $slug     = trim( array_shift( $segments ) );

            if ( '' === $slug ) {
                continue;
            }

            $modifiers[] = [
                'slug' => $slug,
                'args' => array_map( 'trim', $segments ),
            ];
        }

        // Path parsing: `source.field.sub` or `source[0].field`
        $segments = preg_split( '/[.\[\]]+/', $accessor, -1, PREG_SPLIT_NO_EMPTY );

        if ( false === $segments || count( $segments ) < 1 ) {
            return null;
        }

        $source = array_shift( $segments );
        $path   = [];

        foreach ( $segments as $segment ) {
            $path[] = ctype_digit( $segment ) ? (int) $segment : $segment;
        }

        return [
            'source'    => $source,
            'path'      => $path,
            'modifiers' => $modifiers,
        ];
    }

    protected function readValue( array $parsed ): mixed
    {
        $type = $this->registry->get( $parsed['source'] );

        if ( null === $type ) {
            return null;
        }

        // Split path into an optional collection index followed by a field path.
        $path       = $parsed['path'];
        $firstIsInt = ! empty( $path ) && is_int( $path[0] );

        if ( Cardinality::Collection === $type['cardinality'] ) {
            $index  = $firstIsInt ? array_shift( $path ) : 0;
            $record = $this->accessor->collectionItem( $parsed['source'], $index );
        } else {
            $record = $this->accessor->singleton( $parsed['source'] );
        }

        if ( null === $record ) {
            return null;
        }

        return $this->traversePath( $record, $path );
    }

    protected function traversePath( mixed $value, array $path ): mixed
    {
        foreach ( $path as $segment ) {
            if ( is_array( $value ) && array_key_exists( $segment, $value ) ) {
                $value = $value[ $segment ];
                continue;
            }

            return null;
        }

        return $value;
    }

    protected function applyModifiers( mixed $value, array $modifiers ): mixed
    {
        foreach ( $modifiers as $modifier ) {
            $impl = $this->modifiers->get( $modifier['slug'] );

            if ( null === $impl ) {
                continue;
            }

            $value = $impl->apply( $value, $modifier['args'] );
        }

        return $value;
    }

    /**
     * Route the value through its field type's `render()` so text-shaped
     * types are HTML-escaped and rich-text stays raw. When we can't
     * identify a field type (unknown source, no path, unknown field slug),
     * fall back to escaping the value defensively.
     */
    protected function renderFieldValue( array $parsed, mixed $value ): HtmlString
    {
        $fieldSlug = $this->trailingFieldSlug( $parsed['path'] );
        $type      = null === $fieldSlug ? null : $this->registry->get( $parsed['source'] );
        $fieldMeta = null === $type ? null : ( $this->fieldsBySlug( $type )[ $fieldSlug ] ?? null );

        if ( null !== $fieldMeta ) {
            $impl = $this->fieldTypes->get( $fieldMeta['type'] );

            if ( null !== $impl ) {
                return $impl->render( $value, $fieldMeta['options'] ?? [] );
            }
        }

        // Defensive fallback: an unroutable value is treated as untrusted text.
        if ( $value instanceof HtmlString ) {
            return $value;
        }

        if ( null === $value || ! is_scalar( $value ) ) {
            return new HtmlString( '' );
        }

        return new HtmlString( e( (string) $value ) );
    }

    /**
     * Return the last string segment of a path (the field slug), or null
     * if the path doesn't end in one.
     */
    protected function trailingFieldSlug( array $path ): ?string
    {
        for ( $i = count( $path ) - 1; $i >= 0; $i-- ) {
            if ( is_string( $path[ $i ] ) ) {
                return $path[ $i ];
            }
        }

        return null;
    }

    /**
     * Memoized fields-by-slug index for a normalized type definition.
     *
     * @param  array{fields:array<int,array<string,mixed>>}  $type
     *
     * @return array<string, array<string,mixed>>
     */
    protected function fieldsBySlug( array $type ): array
    {
        static $cache = [];

        $key = $type['slug'] ?? spl_object_hash( (object) $type );

        if ( isset( $cache[ $key ] ) ) {
            return $cache[ $key ];
        }

        $out = [];

        foreach ( $type['fields'] as $field ) {
            $out[ $field['slug'] ] = $field;
        }

        return $cache[ $key ] = $out;
    }

    protected function hasDefaultModifier( array $modifiers ): bool
    {
        foreach ( $modifiers as $modifier ) {
            if ( 'default' === $modifier['slug'] ) {
                return true;
            }
        }

        return false;
    }

    protected function logMissing( string $token, string $reason, array $context ): void
    {
        $channel = config( 'logging.channels.dynamic-content' ) ? 'dynamic-content' : 'stack';

        try {
            $url = Request::fullUrl();
        } catch ( Throwable ) {
            $url = null;
        }

        Log::channel( $channel )->warning(
            'Dynamic content token failed to resolve',
            [
                'token'   => $token,
                'reason'  => $reason,
                'url'     => $url,
                'user_id' => Auth::id(),
                'context' => $context,
            ],
        );
    }
}
