<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;
        
        $events = Event::where('tenant_id', $tenant->id)
            ->withCount('registrations')
            ->latest()
            ->paginate(10);
        
        $stats = [
            'total' => Event::where('tenant_id', $tenant->id)->count(),
            'published' => Event::where('tenant_id', $tenant->id)->where('status', 'published')->count(),
            'registrations' => $tenant->events()->withCount('registrations')->get()->sum('registrations_count'),
        ];
        
        return Inertia::render('Dashboard', [
            'events' => $events,
            'stats' => $stats,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Events/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'timezone' => 'required|string',
            'venue_name' => 'nullable|string',
            'venue_address' => 'nullable|string',
            'online_link' => 'nullable|url',
            'capacity' => 'nullable|integer|min:1',
        ]);
        
        $event = Event::create([
            'tenant_id' => $request->user()->tenant_id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'timezone' => $validated['timezone'],
            'venue_name' => $validated['venue_name'] ?? null,
            'venue_address' => $validated['venue_address'] ?? null,
            'online_link' => $validated['online_link'] ?? null,
            'capacity' => $validated['capacity'] ?? null,
            'status' => 'draft',
        ]);
        
        return redirect()->route('events.show', $event->id)
            ->with('success', 'Event created successfully');
    }

    public function show(Event $event): Response
    {
        $this->authorize('view', $event);
        
        $event->load(['ticketTypes', 'registrations.attendee']);
        
        return Inertia::render('Events/Show', [
            'event' => $event,
        ]);
    }

    public function edit(Event $event): Response
    {
        $this->authorize('update', $event);
        
        return Inertia::render('Events/Edit', [
            'event' => $event,
        ]);
    }

    public function update(Request $request, Event $event)
    {
        $this->authorize('update', $event);
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'timezone' => 'required|string',
            'venue_name' => 'nullable|string',
            'venue_address' => 'nullable|string',
            'online_link' => 'nullable|url',
            'capacity' => 'nullable|integer|min:1',
            'status' => 'required|in:draft,published,ended,cancelled',
        ]);
        
        $event->update($validated);
        
        return redirect()->route('events.show', $event->id)
            ->with('success', 'Event updated successfully');
    }

    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);
        
        $event->delete();
        
        return redirect()->route('events.index')
            ->with('success', 'Event deleted successfully');
    }

    public function exportRegistrations(Event $event): StreamedResponse
    {
        $this->authorize('view', $event);

        $filename = $event->slug.'-registrations-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($event) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Name',
                'Email',
                'Phone',
                'Ticket Type',
                'Ticket Price',
                'Status',
                'Source',
                'Registered At',
                'Checked In At',
            ]);

            $event->registrations()
                ->with(['attendee', 'ticketType', 'checkIn'])
                ->orderBy('created_at')
                ->chunk(200, function ($registrations) use ($handle) {
                    foreach ($registrations as $registration) {
                        fputcsv($handle, [
                            $registration->attendee->name,
                            $registration->attendee->email,
                            $registration->attendee->phone,
                            $registration->ticketType->name,
                            $registration->ticketType->price,
                            $registration->status,
                            $registration->source,
                            $registration->created_at?->toDateTimeString(),
                            $registration->checkIn?->scanned_at?->toDateTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
