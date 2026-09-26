<?php

namespace Tests\Feature;

use App\Events\PriorityTicketAvailable;
use App\Models\BookingSearch;
use App\Support\PriorityTickets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PriorityTicketNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_two_priority_tickets_match(): void
    {
        $this->assertTrue(PriorityTickets::matches('Vatican Museums - Admission Ticket'));
        $this->assertTrue(PriorityTickets::matches('  vatican museums - guided tours for individuals museums '));
        $this->assertFalse(PriorityTickets::matches('Vatican Museums - Guided Tours for Individuals Gardens and Museums'));
        $this->assertFalse(PriorityTickets::matches('Papal Palace - Admission Tickets'));
    }

    public function test_a_notification_is_broadcast_once_for_a_newly_available_priority_ticket(): void
    {
        Event::fake([PriorityTicketAvailable::class]);
        Http::fake([
            '*/api/search/resultPerTag*' => Http::response(['visits' => [
                ['id' => 1, 'name' => 'Vatican Museums - Admission Ticket', 'availability' => 'AVAILABLE'],
                ['id' => 2, 'name' => 'Papal Palace - Admission Tickets', 'availability' => 'AVAILABLE'],
            ]]),
            '*' => Http::response([], 500),
        ]);

        BookingSearch::create(['visit_date' => now()->addDay()->toDateString(), 'visitor_count' => 2, 'schedules' => ['10:00'], 'status' => 'watching']);

        $this->artisan('vatican:check-availability')->assertSuccessful();
        Event::assertDispatchedTimes(PriorityTicketAvailable::class, 1);
        Event::assertDispatched(PriorityTicketAvailable::class, fn (PriorityTicketAvailable $event): bool => $event->itemId === '1');

        BookingSearch::query()->update(['next_check_at' => now()->subMinute()]);
        $this->artisan('vatican:check-availability')->assertSuccessful();
        Event::assertDispatchedTimes(PriorityTicketAvailable::class, 1);
    }

    public function test_the_notification_fires_again_after_the_ticket_sells_out_and_reopens(): void
    {
        Event::fake([PriorityTicketAvailable::class]);
        $available = ['visits' => [['id' => 1, 'name' => 'Vatican Museums - Admission Ticket', 'availability' => 'AVAILABLE']]];
        $soldOut = ['visits' => [['id' => 1, 'name' => 'Vatican Museums - Admission Ticket', 'availability' => 'SOLD_OUT']]];

        Http::fake([
            '*/api/search/resultPerTag*' => Http::sequence()->push($available)->push($soldOut)->push($available),
            '*' => Http::response([], 500),
        ]);

        BookingSearch::create(['visit_date' => now()->addDay()->toDateString(), 'visitor_count' => 2, 'schedules' => ['10:00'], 'status' => 'watching']);

        foreach (range(1, 3) as $run) {
            BookingSearch::query()->update(['next_check_at' => now()->subMinute()]);
            $this->artisan('vatican:check-availability')->assertSuccessful();
        }

        Event::assertDispatchedTimes(PriorityTicketAvailable::class, 2);
    }
}
