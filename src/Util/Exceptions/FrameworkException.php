<?php
/**
 * Represents a custom exception specifically designed for handling
 * framework-related errors within the CMSFramework.
 *
 * This exception extends the base Exception class and may be used
 * to handle errors or disruptions specific to the ArtisanPackUI
 * CMSFramework. It can provide additional context or functionality
 * beyond the standard exception handling.
 *
 * Typically, this exception may be thrown in cases such as invalid
 * framework configuration, unsupported features, or general internal
 * framework errors that require special handling.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Util\Interfaces\FrameworkException
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Util\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Represents a custom exception specific to the framework.
 *
 * This class serves as the base exception class for handling framework-related errors.
 * It extends the built-in Exception class, allowing it to leverage all native exception handling capabilities.
 *
 * @since 1.0.0
 */
class FrameworkException extends Exception
{

}