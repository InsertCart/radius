<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): View|RedirectResponse
    {
        $submissions = ContactSubmission::when($request->filled('q'), function ($q) use ($request) {
            $term = '%'.$request->string('q').'%';
            $q->where(fn ($sub) => $sub->where('name', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('subject', 'like', $term));
        })
            ->when($request->boolean('unread'), fn ($q) => $q->unread())
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        if ($submissions->currentPage() > $submissions->lastPage()) {
            $request->session()->reflash();

            return redirect()->to($submissions->url($submissions->lastPage()));
        }

        return view('admin.contact.index', [
            'submissions' => $submissions,
            'unreadCount' => ContactSubmission::unread()->count(),
            'filters' => $request->only(['q', 'unread']),
        ]);
    }

    public function show(ContactSubmission $submission): View
    {
        $submission->markRead();

        return view('admin.contact.show', ['submission' => $submission]);
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::PER_PAGE],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        $deleted = ContactSubmission::whereKey($validated['ids'])->delete();
        if ($deleted === 0) {
            return back()->with('warning', 'The selected messages have already been deleted.');
        }

        activity('contact.deleted', 'Deleted selected contact messages.', null, ['ids' => $validated['ids']]);

        return back()->with('status', trans_choice('Deleted :count message.|Deleted :count messages.', $deleted));
    }

    public function destroy(ContactSubmission $submission): RedirectResponse
    {
        $submission->delete();

        activity('contact.deleted', 'Deleted a contact message.');

        return redirect()->route('admin.contact.index')->with('status', 'Message deleted.');
    }
}
