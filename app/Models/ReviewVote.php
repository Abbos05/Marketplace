<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewVote extends Model
{
    public const VOTE_HELPFUL = 'helpful';

    public const VOTE_UNHELPFUL = 'unhelpful';

    protected $fillable = [
        'user_id',
        'review_id',
        'vote',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public static function syncReviewCounts(Review $review): void
    {
        $review->update([
            'likes_count' => static::query()
                ->where('review_id', $review->id)
                ->where('vote', self::VOTE_HELPFUL)
                ->count(),
            'dislikes_count' => static::query()
                ->where('review_id', $review->id)
                ->where('vote', self::VOTE_UNHELPFUL)
                ->count(),
        ]);
    }
}
