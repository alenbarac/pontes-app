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

    Route::post('/members/{member}/workshops',[MemberWorkshopController::class,'store'])
                ->name('members.workshops.store');

    Route::patch('/members/{member}/workshops/{workshop}', [MemberWorkshopController::class, 'update'])
                ->name('members.workshops.update');

     Route::delete('/members/{member}/workshops/{workshop}',[MemberWorkshopController::class, 'destroy'])
                ->name('members.workshops.destroy');

     Route::delete(
        '/members/{member}/workshops',[MemberWorkshopController::class, 'destroyAll'])
        ->name('members.workshops.destroyAll');
});

require __DIR__.'/auth.php';
