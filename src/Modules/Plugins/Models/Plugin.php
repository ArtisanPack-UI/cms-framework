<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Modules\Plugins\Models;

use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'version',
        'is_active',
        'service_provider',
        'meta',
        'installed_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
        'installed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope to get only active plugins.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get plugin path on filesystem.
     */
    public function getPath(): string
    {
        return base_path(config('cms.plugins.directory', 'plugins').'/'.$this->slug);
    }

    /**
     * Get plugin manifest data.
     */
    public function getManifest(): array
    {
        return $this->meta ?? [];
    }

    /**
     * Check if plugin has a service provider.
     */
    public function hasServiceProvider(): bool
    {
        return ! empty($this->service_provider);
    }
}
