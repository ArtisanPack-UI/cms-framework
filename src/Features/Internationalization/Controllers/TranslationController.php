<?php

declare(strict_types=1);

namespace ArtisanPackUI\CMSFramework\Features\Internationalization\Controllers;

use ArtisanPackUI\CMSFramework\Features\Internationalization\Models\Language;
use ArtisanPackUI\CMSFramework\Features\Internationalization\Models\Translation;
use ArtisanPackUI\CMSFramework\Features\Internationalization\Services\TranslationExtractor;
use ArtisanPackUI\CMSFramework\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TranslationController extends ApiController
{
    protected TranslationExtractor $extractor;

    public function __construct(TranslationExtractor $extractor)
    {
        $this->extractor = $extractor;
    }

    /**
     * Display a listing of translations.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'language_id' => 'nullable|exists:languages,id',
            'group' => 'nullable|string',
            'status' => 'nullable|in:pending,approved,rejected,fuzzy,outdated',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|in:key,value,group,status,updated_at',
            'sort_direction' => 'nullable|in:asc,desc',
        ]);

        $query = Translation::query()->with(['language', 'translator', 'reviewer']);

        // Filter by language
        if ($request->filled('language_id')) {
            $query->forLanguage($request->language_id);
        }

        // Filter by group
        if ($request->filled('group')) {
            $query->forGroup($request->group);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->withStatus($request->status);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('key', 'LIKE', "%{$search}%")
                  ->orWhere('value', 'LIKE', "%{$search}%")
                  ->orWhere('group', 'LIKE', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        $perPage = $request->get('per_page', 20);
        $translations = $query->paginate($perPage);

        // Get statistics
        $stats = [
            'total' => Translation::count(),
            'pending' => Translation::needsReview()->count(),
            'approved' => Translation::completed()->count(),
            'outdated' => Translation::outdated()->count(),
            'fuzzy' => Translation::fuzzy()->count(),
        ];

        return $this->success([
            'translations' => $translations->items(),
            'pagination' => [
                'current_page' => $translations->currentPage(),
                'last_page' => $translations->lastPage(),
                'per_page' => $translations->perPage(),
                'total' => $translations->total(),
            ],
            'statistics' => $stats,
        ]);
    }

    /**
     * Store a newly created translation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language_id' => 'required|exists:languages,id',
            'key' => 'required|string|max:255',
            'value' => 'required|string',
            'group' => 'required|string|max:100',
            'plural_form' => 'nullable|string',
            'context' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        // Check if translation already exists
        $existing = Translation::where([
            'language_id' => $validated['language_id'],
            'key' => $validated['key'],
            'group' => $validated['group'],
        ])->first();

        if ($existing) {
            return $this->error('Translation already exists for this key and language.', 422);
        }

        $validated['translator_id'] = Auth::id();
        $validated['status'] = 'pending';

        $translation = Translation::create($validated);
        $translation->load(['language', 'translator']);

        return $this->success([
            'translation' => $translation->toApiArray(),
            'message' => 'Translation created successfully.'
        ], 201);
    }

    /**
     * Display the specified translation.
     */
    public function show(Translation $translation): JsonResponse
    {
        $translation->load(['language', 'translator', 'reviewer']);

        return $this->success([
            'translation' => $translation->toApiArray(),
            'history' => $this->getTranslationHistory($translation),
        ]);
    }

    /**
     * Update the specified translation.
     */
    public function update(Request $request, Translation $translation): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'required|string',
            'plural_form' => 'nullable|string',
            'context' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $oldValue = $translation->value;
        $validated['translator_id'] = Auth::id();
        
        // If value changed, mark as pending review
        if ($oldValue !== $validated['value']) {
            $validated['status'] = 'pending';
            $validated['reviewed_at'] = null;
            $validated['reviewer_id'] = null;
        }

        $translation->update($validated);
        $translation->load(['language', 'translator', 'reviewer']);

        return $this->success([
            'translation' => $translation->toApiArray(),
            'message' => 'Translation updated successfully.'
        ]);
    }

    /**
     * Remove the specified translation.
     */
    public function destroy(Translation $translation): JsonResponse
    {
        $translation->delete();

        // Update language statistics
        $translation->updateLanguageStats();

        return $this->success([
            'message' => 'Translation deleted successfully.'
        ]);
    }

    /**
     * Approve a translation.
     */
    public function approve(Translation $translation): JsonResponse
    {
        if ($translation->isApproved()) {
            return $this->error('Translation is already approved.', 422);
        }

        $translation->approve(Auth::id());
        $translation->load(['language', 'translator', 'reviewer']);

        return $this->success([
            'translation' => $translation->toApiArray(),
            'message' => 'Translation approved successfully.'
        ]);
    }

    /**
     * Reject a translation.
     */
    public function reject(Request $request, Translation $translation): JsonResponse
    {
        $request->validate([
            'comment' => 'nullable|string|max:500',
        ]);

        if ($translation->status === 'rejected') {
            return $this->error('Translation is already rejected.', 422);
        }

        $translation->reject(Auth::id(), $request->comment);
        $translation->load(['language', 'translator', 'reviewer']);

        return $this->success([
            'translation' => $translation->toApiArray(),
            'message' => 'Translation rejected successfully.'
        ]);
    }

    /**
     * Mark translation as fuzzy.
     */
    public function markFuzzy(Translation $translation): JsonResponse
    {
        $translation->markFuzzy();
        $translation->load(['language', 'translator', 'reviewer']);

        return $this->success([
            'translation' => $translation->toApiArray(),
            'message' => 'Translation marked as fuzzy.'
        ]);
    }

    /**
     * Mark translation as outdated.
     */
    public function markOutdated(Translation $translation): JsonResponse
    {
        $translation->markOutdated();
        $translation->load(['language', 'translator', 'reviewer']);

        return $this->success([
            'translation' => $translation->toApiArray(),
            'message' => 'Translation marked as outdated.'
        ]);
    }

    /**
     * Bulk operations on translations.
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject,delete,mark_fuzzy,mark_outdated',
            'translation_ids' => 'required|array|min:1',
            'translation_ids.*' => 'exists:translations,id',
            'comment' => 'nullable|string|max:500',
        ]);

        $translations = Translation::whereIn('id', $validated['translation_ids'])->get();
        $processed = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($translations as $translation) {
                switch ($validated['action']) {
                    case 'approve':
                        if (!$translation->isApproved()) {
                            $translation->approve(Auth::id());
                            $processed++;
                        }
                        break;

                    case 'reject':
                        if ($translation->status !== 'rejected') {
                            $translation->reject(Auth::id(), $validated['comment'] ?? null);
                            $processed++;
                        }
                        break;

                    case 'delete':
                        $translation->delete();
                        $processed++;
                        break;

                    case 'mark_fuzzy':
                        $translation->markFuzzy();
                        $processed++;
                        break;

                    case 'mark_outdated':
                        $translation->markOutdated();
                        $processed++;
                        break;
                }
            }

            // Update language statistics for all affected languages
            $languageIds = $translations->pluck('language_id')->unique();
            foreach ($languageIds as $languageId) {
                $language = Language::find($languageId);
                if ($language) {
                    $language->updateTranslationStats(
                        $language->translations()->count(),
                        $language->translations()->completed()->count()
                    );
                }
            }

            DB::commit();

            return $this->success([
                'processed' => $processed,
                'total' => count($validated['translation_ids']),
                'errors' => $errors,
                'message' => "Bulk action '{$validated['action']}' completed successfully."
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->error('Bulk action failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Extract translation keys from source code.
     */
    public function extractKeys(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paths' => 'required|array|min:1',
            'paths.*' => 'string',
            'language_id' => 'required|exists:languages,id',
            'group' => 'required|string|max:100',
            'create_missing' => 'boolean',
            'mark_unused' => 'boolean',
        ]);

        try {
            $options = [
                'include_context' => true,
                'group_by_namespace' => true,
                'extract_comments' => true,
            ];

            $extractedKeys = $this->extractor->extract($validated['paths'], $options);
            $language = Language::find($validated['language_id']);

            $created = 0;
            $existing = 0;
            $marked_unused = 0;

            if ($validated['create_missing'] ?? false) {
                foreach ($extractedKeys as $keyData) {
                    $translation = Translation::firstOrCreate([
                        'language_id' => $language->id,
                        'key' => $keyData['key'],
                        'group' => $validated['group'],
                    ], [
                        'value' => $keyData['key'], // Use key as default value
                        'status' => 'pending',
                        'translator_id' => Auth::id(),
                        'context' => $keyData['context'] ?? null,
                    ]);

                    if ($translation->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $existing++;
                    }
                }
            }

            if ($validated['mark_unused'] ?? false) {
                $extractedKeyNames = $extractedKeys->pluck('key')->toArray();
                
                $unusedTranslations = Translation::where([
                    'language_id' => $language->id,
                    'group' => $validated['group'],
                ])->whereNotIn('key', $extractedKeyNames)->get();

                foreach ($unusedTranslations as $unused) {
                    $unused->setMetadata('marked_unused', true);
                    $unused->setMetadata('marked_unused_at', now()->toISOString());
                    $marked_unused++;
                }
            }

            return $this->success([
                'extracted_keys' => $extractedKeys->count(),
                'created_translations' => $created,
                'existing_translations' => $existing,
                'marked_unused' => $marked_unused,
                'statistics' => $this->extractor->generateStats($extractedKeys),
                'message' => 'Translation keys extracted successfully.'
            ]);

        } catch (\Exception $e) {
            return $this->error('Failed to extract translation keys: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get missing translations for a language.
     */
    public function getMissing(Request $request): JsonResponse
    {
        $request->validate([
            'language_id' => 'required|exists:languages,id',
            'reference_language_id' => 'nullable|exists:languages,id',
            'group' => 'nullable|string',
        ]);

        $language = Language::find($request->language_id);
        $referenceLanguage = $request->reference_language_id 
            ? Language::find($request->reference_language_id)
            : Language::getDefault();

        $query = Translation::forLanguage($referenceLanguage->id);

        if ($request->filled('group')) {
            $query->forGroup($request->group);
        }

        $referenceTranslations = $query->get();
        $missingKeys = [];

        foreach ($referenceTranslations as $refTranslation) {
            $exists = Translation::where([
                'language_id' => $language->id,
                'key' => $refTranslation->key,
                'group' => $refTranslation->group,
            ])->exists();

            if (!$exists) {
                $missingKeys[] = [
                    'key' => $refTranslation->key,
                    'group' => $refTranslation->group,
                    'reference_value' => $refTranslation->value,
                    'context' => $refTranslation->context,
                ];
            }
        }

        return $this->success([
            'language' => $language->toApiArray(),
            'reference_language' => $referenceLanguage->toApiArray(),
            'missing_translations' => $missingKeys,
            'count' => count($missingKeys),
            'message' => count($missingKeys) === 0 
                ? 'No missing translations found.'
                : count($missingKeys) . ' missing translations found.'
        ]);
    }

    /**
     * Get translation statistics.
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'language_id' => 'nullable|exists:languages,id',
            'group' => 'nullable|string',
        ]);

        $query = Translation::query();

        if ($request->filled('language_id')) {
            $query->forLanguage($request->language_id);
        }

        if ($request->filled('group')) {
            $query->forGroup($request->group);
        }

        $stats = [
            'total' => $query->count(),
            'by_status' => [
                'pending' => (clone $query)->needsReview()->count(),
                'approved' => (clone $query)->completed()->count(),
                'rejected' => (clone $query)->withStatus('rejected')->count(),
                'fuzzy' => (clone $query)->fuzzy()->count(),
                'outdated' => (clone $query)->outdated()->count(),
            ],
            'by_language' => Translation::select('language_id')
                ->selectRaw('count(*) as count')
                ->with('language:id,name,code')
                ->groupBy('language_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'language' => $item->language->toApiArray(),
                        'count' => $item->count,
                    ];
                }),
            'by_group' => Translation::select('group')
                ->selectRaw('count(*) as count')
                ->groupBy('group')
                ->orderBy('count', 'desc')
                ->get(),
        ];

        return $this->success($stats);
    }

    /**
     * Get translation history (placeholder for audit trail).
     */
    private function getTranslationHistory(Translation $translation): array
    {
        // This would integrate with an audit system if available
        // For now, return basic information
        return [
            'created_at' => $translation->created_at,
            'updated_at' => $translation->updated_at,
            'translator' => $translation->translator?->name,
            'reviewer' => $translation->reviewer?->name,
            'reviewed_at' => $translation->reviewed_at,
        ];
    }
}