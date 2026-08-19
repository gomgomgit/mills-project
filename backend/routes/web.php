<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Livewire — Admin / Supervisor / Mill Management)
|--------------------------------------------------------------------------
| Session-based auth. Screen routes are registered by impl-2-screen between
| the markers below, one per screen tech-spec's `route` field.
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));

// === ASDLC_ROUTES_START ===
// screen-001--login-web
Route::get('/login', \App\Livewire\Auth\LoginForm::class)->name('login');

// screen-003--ganti-password-web
Route::middleware(['auth', 'role:admin,supervisor,mill_management'])
    ->get('/settings/password', \App\Livewire\Settings\ChangePasswordForm::class)
    ->name('settings.password');

// screen-016--data-browser-weighbridge-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/weighbridge', \App\Livewire\Data\DataBrowserWeighbridge::class)
    ->name('data.weighbridge');

// screen-017--data-browser-grading-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/grading', \App\Livewire\Data\DataBrowserGrading::class)
    ->name('data.grading');

// screen-018--data-browser-cages-track-web
Route::middleware(['auth', 'role:supervisor,mill_management,admin'])
    ->get('/data/cages-track', \App\Livewire\Data\DataBrowserCagesTrack::class)
    ->name('data.cages-track');
// === ASDLC_ROUTES_END ===
