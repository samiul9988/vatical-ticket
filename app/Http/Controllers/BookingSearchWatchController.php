<?php

namespace App\Http\Controllers;

use App\Console\Commands\CheckVaticanAvailability;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingSearchWatchController extends Controller
{
    public function index(): View
    {
        return view('watches', [
            'checkIntervalSeconds' => (int) Setting::get(
                'check_interval_seconds',
                (string) CheckVaticanAvailability::DEFAULT_INTERVAL_SECONDS
            ),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'check_interval_seconds' => ['required', 'integer', 'min:5', 'max:3600'],
        ]);

        Setting::set('check_interval_seconds', (string) $validated['check_interval_seconds']);

        return to_route('watches.index')->with('success', 'Check interval updated.');
    }
}
