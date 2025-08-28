<?php

declare(strict_types=1);

use ArtisanPackUI\CMSFramework\Features\Internationalization\Controllers\LanguageController;
use ArtisanPackUI\CMSFramework\Features\Internationalization\Controllers\TranslationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internationalization API Routes
|--------------------------------------------------------------------------
|
| These routes handle all internationalization functionality including
| language management, translation management, and language pack operations.
|
*/

Route::prefix('admin/api/internationalization')->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Language Management Routes
    |--------------------------------------------------------------------------
    */
    
    Route::prefix('languages')->name('languages.')->group(function () {
        // Basic CRUD operations
        Route::get('/', [LanguageController::class, 'index'])->name('index');
        Route::post('/', [LanguageController::class, 'store'])->name('store');
        Route::get('/{language}', [LanguageController::class, 'show'])->name('show');
        Route::put('/{language}', [LanguageController::class, 'update'])->name('update');
        Route::delete('/{language}', [LanguageController::class, 'destroy'])->name('destroy');
        
        // Language status management
        Route::post('/{language}/set-default', [LanguageController::class, 'setDefault'])->name('set-default');
        Route::post('/{language}/set-fallback', [LanguageController::class, 'setFallback'])->name('set-fallback');
        Route::post('/{language}/toggle-active', [LanguageController::class, 'toggleActive'])->name('toggle-active');
        
        // Language pack operations
        Route::get('/{language}/export-pack', [LanguageController::class, 'exportPack'])->name('export-pack');
        Route::post('/{language}/import-pack', [LanguageController::class, 'importPack'])->name('import-pack');
    });
    
    /*
    |--------------------------------------------------------------------------
    | Translation Management Routes
    |--------------------------------------------------------------------------
    */
    
    Route::prefix('translations')->name('translations.')->group(function () {
        // Basic CRUD operations
        Route::get('/', [TranslationController::class, 'index'])->name('index');
        Route::post('/', [TranslationController::class, 'store'])->name('store');
        Route::get('/{translation}', [TranslationController::class, 'show'])->name('show');
        Route::put('/{translation}', [TranslationController::class, 'update'])->name('update');
        Route::delete('/{translation}', [TranslationController::class, 'destroy'])->name('destroy');
        
        // Translation workflow operations
        Route::post('/{translation}/approve', [TranslationController::class, 'approve'])->name('approve');
        Route::post('/{translation}/reject', [TranslationController::class, 'reject'])->name('reject');
        Route::post('/{translation}/mark-fuzzy', [TranslationController::class, 'markFuzzy'])->name('mark-fuzzy');
        Route::post('/{translation}/mark-outdated', [TranslationController::class, 'markOutdated'])->name('mark-outdated');
        
        // Bulk operations
        Route::post('/bulk-action', [TranslationController::class, 'bulkAction'])->name('bulk-action');
        
        // Translation utilities
        Route::post('/extract-keys', [TranslationController::class, 'extractKeys'])->name('extract-keys');
        Route::get('/missing', [TranslationController::class, 'getMissing'])->name('missing');
        Route::get('/statistics', [TranslationController::class, 'getStatistics'])->name('statistics');
    });
    
});

/*
|--------------------------------------------------------------------------
| Public Internationalization Routes (for frontend consumption)
|--------------------------------------------------------------------------
*/

Route::prefix('api/i18n')->name('i18n.')->group(function () {
    // Public language information
    Route::get('/languages', function () {
        $languages = \ArtisanPackUI\CMSFramework\Features\Internationalization\Models\Language::active()
            ->ordered()
            ->get(['id', 'name', 'code', 'locale', 'native_name', 'is_default', 'is_rtl']);
            
        return response()->json([
            'success' => true,
            'data' => [
                'languages' => $languages,
                'default' => $languages->where('is_default', true)->first(),
                'fallback' => \ArtisanPackUI\CMSFramework\Features\Internationalization\Models\Language::getFallback(),
            ]
        ]);
    })->name('languages');
    
    // Get translations for a specific language and group
    Route::get('/translations/{language}/{group?}', function ($languageCode, $group = null) {
        $language = \ArtisanPackUI\CMSFramework\Features\Internationalization\Models\Language::findByCode($languageCode);
        
        if (!$language || !$language->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found or inactive.'
            ], 404);
        }
        
        $query = $language->translations()->completed();
        
        if ($group) {
            $query->forGroup($group);
        }
        
        $translations = $query->get(['key', 'value', 'group', 'plural_form']);
        
        // Format translations for frontend consumption
        $formatted = [];
        foreach ($translations as $translation) {
            if ($group) {
                $formatted[$translation->key] = $translation->value;
            } else {
                $formatted[$translation->group][$translation->key] = $translation->value;
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'language' => [
                    'code' => $language->code,
                    'name' => $language->name,
                    'locale' => $language->locale,
                    'is_rtl' => $language->is_rtl,
                ],
                'translations' => $formatted,
                'metadata' => [
                    'total_strings' => $translations->count(),
                    'cached_at' => now()->toISOString(),
                ]
            ]
        ]);
    })->name('translations');
    
    // Get a single translation
    Route::get('/translate/{language}/{group}/{key}', function ($languageCode, $group, $key) {
        $language = \ArtisanPackUI\CMSFramework\Features\Internationalization\Models\Language::findByCode($languageCode);
        
        if (!$language || !$language->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found or inactive.'
            ], 404);
        }
        
        $translation = \ArtisanPackUI\CMSFramework\Features\Internationalization\Models\Translation::getTranslation(
            $key,
            $language->code,
            $group,
            [\ArtisanPackUI\CMSFramework\Features\Internationalization\Models\Language::getFallback()?->code ?? 'en']
        );
        
        return response()->json([
            'success' => true,
            'data' => [
                'key' => $key,
                'value' => $translation?->value ?? $key,
                'group' => $group,
                'language' => $language->code,
                'found' => $translation !== null,
            ]
        ]);
    })->name('translate');
    
    // Get language-specific formatting information
    Route::get('/formatting/{language}', function ($languageCode) {
        $language = \ArtisanPackUI\CMSFramework\Features\Internationalization\Models\Language::findByCode($languageCode);
        
        if (!$language || !$language->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found or inactive.'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'language' => $language->code,
                'locale' => $language->locale,
                'is_rtl' => $language->is_rtl,
                'formats' => [
                    'date' => $language->date_format,
                    'time' => $language->time_format,
                    'datetime' => $language->datetime_format,
                    'decimal_separator' => $language->decimal_separator ?? '.',
                    'thousands_separator' => $language->thousands_separator ?? ',',
                ],
                'examples' => [
                    'date' => $language->formatDate(now()),
                    'time' => $language->formatTime(now()),
                    'datetime' => $language->formatDateTime(now()),
                    'number' => $language->formatNumber(1234.56, 2),
                ]
            ]
        ]);
    })->name('formatting');
});

/*
|--------------------------------------------------------------------------
| Middleware Groups
|--------------------------------------------------------------------------
|
| Apply appropriate middleware to different route groups
|
*/

// Admin routes require authentication and admin permissions
Route::middleware(['auth:sanctum', 'can:manage_translations'])->group(function () {
    // Admin internationalization routes are already defined above
});

// Public API routes can be cached and rate limited
Route::middleware(['throttle:api', 'cache.headers:public;max_age=3600'])->group(function () {
    // Public i18n routes are already defined above
});