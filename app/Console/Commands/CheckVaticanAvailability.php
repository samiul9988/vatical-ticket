<?php

namespace App\Console\Commands;

use App\Events\PriorityTicketAvailable;
use App\Models\BookingSearch;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\TicketAvailabilityDetected;
use App\Services\VaticanAvailabilityChecker;
use App\Support\PriorityTickets;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('vatican:check-availability')]
#[Description('Check active Vatican ticket watches at a safe interval')]
class CheckVaticanAvailability extends Command
{
    public const DEFAULT_INTERVAL_SECONDS = 60;

    /**
     * Execute the console command.
     */
    public function handle(VaticanAvailabilityChecker $checker): int
    {
        $searches = BookingSearch::query()->whereIn('status', ['watching', 'manual_review'])->where(function ($query): void {
            $query->whereNull('next_check_at')->orWhere('next_check_at', '<=', now());
        })->get();

        if ($searches->isEmpty()) {
            return self::SUCCESS;
        }

        $intervalSeconds = (int) Setting::get('check_interval_seconds', (string) self::DEFAULT_INTERVAL_SECONDS);

        foreach ($searches as $search) {
            $wasAvailable = $search->status === 'manual_review' && ! empty($search->availability_items);

            try {
                $availability = $checker->check($search);
            } catch (\Throwable $exception) {
                $search->update([
                    'attempts' => $search->attempts + 1,
                    'last_checked_at' => now(),
                    'next_check_at' => now()->addSeconds($intervalSeconds),
                    'last_error' => $exception->getMessage(),
                ]);

                $this->warn($exception->getMessage());

                continue;
            }

            $search->update([
                'attempts' => $search->attempts + 1,
                'last_checked_at' => now(),
                'next_check_at' => now()->addSeconds($intervalSeconds),
                'last_error' => null,
            ]);

            $this->broadcastPriorityTickets($search, $availability['items']);

            if ($availability['available']) {
                $search->update([
                    'status' => 'manual_review',
                    'detected_at' => now(),
                    'availability_url' => $availability['url'] ?: config('services.vatican.ticket_url'),
                    'availability_title' => $availability['title'],
                    'availability_items' => $availability['items'],
                ]);

                if (! $wasAvailable) {
                    User::query()->each(function (User $user) use ($search): void {
                        $user->notify(new TicketAvailabilityDetected($search));
                    });
                    $this->info("Review signal detected for watch #{$search->id}.");
                }
            } elseif ($wasAvailable) {
                $search->update([
                    'status' => 'watching',
                    'detected_at' => null,
                    'availability_url' => null,
                    'availability_title' => null,
                    'availability_items' => null,
                ]);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Notify once per continuous availability window. The marker is only stored after a successful broadcast,
     * so a failed Pusher call is retried on the next check, and it is cleared when the ticket sells out again.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function broadcastPriorityTickets(BookingSearch $search, array $items): void
    {
        $availablePriorityIds = [];

        foreach ($items as $item) {
            if (! PriorityTickets::matches($item['title'])) {
                continue;
            }

            $availablePriorityIds[] = (string) $item['id'];

            if (Cache::has($this->notifiedKey($search, $item['id']))) {
                continue;
            }

            try {
                event(PriorityTicketAvailable::forItem($search, $item));
                Cache::put($this->notifiedKey($search, $item['id']), true, now()->addDay());
                $this->info("Realtime notification sent for {$item['title']}.");
            } catch (\Throwable $exception) {
                $this->warn("Realtime notification failed, will retry: {$exception->getMessage()}");
            }
        }

        foreach (collect($search->availability_items ?? [])->pluck('id')->map(fn ($id): string => (string) $id) as $previousId) {
            if (! in_array($previousId, $availablePriorityIds, true)) {
                Cache::forget($this->notifiedKey($search, $previousId));
            }
        }
    }

    private function notifiedKey(BookingSearch $search, int|string $itemId): string
    {
        return "priority-notified.{$search->id}.{$itemId}";
    }
}
