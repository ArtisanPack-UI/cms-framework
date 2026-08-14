<?php

/**
 * Extension Archive
 *
 * Shared ZIP-extraction guards for the theme and plugin modules: zip-slip /
 * path-traversal rejection and an uncompressed-size ceiling, factored into one
 * place so the two modules cannot drift (the theme install extractor was the
 * reference implementation; the plugin extractor had none).
 *
 * @since      2.8.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Core\Updates\Support;

use ZipArchive;

/**
 * Stateless ZIP-archive safety checks.
 *
 * @since 2.8.0
 */
final class ExtensionArchive
{
    /**
     * Return the first archive entry that is unsafe to extract, or null.
     *
     * An entry is unsafe when it is an absolute path, contains a `..`
     * traversal segment, or — when the extraction is anchored — resolves to a
     * destination outside `$realBaseDir`. When `$requireTopSegment` is given,
     * every entry must also live under that top-level directory; a ZIP
     * carrying a *sibling* top-level directory would otherwise overwrite a
     * different, already-trusted extension's files.
     *
     * @since 2.8.0
     *
     * @param  ZipArchive  $zip  Opened archive.
     * @param  string|null  $realBaseDir  Absolute realpath of the extraction root, or null to skip anchoring (e.g. when restoring into a directory that does not exist yet).
     * @param  string|null  $requireTopSegment  Slug every entry's top segment must equal, or null to allow flat/relative entries.
     *
     * @return string|null The offending entry name, or null when every entry is safe.
     */
    public static function firstUnsafeEntry(
        ZipArchive $zip,
        ?string $realBaseDir = null,
        ?string $requireTopSegment = null,
    ): ?string {
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $entry = $zip->getNameIndex( $i );

            if ( false === $entry ) {
                continue;
            }

            $normalized = str_replace( '\\', '/', $entry );

            // Reject absolute paths and any entry containing traversal segments.
            if ( str_starts_with( $normalized, '/' )
                || 1 === preg_match( '#(^|/)\.\.(/|$)#', $normalized ) ) {
                return $entry;
            }

            if ( null !== $requireTopSegment ) {
                $topSegment = explode( '/', $normalized, 2 )[0];

                if ( $topSegment !== $requireTopSegment ) {
                    return $entry;
                }
            }

            if ( null === $realBaseDir ) {
                continue;
            }

            $destination = $realBaseDir . '/' . ltrim( $normalized, '/' );

            // Walk up the destination path until an existing ancestor is found,
            // then verify that ancestor sits inside the extraction root.
            $ancestor = dirname( $destination );
            while ( '' !== $ancestor && '/' !== $ancestor && ! file_exists( $ancestor ) ) {
                $ancestor = dirname( $ancestor );
            }

            $realAncestor = realpath( $ancestor );

            if ( false === $realAncestor || ! str_starts_with( $realAncestor . '/', $realBaseDir . '/' ) ) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Total uncompressed size of every entry in the archive, in bytes.
     *
     * The compressed size of a ZIP says nothing about what it expands to: a
     * few-megabyte archive can decompress to tens of gigabytes and exhaust the
     * disk mid-extraction. Callers compare this against a configured ceiling
     * before calling `extractTo()`.
     *
     * @since 2.8.0
     *
     * @param  ZipArchive  $zip  Opened archive.
     *
     * @return int Total uncompressed size in bytes.
     */
    public static function uncompressedSize( ZipArchive $zip ): int
    {
        $total = 0;

        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $stat = $zip->statIndex( $i );

            if ( false === $stat ) {
                continue;
            }

            $total += (int) ( $stat['size'] ?? 0 );
        }

        return $total;
    }
}
