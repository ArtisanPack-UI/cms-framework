<?php

declare( strict_types = 1 );

/**
 * Unauthorized exception for the CMS Framework.
 *
 * Thrown when a user attempts to perform an action they're not authorized for.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Exceptions;

/**
 * Exception thrown when authorization fails.
 *
 * @since 1.0.0
 */
class UnauthorizedException extends CMSFrameworkException
{
	/**
	 * Create an unauthorized exception for an action.
	 *
	 * @param  string  $action  The action that was attempted.
	 *
	 * @return self
	 *
	 * @since 1.0.0
	 */
	public static function forAction( string $action ): self
	{
		return new self( "You are not authorized to {$action}." );
	}

	/**
	 * Create an unauthorized exception for a resource.
	 *
	 * @param  string  $resource  The resource type.
	 * @param  string  $action  The action that was attempted.
	 *
	 * @return self
	 *
	 * @since 1.0.0
	 */
	public static function forResource( string $resource, string $action ): self
	{
		return new self( "You are not authorized to {$action} {$resource}." );
	}

	/**
	 * Create an unauthorized exception for permission requirement.
	 *
	 * @param  string  $permission  The required permission.
	 *
	 * @return self
	 *
	 * @since 1.0.0
	 */
	public static function requiresPermission( string $permission ): self
	{
		return new self( "This action requires the '{$permission}' permission." );
	}
}
