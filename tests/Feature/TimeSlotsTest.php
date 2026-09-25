<?php

namespace Tests\Feature;

use App\Models\BookingSearch;
use App\Services\VaticanAvailabilityChecker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TimeSlotsTest extends TestCase
{
    public function test_check_attaches_time_slots_to_each_available_ticket(): void
    {
        Http::fake([
            '*/api/search/resultPerTag*' => Http::response(['visits' => [
                ['id' => 1272314170, 'name' => 'Hidden Sections', 'availability' => 'AVAILABLE'],
                ['id' => 5, 'name' => 'Sold', 'availability' => 'SOLD_OUT'],
            ]]),
            '*/api/visit/timeavail*' => Http::response(['timetable' => [
                ['id' => '2026*1', 'time' => '11:30', 'availability' => 'SOLD_OUT'],
                ['id' => '2026*2', 'time' => '13:30', 'availability' => 'AVAILABLE'],
            ]]),
            '*/api/visit*' => Http::response(['ok' => true]),
        ]);

        $search = new BookingSearch(['visit_date' => '2026-09-30', 'visitor_count' => 2]);
        $result = (new VaticanAvailabilityChecker)->check($search);

        $this->assertCount(1, $result['items']);
        $this->assertSame('13:30', $result['items'][0]['slots'][1]['time']);
        $this->assertSame('AVAILABLE', $result['items'][0]['slots'][1]['availability']);
    }

    public function test_slots_are_null_when_the_slot_call_fails(): void
    {
        Http::fake([
            '*/api/search/resultPerTag*' => Http::response(['visits' => [['id' => 1, 'name' => 'A', 'availability' => 'AVAILABLE']]]),
            '*/api/visit*' => Http::response([], 500),
        ]);

        $result = (new VaticanAvailabilityChecker)->check(new BookingSearch(['visit_date' => '2026-09-30', 'visitor_count' => 1]));

        $this->assertNull($result['items'][0]['slots']);
    }
}
