<?php

declare( strict_types = 1 );

/**
 * Base exception for the CMS Framework.
 *
 * All framework-specific exceptions should extend this class to provide a consistent
 * exception hierarchy and allow for catch-all exception handling when needed.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Exceptions;

use Exception;

/**
 * Base exception class for all CMS Framework exceptions.
 *
 * This class provides a foundation for all framework-specific exceptions,
 * allowing applications to catch all framework exceptions if needed while
 * still maintaining granular exception handling for specific cases.
 *
 * @since 1.0.0
 */
class CMSFrameworkException extends Exception
{
	/**
	 * Create a new CMS Framework exception.
	 *
	 * @param  string  $message  The exception message.
	 * @param  int  $code  The exception code (default: 0).
	 * @param  Exception|null  $previous  The previous exception for chaining (default: null).
	 *
	 * @since 1.0.0
	 */
	public function __construct( string $message = '', int $code = 0, ?Exception $previous = null )
	{
		parent::__construct( $message, $code, $previous );
	}
}
