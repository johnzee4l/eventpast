<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

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

    public function duplicate(Event $event)
    {
        $this->authorize('view', $event);
        $this->authorize('create', Event::class);

        $copy = DB::transaction(function () use ($event) {
            $copy = $event->replicate();
            $copy->title = $event->title.' (Copy)';
            $copy->slug = $this->uniqueEventSlug($event->tenant_id, $event->title);
            $copy->status = 'draft';
            $copy->save();

            foreach ($event->ticketTypes()->get() as $ticketType) {
                $ticketCopy = $ticketType->replicate(['quantity_sold']);
                $ticketCopy->event_id = $copy->id;
                $ticketCopy->quantity_sold = 0;
                $ticketCopy->save();
            }

            return $copy;
        });

        return redirect()
            ->route('events.edit', $copy)
            ->with('success', 'Event duplicated as a draft.');
    }

    private function uniqueEventSlug(int $tenantId, string $title): string
    {
        $base = (Str::slug($title) ?: 'event').'-copy';
        $slug = $base;
        $suffix = 2;

        while (Event::withTrashed()->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
