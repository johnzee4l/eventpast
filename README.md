# EventPast

EventPast is a multi-tenant event management platform. Organizers create and publish events, participants register and receive a QR-code ticket by email, and staff check people in at the door. Every registration also comes with a personal referral link, so participants can bring friends and organizers can see which invites worked. Marketing and help content lives in a separate headless CMS (Strapi), so it can be edited without a code deploy.

**Stack:** Laravel 10 · Inertia.js · Vue 3 · Tailwind CSS · Vite · MySQL

> **Status: MVP in development.** The registration, check-in and referral logic works, but some screens are missing and two routing problems block the main participant flow. Read [What's built](#whats-built) and [Known issues](#known-issues) before trying the app end to end.

## Contents

- [What's built](#whats-built)
- [How it works](#how-it-works)
- [Getting started](#getting-started)
- [Routes](#routes)
- [Testing](#testing)
- [Project structure](#project-structure)
- [Known issues](#known-issues)
- [Documentation](#documentation)

## What's built

| Area | State | Details |
| --- | --- | --- |
| Organizations and roles | Built | Each user belongs to one organization (tenant) with the role `owner`, `admin` or `staff`. Users, events and attendees are scoped by `tenant_id`. |
| Authentication | Built | Laravel Breeze: login, password reset, email verification routes and a profile page. |
| Event management | Built | Dashboard with event list and totals. Create, edit, delete, and set status (draft, published, ended, cancelled). Each event has a schedule, timezone, venue or online link, and optional capacity. |
| Registration | Built | Guest registration (no account needed). Attendees are de-duplicated per organization by email. Events that are full put new registrations on a waitlist. Confirmation page with a QR code, plus a confirmation email. |
| QR check-in | API only | `POST /check-in/scan` takes a ticket token and returns `valid`, `duplicate`, `invalid`, `unauthorized`, `cancelled`, `waitlisted` or `payment_pending`. There is no scanner screen yet. |
| Referrals | Built | Every registration gets a 10-character referral code. Visits through `?ref=CODE` count as clicks and completed registrations as conversions (stored in `registrations.source` as `referral:CODE`). The confirmation page has share buttons (X/Twitter, Facebook, WhatsApp, email). Organizers get a per-event analytics page with top referrers and badges. |
| Content (Strapi) | Partly built | Blog and help center pages read from Strapi through a cached client, with webhook endpoints for cache invalidation. Landing pages, playbooks and featured events have backend support but no pages. Requires a running Strapi instance. |
| Plans | Partly built | Free, Pro, Business and Enterprise are defined on the `Tenant` model. Only the per-event participant limit is applied, as the default capacity. |
| Waitlist promotion | Partly built | `Registration::promoteFromWaitlist()` exists, but nothing calls it and no promotion email is sent. |
| Payments | Not built | Prices, orders and payments tables exist, but there is no Stripe integration. A ticket type with a price above zero still registers without taking payment. |

**Not built yet:** ticket type management screen, check-in scanner screen, scheduled reminders, analytics dashboard, CSV export, SMS and email campaigns (tables only), public event directory, add-to-calendar.

## How it works

### Multi-tenancy

EventPast uses one database. Tenant-owned tables carry a `tenant_id` (users, events, attendees); the rest reach their tenant through events. `EventPolicy` and `User::canManageEvent()` compare the user's `tenant_id` with the event's.

Public routes pass through `TenantMiddleware` (alias `tenant`). It reads the subdomain from the request host, for example `demo.eventpast.test` when `APP_DOMAIN=eventpast.test`, and returns 404 for an unknown or inactive organization. On a plain host such as `localhost` no subdomain is resolved and the request continues.

### Roles

| Role | Can do |
| --- | --- |
| `owner` | Everything an admin can, plus restoring and force-deleting events. |
| `admin` | Create, edit and delete events, and check attendees in. |
| `staff` | View their organization's events. |

### Plans

These limits are defined in `app/Models/Tenant.php`. Only the participant limit is used today; the event limit and ticket fees are not enforced yet.

| Plan | Active events | Participants per event | Ticket fee |
| --- | --- | --- | --- |
| `free` | 2 | 50 | 5% + $0.50 |
| `pro` | 20 | 500 | 2.5% |
| `business` | Unlimited | 5,000 | 1.5% |
| `enterprise` | Unlimited | Unlimited | None |

### Data model

`tenants`, `users`, `events`, `ticket_types`, `attendees`, `registrations`, `check_ins`, `referrals`, `orders`, `payments`, `campaigns`.

A registration links an attendee to an event and a ticket type. It has a 64-character random `qr_token`, at most one check-in and one referral. Registration statuses are `confirmed`, `waitlisted`, `cancelled` and `checked_in`.

### Check-in flow

The QR code on a ticket encodes only the registration's `qr_token`. When a signed-in owner or admin posts it to `/check-in/scan`, the first valid scan records who scanned it and when, and sets the registration to `checked_in`. Scanning the same code again returns `duplicate`.

## Getting started

### Prerequisites

- PHP 8.1 or newer and Composer 2
- Node.js 18 or newer and npm
- MySQL (SQLite also works for a quick start)
- Optional: [Mailpit](https://mailpit.axllent.org/) for local email, and a Strapi instance for the content pages

### Install

```bash
git clone https://github.com/johnmarkanthonystorres/eventpast.git
cd eventpast

composer install
cp .env.example .env
php artisan key:generate
```

Create an empty database, then set the `DB_*` values in `.env`. For a quick start with SQLite instead, set `DB_CONNECTION=sqlite` and point `DB_DATABASE` at the full path of an empty file.

```bash
php artisan migrate
php artisan db:seed --class=DemoSeeder
npm install
```

`php artisan db:seed` on its own creates nothing. The demo data comes only from `DemoSeeder`.

### Run

Start both servers, in two terminals:

```bash
php artisan serve    # http://localhost:8000
npm run dev          # Vite dev server
```

Sign in at `http://localhost:8000/login` with the demo account (a Pro-plan organization):

- Email: `admin@demo.com`
- Password: `password`

Use this account for local development only.

### Create your own organization

Signing up at `/register` creates a user with no organization, and the dashboard fails for that user. Create the organization and its owner in Tinker instead:

```php
// php artisan tinker
$tenant = App\Models\Tenant::create([
    'name' => 'Acme Events',
    'subdomain' => 'acme',
    'plan' => 'free',
]);

App\Models\User::create([
    'name' => 'Jane Owner',
    'email' => 'jane@example.com',
    'password' => 'choose-a-password',
    'tenant_id' => $tenant->id,
    'role' => 'owner',
]);
```

### Add a ticket type

There is no screen for ticket types yet, and registration needs at least one. After creating an event, add one in Tinker:

```php
App\Models\TicketType::create([
    'event_id' => 1,            // your event's id
    'name' => 'General Admission',
    'price' => 0,               // prices are stored but not charged yet
]);
```

Leave `quantity` unset for unlimited tickets. Events are created as drafts, and registration only works once an event's status is set to published (edit the event to change it).

### Environment variables

`.env.example` covers the standard Laravel settings. These are the ones that matter for EventPast:

| Variable | Purpose | Default |
| --- | --- | --- |
| `APP_URL` | Base URL used in emails and links | `http://localhost` |
| `APP_DOMAIN` | Base domain for subdomain tenant resolution | `eventpast.test` |
| `DB_*` | Database connection | MySQL on `127.0.0.1` |
| `MAIL_*` | SMTP settings for outgoing email | Mailpit (`mailpit:1025`) |
| `QUEUE_CONNECTION` | Confirmation emails are queued notifications. `sync` sends them immediately. | `sync` |
| `STRAPI_URL` | Strapi base URL | `http://localhost:1337` |
| `STRAPI_API_TOKEN` | Read-only Strapi API token | empty |
| `STRAPI_WEBHOOK_SECRET` | Must match the `X-Webhook-Secret` header Strapi sends | empty |
| `STRAPI_CACHE_DURATION` | Seconds to cache Strapi responses | `900` |

`APP_DOMAIN` and the `STRAPI_*` variables are not in `.env.example`. Add them to your `.env` when you need them.

### Email in development

The default `MAIL_HOST=mailpit` only resolves inside Docker or Sail. If you run Mailpit on your own machine, set `MAIL_HOST=127.0.0.1` and `MAIL_PORT=1025`. Port 1025 is the SMTP port; the Mailpit inbox is on port 8025 by default. To skip real delivery entirely, use `MAIL_MAILER=log`.

### Strapi (optional)

Strapi runs as its own Node service (not part of this repository) and only manages editorial content. Laravel remains the source of truth for events and attendees.

1. Run a Strapi 4 instance and create the content types `blog-post`, `help-article`, `playbook`, `landing-page` and `featured-event`.
2. Create a read-only API token and set it as `STRAPI_API_TOKEN`. Allow `find` and `findOne` for the Public role.
3. Add two webhooks that send an `X-Webhook-Secret` header matching `STRAPI_WEBHOOK_SECRET`:
   - `entry.publish` to `POST {APP_URL}/api/webhooks/strapi/content-published`
   - `entry.unpublish` to `POST {APP_URL}/api/webhooks/strapi/content-unpublished`
4. Visit `/content/blog` and `/content/help`.

Field lists and step-by-step setup are in [STRAPI_QUICKSTART.md](STRAPI_QUICKSTART.md) and [STRAPI_INTEGRATION.md](STRAPI_INTEGRATION.md). If Strapi was unreachable when a page was first requested, the empty result is cached for the cache duration. Run `php artisan cache:clear` after fixing the connection.

## Routes

Run `php artisan route:list` for the full list. The main ones:

| Access | Route | Purpose |
| --- | --- | --- |
| Public | `GET /events/{slug}` | Public event page and registration form (see [Known issues](#known-issues)) |
| Public | `POST /events/{slug}/register` | Submit a registration |
| Public | `GET /registrations/{id}/confirmation` | Confirmation page with QR code and referral link |
| Public | `GET /registrations/{id}/qrcode` | QR code as SVG |
| Public | `GET /content/blog`, `/content/blog/{slug}`, `/content/help`, `/content/pages/{slug}`, `/content/playbooks` | Strapi-backed content |
| Signed in | `GET /dashboard`, `/events`, `/events/create`, `/events/{id}`, `/events/{id}/edit` | Event management |
| Signed in | `GET /events/{id}/check-in`, `POST /check-in/scan` | Check-in |
| Signed in | `GET /events/{id}/referrals`, `/analytics/referrals` | Referral analytics |
| API | `POST /api/webhooks/strapi/content-published`, `/content-unpublished` | Strapi webhooks (secret header) |
| API | `POST /api/strapi/clear-cache` | Manual cache clear (Sanctum token) |

## Testing

```bash
php artisan test
```

The suite has 25 tests covering Breeze authentication and the profile page. Nothing covers events, registration, check-in or referrals yet.

The tests use `RefreshDatabase`, and `phpunit.xml` ships with its test-database lines commented out, so by default they run against, and wipe, the database in your `.env`. Before running them, uncomment these two lines in `phpunit.xml`:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

## Project structure

```
app/
  Http/Controllers/    Event, Registration, CheckIn, ReferralAnalytics, Content, StrapiWebhook, Auth/*
  Http/Middleware/     TenantMiddleware
  Models/              Tenant, User, Event, TicketType, Attendee, Registration,
                       CheckIn, Referral, Order, Payment, Campaign
  Notifications/       RegistrationConfirmed
  Policies/            EventPolicy
  Services/            StrapiService (cached Strapi client)
config/services.php    Strapi settings
database/
  migrations/          Schema
  seeders/             DemoSeeder
resources/js/
  Pages/               Inertia + Vue pages (Events, Registrations, Content, Auth, Profile, Dashboard)
  Layouts/             App, authenticated, guest and public layouts
routes/                web.php, api.php, auth.php
```

## Known issues

Found while verifying this README against the code (commit `7bc2bd2`, October 2026).

1. **The public event page is unreachable, and `/events/create` returns 404.** `routes/web.php` registers `GET /events/{event:slug}` (public) and `GET /events/{event}` (organizer) with the same URI, and Laravel keeps only one of them. Guests opening `/events/<slug>` are redirected to login, and `/events/create` is treated as an event ID. The public page needs its own URI; the referral link built in `Confirmation.vue` has to change with it. `POST /events/{slug}/register` still works.
2. **`npm run build` fails.** `BlogIndex.vue`, `BlogPost.vue` and `HelpCenter.vue` import `ziggy-js`, which is not in `package.json`. Running `npm install ziggy-js` fixes the build.
3. **New sign-ups have no organization.** `/register` creates a user without a `tenant_id`, and the dashboard then returns a server error. See [Create your own organization](#create-your-own-organization).
4. **Some Inertia pages are missing.** Controllers render `Events/CheckIn` (linked from the event page), `Analytics/Index`, `Analytics/ReferralLeaderboard`, `Content/LandingPage` and `Content/Playbooks`, but none of these Vue files exist.
5. **Strapi cache clearing is partial.** `Cache::forget` does not support wildcards, so blog list pages and `POST /api/strapi/clear-cache` clear nothing; those entries only expire after the cache duration. Single blog posts, help articles, landing pages, playbooks and featured events are cleared by the webhooks as intended.
6. **Waitlisted registrants get a "confirmed" email.** `RegistrationConfirmed` is sent regardless of status.

## Documentation

- [EventPast-Blueprint.md](EventPast-Blueprint.md): product plan, pricing, growth strategy and target architecture. Where it differs from the code (it names Laravel 12 and marks several features as built that are not), this README reflects the code.
- [STRAPI_QUICKSTART.md](STRAPI_QUICKSTART.md) and [STRAPI_INTEGRATION.md](STRAPI_INTEGRATION.md): CMS setup.
- [BUILD_STATUS.md](BUILD_STATUS.md), [REFERRAL_SYSTEM_COMPLETE.md](REFERRAL_SYSTEM_COMPLETE.md), [EVENT_EDIT_COMPLETE.md](EVENT_EDIT_COMPLETE.md), [SESSION_SUMMARY_2026-10-23.md](SESSION_SUMMARY_2026-10-23.md): dated progress notes that may be out of date.

## License

No license file has been added to this repository yet.
