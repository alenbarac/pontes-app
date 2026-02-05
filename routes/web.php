<?php

use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberGroupController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\MemberWorkshopController;
use App\Http\Controllers\MemberImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceGenerationController;
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

    Route::get('/members/import', [MemberImportController::class, 'index'])->name('members.import.index');
    Route::post('/members/import', [MemberImportController::class, 'store'])->name('members.import');
    Route::get('/members/import/template', [MemberImportController::class, 'template'])->name('members.import.template');

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

    // Invoice Generation Routes (must be before resource route to avoid conflict)
    Route::get('/invoices/generate', [InvoiceGenerationController::class, 'index'])
        ->name('invoices.generate.index');
    Route::post('/invoices/generate/preview', [InvoiceGenerationController::class, 'preview'])
        ->name('invoices.generate.preview');
    Route::post('/invoices/generate', [InvoiceGenerationController::class, 'generate'])
        ->name('invoices.generate');
    Route::delete('/invoices/generate/month', [InvoiceGenerationController::class, 'deleteMonth'])
        ->name('invoices.generate.deleteMonth');

    Route::resource('invoices', InvoiceController::class)->except(['destroy']);
    Route::patch('/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.updateStatus');
    Route::patch('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markAsPaid'])->name('invoices.markPaid');
    Route::post('/invoices/toggle-bulk-status', [InvoiceController::class, 'toggleBulkInvoiceStatus'])->name('invoices.toggleBulkInvoiceStatus');
    Route::get('/invoices/{invoice}/slip', [InvoiceController::class, 'slip'])
        ->name('invoices.slip');

});

require __DIR__.'/auth.php';
