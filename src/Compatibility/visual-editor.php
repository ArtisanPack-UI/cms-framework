<?php

/**
 * Polyfill stub for ArtisanPackUI\VisualEditor\Concerns\HasBlockContent.
 *
 * Loaded via composer's autoload.files. When artisanpack-ui/visual-editor is
 * installed, the real trait is autoloaded via PSR-4 and this stub is skipped.
 * When visual-editor is not installed, the stub registers a minimal trait so
 * Post and Page (which `use HasBlockContent`) still load and their
 * `block_content` column casts to array.
 *
 * Keeps cms-framework's adoption of the trait soft so visual-editor remains
 * an optional integration rather than a hard composer dependency.
 *
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Concerns {
    if ( ! trait_exists( __NAMESPACE__ . '\\HasBlockContent', true ) ) {
        /**
         * Minimal stub of HasBlockContent.
         *
         * Only implements cast registration and column resolution — enough for
         * models to round-trip the `block_content` JSON column. Apps that
         * actually need the editor surface install artisanpack-ui/visual-editor,
         * which ships the full trait (search extractor, query scope, etc.).
         *
         * @mixin \Illuminate\Database\Eloquent\Model
         */
        trait HasBlockContent
        {
            /**
             * Registers an `array` cast for the configured block content column.
             *
             * @since 2.0.0
             */
            public function initializeHasBlockContent(): void
            {
                $column = $this->getBlockContentColumn();

                if ( ! array_key_exists( $column, $this->getCasts() ) ) {
                    $this->mergeCasts( [$column => 'array'] );
                }
            }

            /**
             * Returns the column that stores the block tree JSON.
             *
             * Override by setting `protected $blockContentColumn = 'body';` on the model.
             *
             * @since 2.0.0
             */
            public function getBlockContentColumn(): string
            {
                return isset( $this->blockContentColumn ) && is_string( $this->blockContentColumn )
                    ? $this->blockContentColumn
                    : 'content';
            }

            /**
             * Returns the current block tree for the model.
             *
             * @since 2.0.0
             *
             * @return array<int, array<string, mixed>>
             */
            public function getBlockContent(): array
            {
                $value = $this->getAttribute( $this->getBlockContentColumn() );

                return is_array( $value ) ? $value : [];
            }

            /**
             * Persists a new block tree for the model.
             *
             * @since 2.0.0
             *
             * @param  array<int, array<string, mixed>>  $blocks  The block tree to store.
             */
            public function setBlockContent( array $blocks ): void
            {
                $this->setAttribute( $this->getBlockContentColumn(), $blocks);
            }
        }
    }
}
