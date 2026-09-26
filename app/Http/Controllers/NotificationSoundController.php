<?php

namespace App\Http\Controllers;

use App\Events\PriorityTicketAvailable;
use App\Models\NotificationSound;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationSoundController extends Controller
{
    public function index(): View
    {
        return view('sounds', [
            'sounds' => NotificationSound::query()->latest()->get(),
            'usingDefault' => ! NotificationSound::query()->where('is_active', true)->exists(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'sound' => ['required', 'file', 'mimes:mp3,wav,ogg,m4a,aac', 'max:2048'],
        ]);

        $sound = NotificationSound::create([
            'name' => $validated['name'],
            'path' => $request->file('sound')->store('sounds'),
        ]);

        $this->activate($sound);

        return to_route('sounds.index')->with('success', "\"{$sound->name}\" added and set as the notification sound.");
    }

    public function activate(NotificationSound $sound): RedirectResponse
    {
        NotificationSound::query()->update(['is_active' => false]);
        $sound->update(['is_active' => true]);

        return to_route('sounds.index')->with('success', "\"{$sound->name}\" is now the notification sound.");
    }

    public function useDefault(): RedirectResponse
    {
        NotificationSound::query()->update(['is_active' => false]);

        return to_route('sounds.index')->with('success', 'Built-in melody is now the notification sound.');
    }

    public function destroy(NotificationSound $sound): RedirectResponse
    {
        Storage::delete($sound->path);
        $sound->delete();

        return to_route('sounds.index')->with('success', 'Sound removed.');
    }

    public function file(NotificationSound $sound): StreamedResponse
    {
        abort_unless(Storage::exists($sound->path), 404);

        return Storage::response($sound->path);
    }

    public function test(): RedirectResponse
    {
        try {
            event(new PriorityTicketAvailable(0, 'test', 'Test priority ticket', now()->format('d M Y'), 2));
        } catch (\Throwable $exception) {
            return to_route('sounds.index')->with('error', 'Test notification failed: '.$exception->getMessage());
        }

        return to_route('sounds.index')->with('success', 'Test notification sent. Open the dashboard in another tab to see and hear it.');
    }
}
