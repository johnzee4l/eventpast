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

class RegistrationCsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_registrations_can_be_exported_as_csv(): void
    {
        [$tenant, $user] = $this->createTenantUser();
        $event = $this->createEvent($tenant);
        $ticket = TicketType::create([
            'event_id' => $event->id,
            'name' => 'General Admission',
            'price' => 25,
            'quantity' => 100,
        ]);
        $attendee = Attendee::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'phone' => '555-0100',
        ]);
        Registration::create([
            'event_id' => $event->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticket->id,
            'status' => 'confirmed',
            'source' => 'referral:ABC123',
        ]);

        $response = $this->actingAs($user)->get(route('events.registrations.export', $event));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString($event->slug.'-registrations-', $response->headers->get('content-disposition'));
        $this->assertStringEndsWith('.csv', $response->headers->get('content-disposition'));

        $rows = array_map('str_getcsv', explode("\n", trim($response->streamedContent())));

        $this->assertSame(['Name', 'Email', 'Phone', 'Ticket Type', 'Ticket Price', 'Status', 'Source', 'Registered At', 'Checked In At'], $rows[0]);
        $this->assertSame('Ada Lovelace', $rows[1][0]);
        $this->assertSame('ada@example.com', $rows[1][1]);
        $this->assertSame('555-0100', $rows[1][2]);
        $this->assertSame('General Admission', $rows[1][3]);
        $this->assertSame('25.00', $rows[1][4]);
        $this->assertSame('confirmed', $rows[1][5]);
        $this->assertSame('referral:ABC123', $rows[1][6]);
    }

    public function test_organizers_cannot_export_another_tenants_registrations(): void
    {
        [$tenant] = $this->createTenantUser();
        $event = $this->createEvent($tenant);

        [, $otherUser] = $this->createTenantUser('other');

        $response = $this->actingAs($otherUser)->get(route('events.registrations.export', $event));

        $response->assertForbidden();
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

    private function createEvent(Tenant $tenant): Event
    {
        return Event::create([
            'tenant_id' => $tenant->id,
            'title' => 'Laravel Live',
            'start_at' => '2026-11-10 18:00:00',
            'end_at' => '2026-11-10 20:00:00',
            'timezone' => 'UTC',
            'status' => 'published',
        ]);
    }
}
