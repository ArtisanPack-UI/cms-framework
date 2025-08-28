<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Features\Internationalization\Controllers;

use ArtisanPackUI\CMSFramework\Features\Internationalization\Models\Language;
use ArtisanPackUI\CMSFramework\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class LanguageController extends ApiController
{
    /**
     * Display a listing of languages.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Language::query();

        if ($request->filled('active')) {
            $query->active();
        }

        if ($request->filled('rtl')) {
            $query->rtl();
        }

        $languages = $query->ordered()->get();

        return $this->success([
            'languages' => $languages->map->toApiArray(),
            'meta' => [
                'total' => $languages->count(),
                'active_count' => $languages->where('is_active', true)->count(),
                'rtl_count' => $languages->where('is_rtl', true)->count(),
            ]
        ]);
    }

    /**
     * Store a newly created language.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|size:2|unique:languages,code',
            'locale' => 'required|string|max:10|unique:languages,locale',
            'native_name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_fallback' => 'boolean',
            'is_rtl' => 'boolean',
            'sort_order' => 'integer|min:0',
            'date_format' => 'nullable|string|max:50',
            'time_format' => 'nullable|string|max:50',
            'datetime_format' => 'nullable|string|max:50',
            'decimal_separator' => 'nullable|string|size:1',
            'thousands_separator' => 'nullable|string|size:1',
            'metadata' => 'nullable|array',
        ]);

        // Handle default language logic
        if ($validated['is_default'] ?? false) {
            Language::where('is_default', true)->update(['is_default' => false]);
        }

        // Handle fallback language logic
        if ($validated['is_fallback'] ?? false) {
            Language::where('is_fallback', true)->update(['is_fallback' => false]);
        }

        $language = Language::create($validated);

        return $this->success([
            'language' => $language->toApiArray(),
            'message' => 'Language created successfully.'
        ], 201);
    }

    /**
     * Display the specified language.
     */
    public function show(Language $language): JsonResponse
    {
        return $this->success([
            'language' => $language->toApiArray(),
            'statistics' => [
                'total_translations' => $language->translations()->count(),
                'completed_translations' => $language->translations()->completed()->count(),
                'pending_translations' => $language->translations()->needsReview()->count(),
                'completion_percentage' => $language->completion_percentage ?? 0,
                'completion_status' => $language->getCompletionStatus(),
            ]
        ]);
    }

    /**
     * Update the specified language.
     */
    public function update(Request $request, Language $language): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => ['sometimes', 'string', 'size:2', Rule::unique('languages')->ignore($language)],
            'locale' => ['sometimes', 'string', 'max:10', Rule::unique('languages')->ignore($language)],
            'native_name' => 'sometimes|string|max:255',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_fallback' => 'boolean',
            'is_rtl' => 'boolean',
            'sort_order' => 'integer|min:0',
            'date_format' => 'nullable|string|max:50',
            'time_format' => 'nullable|string|max:50',
            'datetime_format' => 'nullable|string|max:50',
            'decimal_separator' => 'nullable|string|size:1',
            'thousands_separator' => 'nullable|string|size:1',
            'metadata' => 'nullable|array',
        ]);

        // Handle default language logic
        if (isset($validated['is_default']) && $validated['is_default']) {
            Language::where('is_default', true)->where('id', '!=', $language->id)
                ->update(['is_default' => false]);
        }

        // Handle fallback language logic
        if (isset($validated['is_fallback']) && $validated['is_fallback']) {
            Language::where('is_fallback', true)->where('id', '!=', $language->id)
                ->update(['is_fallback' => false]);
        }

        $language->update($validated);

        return $this->success([
            'language' => $language->fresh()->toApiArray(),
            'message' => 'Language updated successfully.'
        ]);
    }

    /**
     * Remove the specified language.
     */
    public function destroy(Language $language): JsonResponse
    {
        if ($language->isDefault()) {
            return $this->error('Cannot delete the default language.', 422);
        }

        if ($language->isFallback()) {
            return $this->error('Cannot delete the fallback language.', 422);
        }

        $language->delete();

        return $this->success([
            'message' => 'Language deleted successfully.'
        ]);
    }

    /**
     * Set a language as default.
     */
    public function setDefault(Language $language): JsonResponse
    {
        Language::where('is_default', true)->update(['is_default' => false]);
        $language->update(['is_default' => true, 'is_active' => true]);

        return $this->success([
            'language' => $language->fresh()->toApiArray(),
            'message' => 'Default language updated successfully.'
        ]);
    }

    /**
     * Set a language as fallback.
     */
    public function setFallback(Language $language): JsonResponse
    {
        Language::where('is_fallback', true)->update(['is_fallback' => false]);
        $language->update(['is_fallback' => true, 'is_active' => true]);

        return $this->success([
            'language' => $language->fresh()->toApiArray(),
            'message' => 'Fallback language updated successfully.'
        ]);
    }

    /**
     * Toggle language active status.
     */
    public function toggleActive(Language $language): JsonResponse
    {
        if ($language->isDefault() && $language->is_active) {
            return $this->error('Cannot deactivate the default language.', 422);
        }

        $language->update(['is_active' => !$language->is_active]);

        return $this->success([
            'language' => $language->fresh()->toApiArray(),
            'message' => $language->is_active 
                ? 'Language activated successfully.' 
                : 'Language deactivated successfully.'
        ]);
    }

    /**
     * Export language pack.
     */
    public function exportPack(Request $request, Language $language): JsonResponse
    {
        $format = $request->get('format', 'json');
        $includeMetadata = $request->boolean('include_metadata', true);
        $onlyCompleted = $request->boolean('only_completed', false);

        $query = $language->translations();

        if ($onlyCompleted) {
            $query->completed();
        }

        $translations = $query->get();

        $pack = [
            'language' => $language->toApiArray(),
            'translations' => [],
            'metadata' => [
                'exported_at' => now()->toISOString(),
                'total_strings' => $translations->count(),
                'completed_strings' => $translations->where('status', 'approved')->count(),
                'version' => '1.0.0',
            ]
        ];

        foreach ($translations as $translation) {
            $pack['translations'][$translation->group][$translation->key] = $translation->value;
        }

        $filename = "language_pack_{$language->code}_{now()->format('Y-m-d_H-i-s')}.{$format}";

        switch ($format) {
            case 'php':
                $content = "<?php\n\nreturn " . var_export($pack, true) . ';';
                break;
            case 'json':
            default:
                $content = json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
        }

        Storage::put("language_packs/{$filename}", $content);

        return $this->success([
            'filename' => $filename,
            'download_url' => Storage::url("language_packs/{$filename}"),
            'statistics' => $pack['metadata'],
            'message' => 'Language pack exported successfully.'
        ]);
    }

    /**
     * Import language pack.
     */
    public function importPack(Request $request, Language $language): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:json,php',
            'overwrite_existing' => 'boolean',
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getPathname());
        $overwriteExisting = $request->boolean('overwrite_existing', false);

        try {
            if ($file->getClientOriginalExtension() === 'php') {
                $pack = include $file->getPathname();
            } else {
                $pack = json_decode($content, true);
            }

            if (!isset($pack['translations']) || !is_array($pack['translations'])) {
                return $this->error('Invalid language pack format.', 422);
            }

            $imported = 0;
            $skipped = 0;
            $updated = 0;

            foreach ($pack['translations'] as $group => $translations) {
                foreach ($translations as $key => $value) {
                    $existing = $language->translations()
                        ->where('group', $group)
                        ->where('key', $key)
                        ->first();

                    if ($existing && !$overwriteExisting) {
                        $skipped++;
                        continue;
                    }

                    if ($existing) {
                        $existing->update([
                            'value' => $value,
                            'status' => 'approved',
                            'updated_at' => now(),
                        ]);
                        $updated++;
                    } else {
                        $language->translations()->create([
                            'group' => $group,
                            'key' => $key,
                            'value' => $value,
                            'status' => 'approved',
                        ]);
                        $imported++;
                    }
                }
            }

            // Update language statistics
            $language->updateTranslationStats(
                $language->translations()->count(),
                $language->translations()->completed()->count()
            );

            return $this->success([
                'statistics' => [
                    'imported' => $imported,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'total_processed' => $imported + $updated + $skipped,
                ],
                'message' => 'Language pack imported successfully.'
            ]);

        } catch (\Exception $e) {
            return $this->error('Failed to import language pack: ' . $e->getMessage(), 422);
        }
    }
}