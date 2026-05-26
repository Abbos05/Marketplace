<?php

namespace App\Services;

use App\Models\Review;
use App\Models\ReviewImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class ReviewImageService
{
    public function maxPhotos(): int
    {
        return max(1, (int) config('marketplace.review_photos_max', 5));
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function storeMany(Review $review, array $files): void
    {
        $files = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile));
        if ($files === []) {
            return;
        }

        $existing = $review->images()->count();
        $allowed = $this->maxPhotos() - $existing;
        if ($allowed <= 0) {
            return;
        }

        $files = array_slice($files, 0, $allowed);
        $userId = (int) $review->user_id;
        $folder = public_path('img/reviews/'.$userId);
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        $sortOrder = $existing;
        foreach ($files as $file) {
            $this->validateFile($file);
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $filename = time().'_'.$sortOrder.'_'.Str::random(8).'.'.$ext;
            $file->move($folder, $filename);
            $webPath = '/img/reviews/'.$userId.'/'.$filename;

            ReviewImage::create([
                'review_id' => $review->id,
                'path' => $webPath,
                'sort_order' => $sortOrder,
            ]);

            $sortOrder++;
        }
    }

    public function deleteForReview(Review $review): void
    {
        $review->loadMissing('images');
        foreach ($review->images as $image) {
            $this->deleteFileFromDisk($image->path);
        }
        $review->images()->delete();
    }

    /**
     * @param  array<int, UploadedFile>|null  $newFiles
     * @param  array<int, int>|null  $keepImageIds
     */
    public function sync(Review $review, ?array $newFiles = null, ?array $keepImageIds = null): void
    {
        $review->loadMissing('images');
        $keepImageIds = array_values(array_unique(array_map('intval', $keepImageIds ?? [])));

        foreach ($review->images as $image) {
            if (! in_array((int) $image->id, $keepImageIds, true)) {
                $this->deleteFileFromDisk($image->path);
                $image->delete();
            }
        }

        $review->load('images');
        $remaining = $review->images->count();
        $newFiles = array_values(array_filter($newFiles ?? [], fn ($f) => $f instanceof UploadedFile));
        $slots = $this->maxPhotos() - $remaining;
        if ($slots > 0 && $newFiles !== []) {
            $this->storeMany($review, array_slice($newFiles, 0, $slots));
        }

        $this->reindexSortOrder($review);
    }

    /**
     * @return array<int, array{id: int, url: string}>
     */
    public function mapImagesForFrontend(Collection $images): array
    {
        return $images
            ->sortBy('sort_order')
            ->values()
            ->map(fn (ReviewImage $img) => [
                'id' => (int) $img->id,
                'url' => $img->publicUrl(),
            ])
            ->all();
    }

    protected function reindexSortOrder(Review $review): void
    {
        $review->load('images');
        foreach ($review->images->sortBy('sort_order')->values() as $index => $image) {
            if ((int) $image->sort_order !== $index) {
                $image->update(['sort_order' => $index]);
            }
        }
    }

    protected function validateFile(UploadedFile $file): void
    {
        $validator = Validator::make(
            ['photo' => $file],
            ['photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120']
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    protected function deleteFileFromDisk(string $path): void
    {
        $relative = ltrim($path, '/');
        if ($relative === '') {
            return;
        }

        $full = public_path($relative);
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
