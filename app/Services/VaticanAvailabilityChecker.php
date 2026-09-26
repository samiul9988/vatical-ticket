<?php

namespace App\Services;

use App\Models\BookingSearch;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class VaticanAvailabilityChecker
{
    /**
     * @return array{available: bool, url: string|null, title: string|null, items: array<int, array{id: int|string, title: string, availability: string, slots: array<int, array{id: string, time: string, availability: string}>|null}>}
     */
    public function check(BookingSearch $search): array
    {
        $client = $this->client();

        $response = $client->get($this->resultEndpoint(), [
            'lang' => 'en',
            'visitorNum' => $search->visitor_count,
            'visitDate' => $search->visit_date->format('d/m/Y'),
            'area' => config('services.vatican.area', '1'),
            'who' => '',
            'page' => 0,
            'tag' => 'MV-Biglietti',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("Vatican availability API returned HTTP {$response->status()}.");
        }

        $items = collect($response->json('visits', []))
            ->filter(fn (array $visit): bool => in_array($visit['availability'] ?? null, ['AVAILABLE', 'LOW_AVAILABILITY'], true))
            ->map(fn (array $visit): array => [
                'id' => $visit['id'] ?? '',
                'title' => $visit['name'] ?? 'Available Vatican ticket',
                'availability' => $visit['availability'],
                'slots' => $this->loadTimeSlots($client, $search, (string) ($visit['id'] ?? '')),
            ])
            ->values()
            ->all();

        $url = $items !== [] ? $this->bookingUrl($search) : null;

        return [
            'available' => $items !== [],
            'url' => $url,
            'title' => $items[0]['title'] ?? null,
            'items' => $items,
        ];
    }

    public function bookingUrlForItem(BookingSearch $search, string $itemId): ?string
    {
        $storedItem = collect($search->availability_items ?? [])->firstWhere('id', $itemId);

        if (! is_array($storedItem)) {
            return null;
        }

        $availability = $this->check($search);
        $currentItem = collect($availability['items'])->firstWhere('title', $storedItem['title']);

        if (! is_array($currentItem)) {
            return null;
        }

        return $availability['url'];
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout(8)
            ->timeout(12)
            ->withOptions(['cookies' => new CookieJar]);
    }

    /**
     * Mirrors the official page: the ticket detail call precedes the time slot call.
     *
     * @return array<int, array{id: string, time: string, availability: string}>|null
     */
    private function loadTimeSlots(PendingRequest $client, BookingSearch $search, string $itemId): ?array
    {
        $query = [
            'lang' => 'en',
            'visitTypeId' => $itemId,
            'visitorNum' => $search->visitor_count,
            'visitDate' => $search->visit_date->format('d/m/Y'),
        ];

        try {
            $client->get($this->officialBaseUrl().'/api/visit', $query);
            $response = $client->get($this->officialBaseUrl().'/api/visit/timeavail', $query + ['visitLang' => 'ENG']);
        } catch (\Throwable) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        return collect($response->json('timetable', []))
            ->map(fn (array $slot): array => [
                'id' => (string) ($slot['id'] ?? ''),
                'time' => (string) ($slot['time'] ?? ''),
                'availability' => (string) ($slot['availability'] ?? 'UNKNOWN'),
            ])
            ->values()
            ->all();
    }

    private function resultEndpoint(): string
    {
        return $this->officialBaseUrl().'/api/search/resultPerTag';
    }

    private function bookingUrl(BookingSearch $search): string
    {
        $visitDate = $search->visit_date->copy()->startOfDay()->valueOf();

        return $this->officialBaseUrl().'/home/fromtag/'
            .$search->visitor_count.'/'.$visitDate.'/MV-Biglietti/'
            .config('services.vatican.area', '1');
    }

    private function officialBaseUrl(): string
    {
        $url = parse_url((string) config('services.vatican.ticket_url'));

        return ($url['scheme'] ?? 'https').'://'.($url['host'] ?? 'tickets.museivaticani.va');
    }
}
