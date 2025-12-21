<?php

declare( strict_types = 1 );

/**
 * Validation exception for the CMS Framework.
 *
 * Thrown when validation fails for user input or data.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Exceptions;

/**
 * Exception thrown when validation fails.
 *
 * @since 1.0.0
 */
class ValidationException extends CMSFrameworkException
{
	/**
	 * The validation errors.
	 *
	 * @var array<string, array<string>>
	 */
	protected array $errors = [];

	/**
	 * Create a new validation exception with errors.
	 *
	 * @param  string  $message  The exception message.
	 * @param  array<string, array<string>>  $errors  The validation errors.
	 *
	 * @since 1.0.0
	 */
	public static function withErrors( string $message, array $errors ): self
	{
		$exception         = new self( $message );
		$exception->errors = $errors;

		return $exception;
	}

	/**
	 * Get the validation errors.
	 *
	 * @return array<string, array<string>>
	 *
	 * @since 1.0.0
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}

	/**
	 * Check if a specific field has errors.
	 *
	 * @param  string  $field  The field name.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function hasError( string $field ): bool
	{
		return isset( $this->errors[ $field ] );
	}
}
