<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: 'pending';

        return view('admin.comments.index', [
            'comments' => Comment::with(['post', 'user'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
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

    public function destroy(Comment $comment): RedirectResponse
    {
        $comment->delete();

        activity('comment.deleted', 'Deleted a comment.');

        return back()->with('status', 'Comment deleted.');
    }
}
