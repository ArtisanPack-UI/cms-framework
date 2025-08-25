<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers;

use ArtisanPackUI\MediaLibrary\Http\Requests\MediaTagRequest;
use ArtisanPackUI\MediaLibrary\Http\Resources\MediaTagResource;
use ArtisanPackUI\MediaLibrary\Models\MediaTag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class MediaTagController
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', MediaTag::class);

        return MediaTagResource::collection(MediaTag::all());
    }

    public function store(MediaTagRequest $request)
    {
        $this->authorize('create', MediaTag::class);

        return new MediaTagResource(MediaTag::create($request->validated()));
    }

    public function show(MediaTag $mediaTag)
    {
        $this->authorize('view', $mediaTag);

        return new MediaTagResource($mediaTag);
    }

    /**
     * Update the specified media tag in storage.
     *
     * @since 1.0.0
     *
     * @param  MediaTag  $mediaTag  The MediaTag instance resolved by route model binding.
     * @param  MediaTagRequest  $request  The validated form request.
     */
    public function update(MediaTagRequest $request, MediaTag $mediaTag): JsonResponse // Route Model Binding here
    {
        // REMOVE: $request->setResolvedMediaTag( $mediaTag ); // THIS CALL IS NO LONGER VALID

        $validatedData = $request->validated();
        $mediaTag->update($validatedData);

        return response()->json(['message' => 'Media tag updated successfully.', 'data' => $mediaTag]);
    }

    /**
     * Remove the specified media tag from storage.
     *
     * @since 1.0.0
     *
     * @param  MediaTag  $mediaTag  The MediaTag instance resolved by route model binding.
     * @param  MediaTagRequest  $request  The validated form request.
     */
    public function destroy(MediaTagRequest $request, MediaTag $mediaTag): JsonResponse // Route Model Binding here
    {
        // REMOVE: $request->setResolvedMediaTag( $mediaTag ); // THIS CALL IS NO LONGER VALID

        if ($mediaTag->delete()) {
            return response()->json(['message' => 'Media tag deleted successfully.'], 204);
        }

        return response()->json(['message' => 'Media tag deletion failed.'], 500);
    }
}
