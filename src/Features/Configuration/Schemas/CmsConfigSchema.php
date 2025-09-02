<?php

namespace ArtisanPackUI\CMSFramework\Features\Configuration\Schemas;

/**
 * CMS Configuration Schema
 * 
 * Defines validation rules and structure for the main CMS configuration file.
 * This schema ensures that all configuration values are valid and properly typed.
 */
class CmsConfigSchema
{
    /**
     * Get the validation schema for CMS configuration
     */
    public static function getSchema(): array
    {
        return [
            'site' => [
                'type' => 'array',
                'required' => true,
                'rules' => [
                    'site.name' => 'required|string|max:255',
                    'site.tagline' => 'nullable|string|max:500',
                    'site.url' => 'required|url',
                    'site.timezone' => 'required|string|max:100',
                    'site.locale' => 'required|string|size:2',
                ],
                'description' => 'Site-wide configuration settings'
            ],
            
            'paths' => [
                'type' => 'array',
                'required' => true,
                'rules' => [
                    'paths.plugins' => 'required|string',
                    'paths.themes' => 'required|string',
                ],
                'description' => 'File system paths for plugins and themes'
            ],
            
            
            'content_types' => [
                'type' => 'array',
                'required' => true,
                'rules' => [
                    'content_types' => 'required|array|min:1',
                    'content_types.*.label' => 'required|string|max:100',
                    'content_types.*.label_plural' => 'required|string|max:100',
                    'content_types.*.slug' => 'required|string|max:50|regex:/^[a-z0-9_-]+$/',
                    'content_types.*.public' => 'required|boolean',
                    'content_types.*.hierarchical' => 'required|boolean',
                    'content_types.*.supports' => 'required|array',
                    'content_types.*.supports.*' => 'string|in:title,content,author,featured_image,status,categories,tags,parent,order,excerpt,meta',
                    'content_types.*.fields' => 'array',
                ],
                'description' => 'Content type definitions and configurations'
            ],
            
            'taxonomies' => [
                'type' => 'array',
                'required' => true,
                'rules' => [
                    'taxonomies' => 'required|array',
                    'taxonomies.*.label' => 'required|string|max:100',
                    'taxonomies.*.label_plural' => 'required|string|max:100',
                    'taxonomies.*.hierarchical' => 'required|boolean',
                    'taxonomies.*.content_types' => 'required|array|min:1',
                    'taxonomies.*.content_types.*' => 'string|max:50',
                ],
                'description' => 'Taxonomy definitions for categorizing content'
            ],
            
            'theme' => [
                'type' => 'array',
                'required' => true,
                'rules' => [
                    'theme.active' => 'required|string|max:100|regex:/^[a-z0-9_-]+$/',
                ],
                'description' => 'Theme configuration settings'
            ],
            
            'rate_limiting' => [
                'type' => 'array',
                'required' => true,
                'rules' => [
                    'rate_limiting.enabled' => 'required|boolean',
                    'rate_limiting.general' => 'required|array',
                    'rate_limiting.general.requests_per_minute' => 'required|integer|min:1|max:1000',
                    'rate_limiting.general.key_generator' => 'required|string|in:user_ip,user_id,ip_only',
                    'rate_limiting.auth' => 'required|array',
                    'rate_limiting.auth.requests_per_minute' => 'required|integer|min:1|max:100',
                    'rate_limiting.auth.key_generator' => 'required|string|in:user_ip,user_id,ip_only',
                    'rate_limiting.admin' => 'required|array',
                    'rate_limiting.admin.requests_per_minute' => 'required|integer|min:1|max:500',
                    'rate_limiting.admin.key_generator' => 'required|string|in:user_ip,user_id,ip_only',
                    'rate_limiting.upload' => 'required|array',
                    'rate_limiting.upload.requests_per_minute' => 'required|integer|min:1|max:100',
                    'rate_limiting.upload.key_generator' => 'required|string|in:user_ip,user_id,ip_only',
                    'rate_limiting.bypass' => 'required|array',
                    'rate_limiting.bypass.enabled' => 'required|boolean',
                    'rate_limiting.bypass.admin_capabilities' => 'required|array|min:1',
                    'rate_limiting.bypass.admin_capabilities.*' => 'string|max:50',
                    'rate_limiting.headers' => 'required|array',
                    'rate_limiting.headers.enabled' => 'required|boolean',
                    'rate_limiting.headers.remaining_header' => 'required|string|max:50',
                    'rate_limiting.headers.limit_header' => 'required|string|max:50',
                    'rate_limiting.headers.retry_after_header' => 'required|string|max:50',
                ],
                'description' => 'Rate limiting configuration for API endpoints'
            ],
        ];
    }
    
    /**
     * Get all validation rules flattened for Laravel validator
     */
    public static function getRules(): array
    {
        $rules = [];
        $schema = self::getSchema();
        
        foreach ($schema as $section => $config) {
            if (isset($config['rules'])) {
                $rules = array_merge($rules, $config['rules']);
            }
        }
        
        return $rules;
    }
    
    /**
     * Get validation messages for better error reporting
     */
    public static function getMessages(): array
    {
        return [
            'site.name.required' => 'Site name is required',
            'site.name.max' => 'Site name cannot exceed 255 characters',
            'site.url.required' => 'Site URL is required',
            'site.url.url' => 'Site URL must be a valid URL',
            'site.timezone.required' => 'Site timezone is required',
            'site.locale.required' => 'Site locale is required',
            'site.locale.size' => 'Site locale must be exactly 2 characters',
            
            'paths.plugins.required' => 'Plugins path is required',
            'paths.themes.required' => 'Themes path is required',
            
            'content_types.required' => 'At least one content type must be defined',
            'content_types.min' => 'At least one content type must be defined',
            'content_types.*.label.required' => 'Content type label is required',
            'content_types.*.slug.required' => 'Content type slug is required',
            'content_types.*.slug.regex' => 'Content type slug must contain only lowercase letters, numbers, hyphens, and underscores',
            'content_types.*.public.required' => 'Content type public setting is required',
            'content_types.*.hierarchical.required' => 'Content type hierarchical setting is required',
            'content_types.*.supports.required' => 'Content type supports array is required',
            
            'taxonomies.*.label.required' => 'Taxonomy label is required',
            'taxonomies.*.hierarchical.required' => 'Taxonomy hierarchical setting is required',
            'taxonomies.*.content_types.required' => 'Taxonomy must be associated with at least one content type',
            
            'theme.active.required' => 'Active theme is required',
            'theme.active.regex' => 'Theme name must contain only lowercase letters, numbers, hyphens, and underscores',
            
            'rate_limiting.enabled.required' => 'Rate limiting enabled setting is required',
            'rate_limiting.*.requests_per_minute.required' => 'Requests per minute is required for rate limiting',
            'rate_limiting.*.requests_per_minute.min' => 'Requests per minute must be at least 1',
            'rate_limiting.*.key_generator.required' => 'Rate limiting key generator is required',
            'rate_limiting.*.key_generator.in' => 'Rate limiting key generator must be one of: user_ip, user_id, ip_only',
        ];
    }
    
    /**
     * Get schema documentation
     */
    public static function getDocumentation(): array
    {
        return [
            'title' => 'CMS Configuration Schema',
            'version' => '1.0.0',
            'description' => 'Validation schema for the main CMS configuration file',
            'sections' => self::getSchema(),
            'examples' => [
                'site' => [
                    'name' => 'My CMS Site',
                    'tagline' => 'A powerful content management system',
                    'url' => 'https://example.com',
                    'timezone' => 'UTC',
                    'locale' => 'en',
                ],
            ],
        ];
    }
}