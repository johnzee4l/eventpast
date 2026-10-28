<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\ContentController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Public content pages (from Strapi)
Route::prefix('content')->group(function () {
    Route::get('/pages/{slug}', [ContentController::class, 'showLandingPage'])->name('content.pages.show');
    Route::get('/blog', [ContentController::class, 'blogIndex'])->name('content.blog.index');
    Route::get('/blog/{slug}', [ContentController::class, 'showBlogPost'])->name('content.blog.show');
    Route::get('/help', [ContentController::class, 'helpCenter'])->name('content.help');
    Route::get('/playbooks', [ContentController::class, 'playbooks'])->name('content.playbooks');
});

// Public event pages (with tenant middleware)
Route::middleware(['tenant'])->group(function () {
    Route::get('/events/{event:slug}', [RegistrationController::class, 'show'])->name('public.events.show');
    Route::post('/events/{event:slug}/register', [RegistrationController::class, 'store'])->name('public.events.register');
    Route::get('/registrations/{registration}/confirmation', [RegistrationController::class, 'confirmation'])->name('registrations.confirmation');
    Route::get('/registrations/{registration}/qrcode', [RegistrationController::class, 'qrcode'])->name('registrations.qrcode');
});

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [EventController::class, 'index'])->name('dashboard');
    
    // Events
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    Route::post('/events/{event}/duplicate', [EventController::class, 'duplicate'])->name('events.duplicate');
    
    // Check-in
    Route::get('/events/{event}/check-in', [CheckInController::class, 'index'])->name('events.checkin');
    Route::post('/check-in/scan', [CheckInController::class, 'scan'])->name('checkin.scan');
    
    // Analytics placeholder
    Route::get('/analytics', function () {
        return Inertia::render('Analytics/Index');
    })->name('analytics.index');
    
    // Referral Analytics
    Route::get('/analytics/referrals', [App\Http\Controllers\ReferralAnalyticsController::class, 'leaderboard'])->name('analytics.referrals');
    Route::get('/events/{event}/referrals', [App\Http\Controllers\ReferralAnalyticsController::class, 'show'])->name('events.referrals');
    
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
