<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ReviewVote;
use Illuminate\Http\Request;

class ReviewVoteController extends Controller
{
    public function store(Request $request, Review $review)
    {
        $request->validate([
            'vote' => 'required|in:helpful,unhelpful',
        ]);

        $user = $request->user();
        $vote = $request->input('vote');

        $existing = ReviewVote::query()
            ->where('user_id', $user->id)
            ->where('review_id', $review->id)
            ->first();

        if ($existing && $existing->vote === $vote) {
            $existing->delete();
        } else {
            ReviewVote::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'review_id' => $review->id,
                ],
                ['vote' => $vote]
            );
        }

        $review->refresh();
        ReviewVote::syncReviewCounts($review);
        $review->refresh();

        $userVote = ReviewVote::query()
            ->where('user_id', $user->id)
            ->where('review_id', $review->id)
            ->value('vote');

        $payload = [
            'review_id' => $review->id,
            'likes_count' => (int) $review->likes_count,
            'dislikes_count' => (int) $review->dislikes_count,
            'user_vote' => $userVote,
        ];

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($payload);
        }

        return back()->with('review_vote', $payload);
    }
}
