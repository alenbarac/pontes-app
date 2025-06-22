<?php

use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberGroupController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\MemberWorkshopController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('members', MemberController::class);
    Route::resource('member-groups', MemberGroupController::class);
    Route::resource('memberships', MembershipController::class);

    Route::patch(
        '/members/{member}/workshops/{workshop}/roll-out',
        [MemberWorkshopController::class, 'rollOut']
    )->name('members.workshops.rollout');

    Route::patch('/members/{member}/workshops/{workshop}', 
            [MemberWorkshopController::class, 'update'])
            ->name('members.workshops.update');
});

require __DIR__.'/auth.php';
