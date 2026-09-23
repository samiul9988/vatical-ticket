<?php

namespace App\Console\Commands;

use App\Models\BookingSearch;
use App\Models\User;
use App\Notifications\TicketAvailabilityDetected;
use App\Services\VaticanAvailabilityChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vatican:check-availability')]
#[Description('Check active Vatican ticket watches at a safe interval')]
class CheckVaticanAvailability extends Command
{
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

        foreach ($searches as $search) {
            $wasAvailable = $search->status === 'manual_review' && ! empty($search->availability_items);

            try {
                $availability = $checker->check($search);
            } catch (\Throwable $exception) {
                $search->update([
                    'attempts' => $search->attempts + 1,
                    'last_checked_at' => now(),
                    'next_check_at' => now()->addMinute(),
                    'last_error' => $exception->getMessage(),
                ]);

                $this->warn($exception->getMessage());

                continue;
            }

            $search->update([
                'attempts' => $search->attempts + 1,
                'last_checked_at' => now(),
                'next_check_at' => now()->addMinute(),
                'last_error' => null,
            ]);

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
}
