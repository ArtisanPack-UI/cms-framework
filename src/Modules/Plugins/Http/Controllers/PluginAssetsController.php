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
 * The federation entry ( e.g. `remoteEntry.js` ) keeps a stable URL while
 * its contents change on every build, so it is sent `no-cache` with an ETag
 * and Last-Modified, and a matching conditional request gets a 304. Every
 * other file is a content-hashed chunk whose URL changes on rebuild, so it is
 * cached as `immutable` for a year.
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

        if ( $root . DIRECTORY_SEPARATOR . $assets['entry'] !== $requested ) {
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
     * An empty 404 response.
     *
     * @since 2.14.0
     */
    protected function notFound(): Response
    {
        return response( '', Response::HTTP_NOT_FOUND );
    }
}
