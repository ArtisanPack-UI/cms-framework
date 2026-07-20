<?php

/**
 * Theme Assets Controller.
 *
 * Serves static assets bundled inside a theme's `assets/` directory over
 * HTTP so themes can enqueue their own CSS/JS/fonts/vendor files without
 * publishing anything to the host app.
 *
 * @since      2.5.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Themes\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves files from `themes/{slug}/assets/{path}`.
 *
 * The route is intentionally public — theme CSS/JS are shipped as static
 * bytes, not gated content — but the handler must still reject any
 * attempt to escape the theme's `assets/` directory or read files from
 * disk locations the theme didn't intend to expose.
 *
 * @since 2.5.0
 */
class ThemeAssetsController extends Controller
{
    /**
     * Allowed asset file extensions.
     *
     * Kept intentionally narrow; new extensions must be added
     * consciously so a compromised or careless theme cannot serve
     * arbitrary file types (e.g. `.php`, `.env`).
     *
     * @var array<int, string>
     */
    protected const ALLOWED_EXTENSIONS = [
        'css', 'js', 'mjs', 'map',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'ico',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'json', 'xml', 'txt',
        'mp4', 'webm', 'mp3', 'ogg',
    ];

    /**
     * Explicit MIME map for the extensions in {@see ALLOWED_EXTENSIONS}.
     *
     * `BinaryFileResponse` defers to `finfo`, which classifies text-based
     * assets (CSS, JS, source maps, JSON) as `text/plain` — enough for
     * strict-MIME browsers to reject a stylesheet or ES module. This map
     * pins the correct type for every allowed extension so the browser
     * treats the response the way the theme intended.
     *
     * @var array<string, string>
     */
    protected const MIME_MAP = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'mjs'   => 'application/javascript',
        'map'   => 'application/json',
        'json'  => 'application/json',
        'xml'   => 'application/xml',
        'txt'   => 'text/plain',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'avif'  => 'image/avif',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'eot'   => 'application/vnd.ms-fontobject',
        'mp4'   => 'video/mp4',
        'webm'  => 'video/webm',
        'mp3'   => 'audio/mpeg',
        'ogg'   => 'audio/ogg',
    ];

    /**
     * Serve a static asset from a theme's `assets/` directory.
     *
     * Rejects requests that:
     *  - carry an invalid slug (must match the ThemeManager's slug pattern)
     *  - carry an empty or traversal-laden path
     *  - resolve outside the theme's `assets/` directory
     *  - target a file with a disallowed extension
     *  - target a file that does not exist
     *
     * @since 2.5.0
     *
     * @param  string  $slug  Theme slug (route parameter).
     * @param  string  $path  Path under the theme's `assets/` directory.
     */
    public function show( Request $request, string $slug, string $path ): BinaryFileResponse|Response
    {
        if ( ! $this->isValidSlug( $slug ) ) {
            return response( '', Response::HTTP_NOT_FOUND );
        }

        // Normalize Windows-style backslashes so the traversal check below
        // covers `..\` as well as `..\/`. `realpath()` would catch the
        // eventual escape either way, but layering the check keeps the
        // early-return contract honest across platforms.
        $path = str_replace( '\\', '/', trim( $path ) );

        if ( '' === $path
            || str_contains( $path, "\0" )
            || preg_match( '#(^|/)\.\.(/|$)#', $path )
            || str_starts_with( $path, '/' ) ) {
            return response( '', Response::HTTP_NOT_FOUND );
        }

        $extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        if ( '' === $extension || ! in_array( $extension, self::ALLOWED_EXTENSIONS, true ) ) {
            return response( '', Response::HTTP_NOT_FOUND );
        }

        $themesBase = base_path( config( 'cms.themes.directory', 'themes' ) );
        $assetsBase = $themesBase . '/' . $slug . '/assets';
        $realBase   = realpath( $assetsBase );

        if ( false === $realBase ) {
            return response( '', Response::HTTP_NOT_FOUND );
        }

        $fullPath = $realBase . '/' . ltrim( $path, '/' );
        $realFull = realpath( $fullPath );

        if ( false === $realFull
            || ! str_starts_with( $realFull . DIRECTORY_SEPARATOR, $realBase . DIRECTORY_SEPARATOR ) ) {
            return response( '', Response::HTTP_NOT_FOUND );
        }

        if ( ! File::isFile( $realFull ) ) {
            return response( '', Response::HTTP_NOT_FOUND );
        }

        $response = new BinaryFileResponse( $realFull );

        // Pin the Content-Type from the extension: BinaryFileResponse's
        // finfo-based guess classifies CSS/JS/JSON/source-maps as
        // `text/plain`, which strict-MIME browsers refuse to apply to a
        // `<link rel=stylesheet>` or ES `<script type=module>`.
        if ( isset( self::MIME_MAP[ $extension ] ) ) {
            $response->headers->set( 'Content-Type', self::MIME_MAP[ $extension ] );
        }

        // Defense-in-depth against MIME sniffing on older browsers with
        // relaxed enforcement (`X-Content-Type-Options: nosniff` is a
        // no-op on modern strict browsers but closes the loophole on
        // legacy ones).
        $response->headers->set( 'X-Content-Type-Options', 'nosniff' );

        // SVG hardening: an uploaded theme could ship `logo.svg` with an
        // inline `<script>`; served with `image/svg+xml` from our origin
        // it would execute in the app's security context (same-origin
        // cookies attach). CSP `sandbox` blocks script execution in the
        // top-level document; `Content-Disposition: attachment` prevents
        // the browser from rendering the SVG inline when navigated to
        // directly. Both together neutralize the vector while keeping
        // `<img src="…svg">` (which never executes SVG scripts anyway)
        // working from theme markup.
        if ( 'svg' === $extension ) {
            $response->headers->set( 'Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox" );
            $response->headers->set( 'Content-Disposition', 'attachment' );
        }

        $response->setPublic();
        $response->setMaxAge( (int) config( 'cms.themes.assetsMaxAge', 3600 ) );

        return $response;
    }

    /**
     * Validate a slug against the ThemeManager's canonical pattern.
     *
     * Duplicates the check rather than reaching into ThemeManager so
     * a request rejected here never hits any theme resolution logic.
     *
     * @since 2.5.0
     */
    protected function isValidSlug( string $slug ): bool
    {
        return '' !== $slug && 1 === preg_match( '/^[a-zA-Z0-9_-]+$/', $slug );
    }
}
