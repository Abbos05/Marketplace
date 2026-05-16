<?php

use App\Models\Review;
use App\Models\ReviewVote;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $pool = User::query()->pluck('id')->all();

        Review::query()->each(function (Review $review) use ($pool) {
            $this->backfillMissingVotes($review, $pool);
            ReviewVote::syncReviewCounts($review);
        });
    }

    public function down(): void
    {
        // counts are derived from review_votes; no rollback
    }

    /** @param list<int> $pool */
    private function backfillMissingVotes(Review $review, array $pool): void
    {
        $existingVoterIds = ReviewVote::query()
            ->where('review_id', $review->id)
            ->pluck('user_id')
            ->all();

        $candidates = collect($pool)
            ->reject(fn (int $id) => $id === (int) $review->user_id)
            ->reject(fn (int $id) => in_array($id, $existingVoterIds, true))
            ->shuffle()
            ->values();

        $helpfulNeeded = max(0, (int) $review->likes_count - ReviewVote::query()
            ->where('review_id', $review->id)
            ->where('vote', ReviewVote::VOTE_HELPFUL)
            ->count());

        $unhelpfulNeeded = max(0, (int) $review->dislikes_count - ReviewVote::query()
            ->where('review_id', $review->id)
            ->where('vote', ReviewVote::VOTE_UNHELPFUL)
            ->count());

        $offset = 0;

        foreach ($candidates->slice($offset, $helpfulNeeded) as $userId) {
            ReviewVote::query()->create([
                'user_id' => $userId,
                'review_id' => $review->id,
                'vote' => ReviewVote::VOTE_HELPFUL,
            ]);
        }
        $offset += $helpfulNeeded;

        foreach ($candidates->slice($offset, $unhelpfulNeeded) as $userId) {
            ReviewVote::query()->create([
                'user_id' => $userId,
                'review_id' => $review->id,
                'vote' => ReviewVote::VOTE_UNHELPFUL,
            ]);
        }
    }
};
