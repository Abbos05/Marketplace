<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewModerationController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'pending');
        if (! in_array($status, ['pending', 'published', 'all'], true)) {
            $status = 'pending';
        }

        $query = Review::query()
            ->with([
                'product:id,title',
                'user:id,name',
                'variant:id,options',
            ])
            ->orderByDesc('created_at');

        if ($status === 'pending') {
            $query->where('is_moderated', false);
        } elseif ($status === 'published') {
            $query->where('is_moderated', true);
        }

        $search = trim((string) $request->query('search', ''));
        if (mb_strlen($search) > 200) {
            $search = mb_substr($search, 0, 200);
        }
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($search, $like) {
                $q->where('reviews.comment', 'like', $like);
                $q->orWhereHas('product', fn ($pq) => $pq->where('title', 'like', $like));
                $q->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', $like));
                if (ctype_digit($search)) {
                    $q->orWhere('reviews.id', (int) $search);
                }
            });
        }

        $reviews = $query->paginate(20)->withQueryString()->through(function (Review $r) {
            $comment = (string) ($r->comment ?? '');
            $snippet = mb_strlen($comment) > 160 ? mb_substr($comment, 0, 160).'…' : $comment;

            return [
                'id' => $r->id,
                'rating' => (int) $r->rating,
                'comment' => $comment,
                'comment_snippet' => $snippet,
                'is_moderated' => (bool) $r->is_moderated,
                'created_at' => $r->created_at?->format('d.m.Y H:i'),
                'product' => $r->product ? [
                    'id' => $r->product->id,
                    'title' => $r->product->title,
                ] : null,
                'user' => $r->user ? [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                ] : null,
                'variant_label' => $r->variant?->displayLabel(),
            ];
        });

        return Inertia::render('Admin/Reviews', [
            'reviews' => $reviews,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function approve(Review $review): RedirectResponse
    {
        $review->update(['is_moderated' => true]);

        return redirect()->route('admin.reviews.index', $this->reviewsIndexQuery())->with('success', 'Отзыв опубликован.');
    }

    public function reject(Review $review): RedirectResponse
    {
        $review->delete();

        return redirect()->route('admin.reviews.index', $this->reviewsIndexQuery())->with('success', 'Отзыв отклонён и скрыт.');
    }

    /**
     * @return array<string, string>
     */
    private function reviewsIndexQuery(): array
    {
        $q = ['status' => request('redirect_status', 'pending')];
        $s = trim((string) request('redirect_search', ''));
        if ($s !== '') {
            $q['search'] = mb_strlen($s) > 200 ? mb_substr($s, 0, 200) : $s;
        }

        return $q;
    }
}
