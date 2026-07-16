<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\ContentEditExtensions;

test( 'panels() returns filter-registered panels ordered by order', function (): void {
    $manager = new ContentEditExtensions;

    addFilter( 'ap.admin.contentEdit.panels', function ( array $panels ): array {
        $panels[] = ['slug' => 'seo', 'title' => 'SEO', 'component' => 'SeoPanel', 'order' => 20];
        $panels[] = ['slug' => 'schedule', 'title' => 'Schedule', 'component' => 'SchedulePanel', 'order' => 5];

        return $panels;
    } );

    $panels = $manager->panels( ['contentType' => 'post', 'recordId' => 42] );

    expect( $panels )->toHaveCount( 2 );
    expect( $panels[0]['slug'] )->toBe( 'schedule' );
    expect( $panels[1]['slug'] )->toBe( 'seo' );
} );

test( 'panels() filters by contentTypes restriction', function (): void {
    $manager = new ContentEditExtensions;

    addFilter( 'ap.admin.contentEdit.panels', function ( array $panels ): array {
        $panels[] = ['slug' => 'gallery', 'title' => 'Gallery', 'component' => 'GalleryPanel', 'contentTypes' => ['portfolio']];
        $panels[] = ['slug' => 'universal', 'title' => 'Universal', 'component' => 'AllPanel', 'contentTypes' => ['*']];
        $panels[] = ['slug' => 'no-restriction', 'title' => 'Any', 'component' => 'AnyPanel'];

        return $panels;
    } );

    $portfolioPanels = $manager->panels( ['contentType' => 'portfolio'] );
    $postPanels      = $manager->panels( ['contentType' => 'post'] );

    expect( array_column( $portfolioPanels, 'slug' ) )->toBe( ['gallery', 'universal', 'no-restriction'] );
    expect( array_column( $postPanels, 'slug' ) )->toBe( ['universal', 'no-restriction'] );
} );

test( 'panels() rejects entries missing slug or component', function (): void {
    $manager = new ContentEditExtensions;

    addFilter( 'ap.admin.contentEdit.panels', function ( array $panels ): array {
        $panels[] = ['title' => 'No slug', 'component' => 'X'];
        $panels[] = ['slug' => 'no-component', 'title' => 'Bad'];
        $panels[] = ['slug' => 'ok', 'title' => 'Good', 'component' => 'OkPanel'];

        return $panels;
    } );

    $panels = $manager->panels( [] );

    expect( array_column( $panels, 'slug' ) )->toBe( ['ok'] );
} );

test( 'tabs() and beforeEditor()/afterEditor() honor the same filter contract', function (): void {
    $manager = new ContentEditExtensions;

    addFilter( 'ap.admin.contentEdit.tabs', function ( array $items ): array {
        $items[] = ['slug' => 'reviews', 'title' => 'Reviews', 'component' => 'ReviewsTab'];

        return $items;
    } );
    addFilter( 'ap.admin.contentEdit.beforeEditor', function ( array $items ): array {
        $items[] = ['slug' => 'banner', 'title' => 'Banner', 'component' => 'BannerBlock'];

        return $items;
    } );
    addFilter( 'ap.admin.contentEdit.afterEditor', function ( array $items ): array {
        $items[] = ['slug' => 'footer', 'title' => 'Footer', 'component' => 'FooterBlock'];

        return $items;
    } );

    expect( $manager->tabs( ['contentType' => 'post'] )[0]['slug'] )->toBe( 'reviews' );
    expect( $manager->beforeEditor( ['contentType' => 'post'] )[0]['slug'] )->toBe( 'banner' );
    expect( $manager->afterEditor( ['contentType' => 'post'] )[0]['slug'] )->toBe( 'footer' );
} );

test( 'saveData() runs the payload through the filter and preserves fallback', function (): void {
    $manager = new ContentEditExtensions;

    addFilter( 'ap.admin.contentEdit.saveData', function ( array $data ): array {
        $data['title'] = strtoupper( $data['title'] );

        return $data;
    } );

    $result = $manager->saveData( ['title' => 'hello'], ['contentType' => 'post'] );

    expect( $result['title'] )->toBe( 'HELLO' );
} );
