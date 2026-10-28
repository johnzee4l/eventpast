<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Response;

class EventCalendarController extends Controller
{
    /**
     * Download a published event as an iCalendar file.
     */
    public function download(Event $event): Response
    {
        $timezoneHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'eventpast.local';
        $location = collect([$event->venue_name, $event->venue_address])
            ->filter()
            ->implode(', ');

        if ($location === '' && $event->online_link) {
            $location = 'Online: '.$event->online_link;
        }

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//EventPast//Event Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:event-'.$event->id.'@'.$timezoneHost,
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$event->start_at->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$event->end_at->copy()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($event->title),
            'DESCRIPTION:'.$this->escape(strip_tags($event->description ?? '')),
            'LOCATION:'.$this->escape($location),
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        $calendar = implode("\r\n", array_map([$this, 'foldLine'], $lines))."\r\n";

        return response($calendar, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$event->slug.'.ics"',
        ]);
    }

    /**
     * Escape text values according to RFC 5545.
     */
    private function escape(?string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", (string) $value);
        $value = str_replace('\\', '\\\\', $value);

        return str_replace(["\n", ';', ','], ['\n', '\;', '\,'], $value);
    }

    /**
     * Fold long content lines at 75 octets as required by RFC 5545.
     */
    private function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = mb_strcut($line, 0, 75, 'UTF-8');
        $remaining = mb_strcut($line, 75, null, 'UTF-8');

        while ($remaining !== '') {
            $folded .= "\r\n ".mb_strcut($remaining, 0, 74, 'UTF-8');
            $remaining = mb_strcut($remaining, 74, null, 'UTF-8');
        }

        return $folded;
    }
}
