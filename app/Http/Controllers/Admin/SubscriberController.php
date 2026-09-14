<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriberController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.subscribers.index', [
            'subscribers' => Subscriber::when($request->filled('q'),
                fn ($q) => $q->where('email', 'like', '%'.$request->string('q').'%'))
                ->latest()
                ->paginate(50)
                ->withQueryString(),
            'total' => Subscriber::subscribed()->count(),
            'filters' => $request->only('q'),
        ]);
    }

    /**
     * Streams a CSV rather than building it in memory, so exporting a large
     * list does not exhaust the memory limit on shared hosting.
     */
    public function export(): StreamedResponse
    {
        $filename = 'subscribers-'.now()->format('Y-m-d').'.csv';

        activity('subscribers.exported', 'Exported the newsletter list.');

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Email', 'Name', 'Status', 'Source', 'Subscribed at']);

            Subscriber::orderBy('id')->chunk(500, function ($subscribers) use ($handle) {
                foreach ($subscribers as $subscriber) {
                    fputcsv($handle, [
                        $subscriber->email,
                        $subscriber->name,
                        $subscriber->status,
                        $subscriber->source,
                        $subscriber->created_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function destroy(Subscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return back()->with('status', 'Subscriber removed.');
    }
}
