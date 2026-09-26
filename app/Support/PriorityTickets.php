<?php

namespace App\Support;

use Illuminate\Support\Str;

class PriorityTickets
{
    /**
     * Ticket names that trigger a realtime notification and the priority highlight.
     *
     * @var array<int, string>
     */
    public const TITLES = [
        'Vatican Museums - Admission Ticket',
        'Vatican Museums - Guided Tours for Individuals Museums',
    ];

    public static function matches(?string $title): bool
    {
        $normalised = Str::of((string) $title)->squish()->lower()->toString();

        foreach (self::TITLES as $priorityTitle) {
            if ($normalised === Str::lower($priorityTitle)) {
                return true;
            }
        }

        return false;
    }
}
