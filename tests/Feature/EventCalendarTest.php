<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_calendar_can_be_downloaded(): void
    {
        $tenant = Tenant::create([
            'name' => 'Demo Organization',
            'subdomain' => 'demo',
        ]);

        $event = Event::create([
            'tenant_id' => $tenant->id,
            'title' => 'Laravel Meetup; Live',
            'description' => "First line\nSecond, line",
            'start_at' => '2026-11-10 18:00:00',
            'end_at' => '2026-11-10 20:00:00',
            'timezone' => 'UTC',
            'venue_name' => 'The Hall',
            'venue_address' => '1 Main St',
            'status' => 'published',
        ]);

        $response = $this->get(route('public.events.calendar', $event->slug));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="laravel-meetup-live.ics"');

        $calendar = $response->getContent();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $calendar);
        $this->assertStringContainsString('SUMMARY:Laravel Meetup\; Live', $calendar);
        $this->assertStringContainsString('DESCRIPTION:First line\nSecond\, line', $calendar);
        $this->assertStringContainsString('LOCATION:The Hall\, 1 Main St', $calendar);
        $this->assertStringContainsString('DTSTART:20261110T180000Z', $calendar);
        $this->assertStringContainsString('DTEND:20261110T200000Z', $calendar);
        $this->assertStringContainsString('END:VCALENDAR', $calendar);
    }
}
