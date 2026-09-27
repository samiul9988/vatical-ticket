<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingContactController extends Controller
{
    public function index(): View
    {
        return view('booking-contact', [
            'contact' => $this->contact(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'surname' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:100'],
            'sex' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'birthdate' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:190'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'language' => ['nullable', 'string', 'max:50'],
        ]);

        Setting::set('booking_contact', json_encode($validated));

        return to_route('booking-contact.index')->with('success', 'Booking contact details updated.');
    }

    /**
     * @return array<string, string|null>
     */
    public static function contact(): array
    {
        $decoded = json_decode(Setting::get('booking_contact', '{}'), true);

        return array_merge([
            'surname' => null,
            'name' => null,
            'sex' => null,
            'country' => null,
            'city' => null,
            'birthdate' => null,
            'email' => null,
            'mobile' => null,
            'language' => null,
        ], is_array($decoded) ? $decoded : []);
    }
}
