<?php

namespace Tests\Feature;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventDuplicateTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_event_can_be_duplicated_as_a_draft(): void
    {
        [$tenant, $user] = $this->createTenantUser();
        $event = $this->createEvent($tenant);

        $ticket = TicketType::create([
            'event_id' => $event->id,
            'name' => 'General Admission',
            'price' => 25,
            'quantity' => 50,
            'quantity_sold' => 12,
        ]);

        $attendee = Attendee::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);

        Registration::create([
            'event_id' => $event->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticket->id,
            'status' => 'confirmed',
        ]);

        $this->createEvent($tenant, [
            'title' => 'Laravel Live (Copy)',
            'slug' => 'laravel-live-copy',
        ]);

        $response = $this->actingAs($user)->post(route('events.duplicate', $event));

        $copy = Event::where('slug', 'laravel-live-copy-2')->firstOrFail();

        $response->assertRedirect(route('events.edit', $copy));
        $this->assertSame('Laravel Live (Copy)', $copy->title);
        $this->assertSame('draft', $copy->status);
        $this->assertSame($event->start_at->toDateTimeString(), $copy->start_at->toDateTimeString());
        $this->assertSame(0, $copy->registrations()->count());

        $this->assertDatabaseHas('ticket_types', [
            'event_id' => $copy->id,
            'name' => 'General Admission',
            'quantity' => 50,
            'quantity_sold' => 0,
        ]);
        $this->assertDatabaseHas('ticket_types', [
            'id' => $ticket->id,
            'quantity_sold' => 12,
        ]);
    }

    public function test_another_tenants_event_cannot_be_duplicated(): void
    {
        [$tenant] = $this->createTenantUser();
        $event = $this->createEvent($tenant);

        [, $otherUser] = $this->createTenantUser('other');

        $response = $this->actingAs($otherUser)->post(route('events.duplicate', $event));

        $response->assertForbidden();
        $this->assertDatabaseCount('events', 1);
    }

    private function createTenantUser(string $subdomain = 'demo'): array
    {
        $tenant = Tenant::create([
            'name' => ucfirst($subdomain).' Organization',
            'subdomain' => $subdomain,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        return [$tenant, $user];
    }

    private function createEvent(Tenant $tenant, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'tenant_id' => $tenant->id,
            'title' => 'Laravel Live',
            'description' => 'A hands-on Laravel event.',
            'start_at' => '2026-11-10 18:00:00',
            'end_at' => '2026-11-10 20:00:00',
            'timezone' => 'UTC',
            'venue_name' => 'The Hall',
            'venue_address' => '1 Main St',
            'status' => 'published',
        ], $overrides));
    }
}
