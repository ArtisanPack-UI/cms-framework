<?php

/**
 * Plugin Assets Controller.
 *
 * Serves a plugin's built federation bundle so a manifest can declare a
 * relative `federated_module.entry` without the plugin shipping its own
 * asset controller.
 *
 * @since      2.14.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Http\Controllers;

use ArtisanPackUI\CMSFramework\Modules\Plugins\Support\PluginRegistry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves files from the asset directory a plugin registered through
 * {@see PluginRegistry::setAssetDirectory()}, at
 * `/plugins/{slug}/assets/{path}`.
 *
 * The route is public: a host's federation runtime fetches the entry and its
 * chunks before the admin shell has a page to gate, and a build carries no
 * secrets. Only directories a booted ( active ) plugin registered are served,
 * and the resolved file must stay inside that directory, carry an allowed
 * extension and be a regular file. Anything else 404s.
 *
 * A file whose name carries a bundler content hash ( `index-BxQ3k9aZ.js` )
 * changes URL on every rebuild, so it is cached as `immutable` for a year.
 * Everything else keeps a stable URL while its contents change: the
 * federation entry ( e.g. `remoteEntry.js` ), `mf-manifest.json`, Vite
 * `public/` copies, a library-mode `style.css`. Those are sent `no-cache`
 * with an ETag and Last-Modified, and a matching conditional request gets a
 * 304, so a plugin update is picked up on the next load.
 *
 * @since 2.14.0
 */
class PluginAssetsController extends Controller
{
    /**
     * Content types for the extensions a federation build emits.
     *
     * Doubles as the extension allowlist. It is pinned explicitly because
     * `BinaryFileResponse` guesses `text/plain` for JS and CSS, which
     * strict-MIME browsers refuse to run as a module or apply as a stylesheet.
     *
     * @var array<string, string>
     */
    protected const CONTENT_TYPES = [
        'js'    => 'text/javascript',
        'mjs'   => 'text/javascript',
        'css'   => 'text/css',
        'map'   => 'application/json',
        'json'  => 'application/json',
        'wasm'  => 'application/wasm',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'avif'  => 'image/avif',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
    ];

    /**
     * Matches the last name segment before the extension that may be a
     * bundler content hash: Vite/Rollup `[name]-[hash].[ext]` or webpack
     * `[name].[contenthash].[ext]`. {@see isContentHashed()} applies the rest
     * of the heuristic.
     *
     * @since 2.14.0
     */
    protected const HASH_SEGMENT_PATTERN = '#[.-]([A-Za-z0-9_]{8,64})\.[A-Za-z0-9]+$#D';

    /**
     * Stream a file from a plugin's registered asset directory.
     *
     * @since 2.14.0
     *
     * @param  Request  $request  The incoming request.
     * @param  PluginRegistry  $registry  The runtime plugin registry.
     * @param  string  $slug  Plugin slug ( route parameter ).
     * @param  string  $path  Path under the plugin's asset directory.
     */
    public function show( Request $request, PluginRegistry $registry, string $slug, string $path ): BinaryFileResponse|Response
    {
        $assets = $registry->assetDirectory( $slug );

        // The route constraint already rejects these; `realpath()` throws a
        // ValueError on a null byte, so this is defence in depth.
        if ( null === $assets || str_contains( $path, "\0" ) || str_contains( $path, '\\' ) ) {
            return $this->notFound();
        }

        // Dot-files and dot-directories ( `.vite/manifest.json`, `.git/…` ) are
        // build or tooling metadata, never federation output, so they are
        // refused even when the extension is allowed. This also covers `..`.
        if ( 1 === preg_match( '#(^|/)\.#', $path ) ) {
            return $this->notFound();
        }

        $extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

        if ( ! isset( self::CONTENT_TYPES[ $extension ] ) ) {
            return $this->notFound();
        }

        $root      = realpath( $assets['directory'] );
        $requested = false === $root ? false : realpath( $root . '/' . $path );

        if ( false === $root
            || false === $requested
            || ! str_starts_with( $requested, $root . DIRECTORY_SEPARATOR )
            || ! File::isFile( $requested ) ) {
            return $this->notFound();
        }

        $response = new BinaryFileResponse( $requested );
        $response->headers->set( 'Content-Type', self::CONTENT_TYPES[ $extension ] );
        $response->headers->set( 'X-Content-Type-Options', 'nosniff' );

        // An SVG opened directly from this origin could run inline script with
        // the app's cookies; sandbox it and force a download, as the theme
        // asset route does. `<img src>` use is unaffected.
        if ( 'svg' === $extension ) {
            $response->headers->set( 'Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox" );
            $response->headers->set( 'Content-Disposition', 'attachment' );
        }

        $response->setPublic();

        // Compare resolved paths: the entry may itself be a symlink ( pnpm or
        // workspace builds ), and the stable-named entry must never be cached
        // as immutable.
        $entry   = realpath( $root . DIRECTORY_SEPARATOR . $assets['entry'] );
        $isEntry = false !== $entry && $entry === $requested;

        if ( ! $isEntry && $this->isContentHashed( basename( $requested ) ) ) {
            $response->setMaxAge( 31536000 );
            $response->setImmutable();

            return $response;
        }

        $response->headers->addCacheControlDirective( 'no-cache' );
        $response->setAutoLastModified();
        $response->setAutoEtag();
        $response->isNotModified( $request );

        return $response;
    }

    /**
     * Whether a file name carries a bundler content hash, so its URL changes
     * whenever its contents do.
     *
     * The hash segment ( see {@see HASH_SEGMENT_PATTERN} ) must contain a
     * digit, and either an uppercase letter ( Vite's base64 hashes ) or only
     * hex digits ( webpack's `[contenthash]` ). A word such as `-version2` or
     * `-HelloWorldPanel` is therefore not taken for a hash. A hash this
     * misses is only revalidated, never served stale.
     *
     * @since 2.14.0
     *
     * @param  string  $fileName  The file name, without directories.
     */
    protected function isContentHashed( string $fileName ): bool
    {
        if ( 1 !== preg_match( self::HASH_SEGMENT_PATTERN, $fileName, $matches ) ) {
            return false;
        }

        $segment = $matches[1];

        if ( 1 !== preg_match( '/\d/', $segment ) ) {
            return false;
        }

        return 1 === preg_match( '/[A-Z]/', $segment ) || 1 === preg_match( '/^[0-9a-f]+$/D', $segment );
    }

    /**
     * An empty 404 response.
     *
     * @since 2.14.0
     */
    protected function notFound(): Response
    {
        return response( '', Response::HTTP_NOT_FOUND );
    }
}
