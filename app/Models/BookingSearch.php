<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'visit_date', 'visitor_count', 'schedules', 'status', 'attempts',
    'last_checked_at', 'next_check_at', 'booked_at', 'detected_at',
    'availability_url', 'availability_title', 'availability_items', 'last_error',
])]
class BookingSearch extends Model
{
    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'schedules' => 'array',
            'last_checked_at' => 'datetime',
            'next_check_at' => 'datetime',
            'booked_at' => 'datetime',
            'detected_at' => 'datetime',
            'availability_items' => 'array',
        ];
    }
}
