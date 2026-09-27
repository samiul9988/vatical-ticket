<?php

namespace App\Http\Controllers;

use App\Console\Commands\CheckVaticanAvailability;
use App\Models\BookingSearch;
use App\Models\NotificationSound;
use App\Models\Setting;
use App\Services\VaticanAvailabilityChecker;
use App\Support\PriorityTickets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $showAllTickets = Setting::get('show_all_tickets', 'true') === 'true';

        $availableSearches = BookingSearch::query()
            ->where('status', 'manual_review')
            ->latest('detected_at')
            ->get();

        if (! $showAllTickets) {
            $availableSearches = $availableSearches
                ->map(function (BookingSearch $search): BookingSearch {
                    $search->availability_items = collect($search->availability_items ?? [])
                        ->filter(fn (array $item): bool => PriorityTickets::matches($item['title']))
                        ->values()
                        ->all();

                    return $search;
                })
                ->filter(fn (BookingSearch $search): bool => ! empty($search->availability_items))
                ->values();
        }

        return view('dashboard', [
            'searches' => BookingSearch::query()->latest()->get(),
            'availableSearches' => $availableSearches,
            'soundUrl' => NotificationSound::activeUrl(),
            'watchingCount' => BookingSearch::query()->where('status', 'watching')->count(),
            'bookedCount' => BookingSearch::query()->where('status', 'booked')->count(),
            'checkIntervalSeconds' => (int) Setting::get(
                'check_interval_seconds',
                (string) CheckVaticanAvailability::DEFAULT_INTERVAL_SECONDS
            ),
            'showAllTickets' => $showAllTickets,
        ]);
    }

    public function updateTicketVisibility(Request $request): RedirectResponse
    {
        Setting::set('show_all_tickets', $request->boolean('show_all_tickets') ? 'true' : 'false');

        return to_route('dashboard')->with('success', 'Ticket visibility updated.');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'visit_date' => ['required', 'date', 'after_or_equal:today'],
            'visitor_count' => ['required', 'integer', 'min:1', 'max:30'],
            'schedules' => ['required', 'array', 'min:1'],
            'schedules.*' => ['date_format:H:i'],
        ]);

        BookingSearch::query()->create([
            'visit_date' => $validated['visit_date'],
            'visitor_count' => $validated['visitor_count'],
            'schedules' => array_values($validated['schedules']),
            'status' => 'watching',
            'next_check_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Availability watch created successfully.',
            ], 201);
        }

        return to_route('dashboard')->with('success', 'Availability watch created. Checkout remains manual after add-to-cart.');
    }

    public function stop(BookingSearch $search): RedirectResponse
    {
        $search->update(['status' => 'stopped']);

        return to_route('dashboard')->with('success', 'Availability watch stopped.');
    }

    public function destroy(BookingSearch $search): RedirectResponse|JsonResponse
    {
        $search->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'message' => 'Availability listing removed successfully.',
            ]);
        }

        return to_route('dashboard')->with('success', 'Availability listing removed.');
    }

    public function openAvailability(Request $request, BookingSearch $search, string $item, VaticanAvailabilityChecker $checker): RedirectResponse
    {
        try {
            $url = $checker->bookingUrlForItem($search, $item);
        } catch (\Throwable) {
            return to_route('dashboard')->with('error', 'Vatican is temporarily unavailable. Please try the Book button again shortly.');
        }

        if ($url === null) {
            return to_route('dashboard')->with('error', 'This ticket is no longer available. Refresh the dashboard and try again.');
        }

        $title = collect($search->availability_items ?? [])->firstWhere('id', $item)['title'] ?? '';

        $handoff = http_build_query([
            'book' => $item,
            'title' => $title,
            'full' => max(0, min(30, $request->integer('full', $search->visitor_count))),
            'reduced' => max(0, min(30, $request->integer('reduced'))),
            'lang' => $request->string('lang', 'English')->limit(20)->toString(),
            'time' => $request->string('time')->limit(10)->toString(),
        ]);

        return redirect()->away($url.'#'.$handoff);
    }
}
