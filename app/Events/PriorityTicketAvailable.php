<?php

namespace App\Events;

use App\Models\BookingSearch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PriorityTicketAvailable implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $searchId,
        public string $itemId,
        public string $title,
        public string $visitDate,
        public int $visitorCount,
    ) {}

    public static function forItem(BookingSearch $search, array $item): self
    {
        return new self(
            $search->id,
            (string) $item['id'],
            (string) $item['title'],
            $search->visit_date->format('d M Y'),
            $search->visitor_count,
        );
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin-dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'priority-ticket.available';
    }

    /**
     * @return array{search_id: int, item_id: string, title: string, visit_date: string, visitor_count: int}
     */
    public function broadcastWith(): array
    {
        return [
            'search_id' => $this->searchId,
            'item_id' => $this->itemId,
            'title' => $this->title,
            'visit_date' => $this->visitDate,
            'visitor_count' => $this->visitorCount,
        ];
    }
}
