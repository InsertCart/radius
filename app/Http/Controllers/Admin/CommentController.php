<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Support\PageCache;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommentController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): View|RedirectResponse
    {
        $status = $request->string('status')->toString() ?: 'pending';
        $comments = Comment::with(['post', 'user'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        if ($comments->currentPage() > $comments->lastPage()) {
            $request->session()->reflash();

            return redirect()->to($comments->url($comments->lastPage()));
        }

        return view('admin.comments.index', [
            'comments' => $comments,
            'status' => $status,
            'counts' => [
                'pending' => Comment::pending()->count(),
                'approved' => Comment::approved()->count(),
                'spam' => Comment::where('status', 'spam')->count(),
            ],
        ]);
    }

    public function update(Request $request, Comment $comment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,spam'],
        ]);

        $comment->update($validated);

        activity('comment.moderated', "Marked a comment as {$validated['status']}.", $comment);

        return back()->with('status', 'Comment updated.');
    }

    public function approve(Comment $comment): RedirectResponse
    {
        $comment->update(['status' => 'approved']);

        return back()->with('status', 'Comment approved.');
    }

    public function updateBody(Request $request, Comment $comment): RedirectResponse
    {
        $validated = $request->validateWithBag('comment'.$comment->id, [
            'body' => ['required', 'string', 'min:3', 'max:3000'],
        ]);

        $comment->update($validated);
        activity('comment.edited', 'Edited the text of a comment.', $comment);

        return back()->with('status', 'Comment saved.');
    }

    public function bulkDestroy(Request $request, PageCache $cache): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::PER_PAGE],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        $deleted = Comment::whereKey($validated['ids'])->delete();

        if ($deleted === 0) {
            return back()->with('warning', 'The selected comments have already been deleted.');
        }

        // A query-level delete skips the model events that retire cached blog pages.
        if ($cache->enabled()) {
            $cache->flush();
        }
        activity('comment.deleted', 'Bulk deleted selected comments.', null, ['ids' => $validated['ids']]);

        return back()->with('status', 'Selected comments deleted.');
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $comment->delete();

        activity('comment.deleted', 'Deleted a comment.');

        return back()->with('status', 'Comment deleted.');
    }
}
