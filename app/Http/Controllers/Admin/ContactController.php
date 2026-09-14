<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.contact.index', [
            'submissions' => ContactSubmission::when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('subject', 'like', $term));
            })
                ->when($request->boolean('unread'), fn ($q) => $q->unread())
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'unreadCount' => ContactSubmission::unread()->count(),
            'filters' => $request->only(['q', 'unread']),
        ]);
    }

    public function show(ContactSubmission $submission): View
    {
        $submission->markRead();

        return view('admin.contact.show', ['submission' => $submission]);
    }

    public function destroy(ContactSubmission $submission): RedirectResponse
    {
        $submission->delete();

        activity('contact.deleted', 'Deleted a contact message.');

        return redirect()->route('admin.contact.index')->with('status', 'Message deleted.');
    }
}
