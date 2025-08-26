<?php

namespace ArtisanPackUI\CMSFramework\OpenApi;

use OpenApi\Attributes as OA;

/**
 * @OA\OpenApi(
 *     openapi="3.0.0",
 *     @OA\Info(
 *         title="ArtisanPack UI CMS Framework API",
 *         description="Comprehensive REST API for the ArtisanPack UI CMS Framework - a backend framework for building content management systems with any frontend framework.",
 *         version="0.1.0",
 *         termsOfService="https://artisanpack-ui.com/terms",
 *         @OA\Contact(
 *             name="Jacob Martella",
 *             email="me@jacobmartella.com",
 *             url="https://jacobmartella.com"
 *         ),
 *         @OA\License(
 *             name="GPL-3.0-or-later",
 *             url="https://www.gnu.org/licenses/gpl-3.0.html"
 *         )
 *     ),
 *     @OA\Server(
 *         url="http://localhost:8000",
 *         description="Local development server"
 *     ),
 *     @OA\Server(
 *         url="https://your-domain.com",
 *         description="Production server"
 *     ),
 *     @OA\ExternalDocumentation(
 *         description="Find more info here",
 *         url="https://github.com/artisanpack-ui/cms-framework"
 *     )
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     description="Laravel Sanctum token authentication. Prefix token with 'Bearer ' in Authorization header.",
 *     name="Authorization",
 *     in="header",
 *     scheme="bearer",
 *     bearerFormat="token"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="Authentication and authorization endpoints"
 * )
 *
 * @OA\Tag(
 *     name="Content Management",
 *     description="Content CRUD operations and management"
 * )
 *
 * @OA\Tag(
 *     name="Media Management", 
 *     description="Media file upload, management, and organization"
 * )
 *
 * @OA\Tag(
 *     name="User Management",
 *     description="User accounts, roles, and permissions management"
 * )
 *
 * @OA\Tag(
 *     name="System Management",
 *     description="System settings, configurations, and administrative operations"
 * )
 *
 * @OA\Tag(
 *     name="Plugin Management",
 *     description="Plugin installation, activation, and management"
 * )
 *
 * @OA\Tag(
 *     name="Taxonomy Management",
 *     description="Taxonomy and term management for content organization"
 * )
 *
 * Common response schemas
 *
 * @OA\Schema(
 *     schema="Error",
 *     type="object",
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         description="Error message",
 *         example="The given data was invalid."
 *     ),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         description="Validation errors (optional)",
 *         example={"field": {"The field is required."}}
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="Success",
 *     type="object",
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         description="Success message",
 *         example="Operation completed successfully"
 *     ),
 *     @OA\Property(
 *         property="data",
 *         description="Response data (optional)"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PaginatedResponse",
 *     type="object",
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items()
 *     ),
 *     @OA\Property(
 *         property="links",
 *         type="object",
 *         @OA\Property(property="first", type="string", nullable=true),
 *         @OA\Property(property="last", type="string", nullable=true),
 *         @OA\Property(property="prev", type="string", nullable=true),
 *         @OA\Property(property="next", type="string", nullable=true)
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         @OA\Property(property="current_page", type="integer"),
 *         @OA\Property(property="from", type="integer", nullable=true),
 *         @OA\Property(property="last_page", type="integer"),
 *         @OA\Property(property="per_page", type="integer"),
 *         @OA\Property(property="to", type="integer", nullable=true),
 *         @OA\Property(property="total", type="integer")
 *     )
 * )
 *
 * Rate limiting information
 *
 * @OA\Schema(
 *     schema="RateLimitInfo",
 *     type="object",
 *     description="Rate limiting information included in response headers",
 *     @OA\Property(
 *         property="X-RateLimit-Limit",
 *         type="integer",
 *         description="Request limit per time window"
 *     ),
 *     @OA\Property(
 *         property="X-RateLimit-Remaining", 
 *         type="integer",
 *         description="Remaining requests in current time window"
 *     ),
 *     @OA\Property(
 *         property="X-RateLimit-Reset",
 *         type="integer",
 *         description="Unix timestamp when the rate limit resets"
 *     )
 * )
 */
class OpenApiSpec
{
    // This class serves as a container for the main OpenAPI specification annotations
}