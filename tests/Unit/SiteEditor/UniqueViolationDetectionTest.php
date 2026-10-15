<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\BlockPatternsController;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\BlocksController;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\MenusController;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\TemplatePartsController;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Controllers\TemplatesController;
use Illuminate\Database\QueryException;

/**
 * Build a QueryException the way the PDO driver raises one.
 *
 * @param  array{0: string, 1: int|null, 2: string}  $errorInfo
 */
function siteEditorQueryException( array $errorInfo, string $sql = 'insert into "block_patterns" ("title") values (?)', array $bindings = ['A unique hero'] ): QueryException
{
    $pdo            = new PDOException( 'SQLSTATE[' . $errorInfo[0] . ']: ' . $errorInfo[2] );
    $pdo->errorInfo = $errorInfo;

    $code = new ReflectionProperty( Exception::class, 'code' );
    $code->setValue( $pdo, $errorInfo[0] );

    return new QueryException( 'sqlite', $sql, $bindings, $pdo );
}

function siteEditorIsUniqueViolation( string $controller, QueryException $exception ): bool
{
    $method = new ReflectionMethod( $controller, 'isUniqueViolation' );

    return $method->invoke( ( new ReflectionClass( $controller ) )->newInstanceWithoutConstructor(), $exception );
}

dataset( 'site editor controllers', [
    'block patterns' => [BlockPatternsController::class],
    'blocks'         => [BlocksController::class],
    'templates'      => [TemplatesController::class],
    'template parts' => [TemplatePartsController::class],
    'menus'          => [MenusController::class],
] );

it( 'recognizes unique violations from each driver', function ( string $controller ): void {
    expect( siteEditorIsUniqueViolation( $controller, siteEditorQueryException( ['23000', 19, 'UNIQUE constraint failed: block_patterns.slug'] ) ) )->toBeTrue()
        ->and( siteEditorIsUniqueViolation( $controller, siteEditorQueryException( ['23000', 1062, "Duplicate entry 'user/hero' for key 'block_patterns_slug_unique'"] ) ) )->toBeTrue()
        ->and( siteEditorIsUniqueViolation( $controller, siteEditorQueryException( ['23505', 7, 'duplicate key value violates unique constraint'] ) ) )->toBeTrue();
} )->with( 'site editor controllers' );

it( 'does not treat other integrity errors as unique violations', function ( string $controller ): void {
    expect( siteEditorIsUniqueViolation( $controller, siteEditorQueryException( ['23000', 19, 'NOT NULL constraint failed: block_patterns.inserter'] ) ) )->toBeFalse()
        ->and( siteEditorIsUniqueViolation( $controller, siteEditorQueryException( ['23000', 1452, 'Cannot add or update a child row: a foreign key constraint fails'] ) ) )->toBeFalse();
} )->with( 'site editor controllers' );
