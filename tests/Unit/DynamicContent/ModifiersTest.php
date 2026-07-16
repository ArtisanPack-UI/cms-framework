<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers\DateModifier;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers\DefaultModifier;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers\LowerModifier;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers\Nl2brModifier;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers\TimeModifier;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers\TruncateModifier;
use ArtisanPackUI\CMSFramework\Modules\DynamicContent\Modifiers\UpperModifier;

test( 'default modifier substitutes when empty', function (): void {
    $m = new DefaultModifier();

    expect( $m->apply( null, [ 'fallback' ] ) )->toBe( 'fallback' );
    expect( $m->apply( '', [ 'fallback' ] ) )->toBe( 'fallback' );
    expect( $m->apply( 'value', [ 'fallback' ] ) )->toBe( 'value' );
} );

test( 'upper modifier uppercases strings', function (): void {
    expect( ( new UpperModifier() )->apply( 'abc', [] ) )->toBe( 'ABC' );
    expect( ( new UpperModifier() )->apply( 42, [] ) )->toBe( 42 );
} );

test( 'lower modifier lowercases strings', function (): void {
    expect( ( new LowerModifier() )->apply( 'ABC', [] ) )->toBe( 'abc' );
} );

test( 'truncate modifier respects length', function (): void {
    $m = new TruncateModifier();
    expect( $m->apply( 'short', [ '10' ] ) )->toBe( 'short' );
    expect( $m->apply( 'this is a long string', [ '4' ] ) )->toBe( 'this…' );
} );

test( 'date modifier formats parseable input', function (): void {
    expect( ( new DateModifier() )->apply( '2026-07-16', [ 'Y-m-d' ] ) )->toBe( '2026-07-16' );
    expect( ( new DateModifier() )->apply( null, [ 'Y-m-d' ] ) )->toBe( '' );
} );

test( 'time modifier reassembles colon-split args', function (): void {
    // "time:g:i a" -> args ['g','i a']
    expect( ( new TimeModifier() )->apply( '2026-07-16 15:04', [ 'g', 'i a' ] ) )->toBe( '3:04 pm' );
} );

test( 'nl2br modifier converts newlines and returns HtmlString', function (): void {
    $result = ( new Nl2brModifier() )->apply( "a\nb", [] );
    expect( $result )->toBeInstanceOf( Illuminate\Support\HtmlString::class );
    expect( (string) $result )->toContain( '<br' );
} );

test( 'nl2br modifier escapes HTML in input before adding br tags', function (): void {
    $result = ( new Nl2brModifier() )->apply( "<img src=x onerror=alert(1)>\nnext", [] );
    $out    = (string) $result;
    expect( $out )->not->toContain( '<img' );
    expect( $out )->toContain( '&lt;img' );
    expect( $out )->toContain( '<br' );
} );
