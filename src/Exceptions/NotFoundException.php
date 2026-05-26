<?php

declare( strict_types=1 );

/**
 * Not found exception for the CMS Framework.
 *
 * Thrown when a requested resource cannot be found.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Exceptions;

/**
 * Exception thrown when a resource is not found.
 *
 * @since 1.0.0
 */
class NotFoundException extends CMSFrameworkException
{
    /**
     * The machine-readable error code for this exception.
     *
     * @since 1.1.0
     */
    protected string $errorCode = 'NOT_FOUND';

    /**
     * The HTTP status code for this exception.
     *
     * @since 1.1.0
     */
    protected int $statusCode = 404;

    /**
     * Create a not found exception for a model.
     *
     * @param  string  $model  The model class name.
     * @param  int|string  $id  The model ID.
     *
     * @since 1.0.0
     */
    public static function model( string $model, int|string $id ): self
    {
        return new self( "Model {$model} with ID {$id} not found." );
    }

    /**
     * Create a not found exception for a resource.
     *
     * @param  string  $resource  The resource type.
     * @param  string  $identifier  The resource identifier.
     *
     * @since 1.0.0
     */
    public static function resource( string $resource, string $identifier ): self
    {
        return new self( "{$resource} '{$identifier}' not found." );
    }
}
