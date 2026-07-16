<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Enums\Cardinality;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentRecordManager;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Managers\DynamicContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Services\DynamicContentResolver;

beforeEach( function (): void {
    $this->typeManager   = app( DynamicContentTypeManager::class );
    $this->recordManager = app( DynamicContentRecordManager::class );
    $this->resolver      = app( DynamicContentResolver::class );
} );

test( 'resolves singleton tokens', function (): void {
    $type = $this->typeManager->create( [
        'slug'        => 'business_info',
        'name'        => 'Business Info',
        'cardinality' => 'singleton',
        'fields'      => [
            [ 'slug' => 'phone', 'label' => 'Phone', 'type' => 'phone' ],
            [ 'slug' => 'email', 'label' => 'Email', 'type' => 'email' ],
        ],
    ] );

    $this->recordManager->create( $type, [
        'values' => [ 'phone' => '555-1212', 'email' => 'hi@example.com' ],
    ] );

    $out = $this->resolver->render(
        'Call {{business_info.phone}} or email {{business_info.email}}.',
    );

    expect( $out )->toBe( 'Call 555-1212 or email hi@example.com.' );
} );

test( 'resolves collection tokens via index', function (): void {
    $type = $this->typeManager->create( [
        'slug'        => 'team',
        'name'        => 'Team',
        'cardinality' => 'collection',
        'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
    ] );

    $this->recordManager->create( $type, [ 'label' => 'A', 'values' => [ 'name' => 'Alice' ] ] );
    $this->recordManager->create( $type, [ 'label' => 'B', 'values' => [ 'name' => 'Bob' ] ] );

    expect( $this->resolver->render( 'Lead: {{team[0].name}}' ) )->toBe( 'Lead: Alice' );
    expect( $this->resolver->render( 'Second: {{team[1].name}}' ) )->toBe( 'Second: Bob' );
} );

test( 'missing source renders empty and does not leak braces', function (): void {
    $out = $this->resolver->render( 'Hello {{unknown.thing}} world' );

    expect( $out )->toBe( 'Hello  world' );
} );

test( 'default modifier applies when value is missing', function (): void {
    $type = $this->typeManager->create( [
        'slug'        => 'site',
        'name'        => 'Site',
        'cardinality' => 'singleton',
        'fields'      => [ [ 'slug' => 'tag', 'label' => 'Tag', 'type' => 'text' ] ],
    ] );

    $this->recordManager->create( $type, [ 'values' => [ 'tag' => null ] ] );

    expect( $this->resolver->render( '{{site.tag|default:none}}' ) )->toBe( 'none' );
} );

test( 'upper modifier transforms resolved value', function (): void {
    $type = $this->typeManager->create( [
        'slug'        => 'brand',
        'name'        => 'Brand',
        'cardinality' => 'singleton',
        'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
    ] );

    $this->recordManager->create( $type, [ 'values' => [ 'name' => 'acme' ] ] );

    expect( $this->resolver->render( '{{brand.name|upper}}' ) )->toBe( 'ACME' );
} );

test( 'resolver does not recurse into resolved output', function (): void {
    $type = $this->typeManager->create( [
        'slug'        => 'nested',
        'name'        => 'Nested',
        'cardinality' => 'singleton',
        'fields'      => [ [ 'slug' => 'ref', 'label' => 'Ref', 'type' => 'text' ] ],
    ] );

    $this->recordManager->create( $type, [ 'values' => [ 'ref' => '{{nested.ref}}' ] ] );

    expect( $this->resolver->render( 'Value: {{nested.ref}}' ) )->toBe( 'Value: {{nested.ref}}' );
} );

test( 'malformed tokens with braces do not throw', function (): void {
    $out = $this->resolver->render( '{{ }} and {{ .field }}' );

    expect( $out )->toBeString();
} );

test( 'code-registered types resolve alongside db types', function (): void {
    apRegisterDynamicContentType( 'from_code', [
        'name'        => 'From Code',
        'cardinality' => Cardinality::Singleton,
        'fields'      => [
            [ 'slug' => 'greeting', 'label' => 'Greeting', 'type' => 'text' ],
        ],
        'records' => [
            [ 'greeting' => 'Howdy' ],
        ],
    ] );

    expect( $this->resolver->render( '{{from_code.greeting}}' ) )->toBe( 'Howdy' );
} );

test( 'signatureFor returns a stable string when there are no tokens', function (): void {
    expect( $this->resolver->signatureFor( 'plain content' ) )->toBe( 'dc:none' );
} );

test( 'text field values are HTML-escaped on render', function (): void {
    $type = $this->typeManager->create( [
        'slug'        => 'settings',
        'name'        => 'Settings',
        'cardinality' => 'singleton',
        'fields'      => [ [ 'slug' => 'tagline', 'label' => 'Tagline', 'type' => 'text' ] ],
    ] );

    $this->recordManager->create( $type, [ 'values' => [ 'tagline' => '<script>alert(1)</script>' ] ] );

    $out = $this->resolver->render( 'Site: {{settings.tagline}}' );

    expect( $out )->not->toContain( '<script>' );
    expect( $out )->toContain( '&lt;script&gt;' );
} );

test( 'rich text field values render raw HTML', function (): void {
    $type = $this->typeManager->create( [
        'slug'        => 'article',
        'name'        => 'Article',
        'cardinality' => 'singleton',
        'fields'      => [ [ 'slug' => 'body', 'label' => 'Body', 'type' => 'rich_text' ] ],
    ] );

    $this->recordManager->create( $type, [ 'values' => [ 'body' => '<p>Hello <strong>world</strong></p>' ] ] );

    $out = $this->resolver->render( '{{article.body}}' );

    expect( $out )->toContain( '<p>' );
    expect( $out )->toContain( '<strong>' );
} );

test( 'unrouted values are escaped defensively', function (): void {
    // No such type — value stays null and renders as empty; but if a source
    // exists without a field-type match, the fallback path must escape.
    apRegisterDynamicContentType( 'brandcode', [
        'name'        => 'Brand',
        'cardinality' => Cardinality::Singleton,
        'fields'      => [ [ 'slug' => 'raw', 'label' => 'Raw', 'type' => 'nonexistent_type' ] ],
        'records'     => [ [ 'raw' => '<img src=x onerror=alert(1)>' ] ],
    ] );

    $out = $this->resolver->render( '{{brandcode.raw}}' );

    expect( $out )->not->toContain( '<img' );
    expect( $out )->toContain( '&lt;img' );
} );

test( 'nl2br modifier output is not double-escaped by field renderer', function (): void {
    $type = $this->typeManager->create( [
        'slug'        => 'notes',
        'name'        => 'Notes',
        'cardinality' => 'singleton',
        'fields'      => [ [ 'slug' => 'body', 'label' => 'Body', 'type' => 'text' ] ],
    ] );

    $this->recordManager->create( $type, [ 'values' => [ 'body' => "line1\nline2" ] ] );

    $out = $this->resolver->render( '{{notes.body|nl2br}}' );

    expect( $out )->toContain( '<br' );
    expect( $out )->toContain( 'line1' );
    expect( $out )->toContain( 'line2' );
} );

test( 'slug is immutable on update at the manager level', function (): void {
    $type = $this->typeManager->create( [
        'slug'        => 'immutable',
        'name'        => 'Immutable',
        'cardinality' => 'singleton',
        'fields'      => [],
    ] );

    $this->typeManager->update( $type, [
        'slug' => 'renamed',
        'name' => 'Renamed',
    ] );

    expect( $type->fresh()->slug )->toBe( 'immutable' );
    expect( $type->fresh()->name )->toBe( 'Renamed' );
} );
