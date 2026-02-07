<?php

use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberGroupController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\MemberWorkshopController;
use App\Http\Controllers\MemberImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceGenerationController;
use App\Http\Controllers\MemberInvoiceController;
use App\Http\Controllers\DocumentTemplateController;
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
    Route::post('/member-groups/{memberGroup}/bulk-reassign', [MemberGroupController::class, 'bulkReassign'])
        ->name('member-groups.bulk-reassign');
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

    // Member-specific invoice routes (for session invoices)
    Route::post('/members/{member}/invoices/session', [MemberInvoiceController::class, 'generateSessionInvoice'])
        ->name('members.invoices.session.generate');
    Route::post('/members/{member}/invoices/session/preview', [MemberInvoiceController::class, 'previewSessionInvoice'])
        ->name('members.invoices.session.preview');

    Route::resource('invoices', InvoiceController::class)->except(['destroy']);
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::patch('/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.updateStatus');
    Route::patch('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markAsPaid'])->name('invoices.markPaid');
    Route::post('/invoices/toggle-bulk-status', [InvoiceController::class, 'toggleBulkInvoiceStatus'])->name('invoices.toggleBulkInvoiceStatus');
    Route::get('/invoices/{invoice}/slip', [InvoiceController::class, 'slip'])
        ->name('invoices.slip');

    // Document Templates Routes
    Route::prefix('document-templates')->group(function () {
        Route::get('/ispricnice', [DocumentTemplateController::class, 'index'])
            ->defaults('type', 'ispricnica')
            ->name('document-templates.ispricnice.index');
        Route::get('/privole', [DocumentTemplateController::class, 'index'])
            ->defaults('type', 'privola')
            ->name('document-templates.privole.index');
        Route::get('/ispricnice/create', [DocumentTemplateController::class, 'create'])
            ->defaults('type', 'ispricnica')
            ->name('document-templates.ispricnice.create');
        Route::get('/privole/create', [DocumentTemplateController::class, 'create'])
            ->defaults('type', 'privola')
            ->name('document-templates.privole.create');
    });
    Route::post('/document-templates', [DocumentTemplateController::class, 'store'])
        ->name('document-templates.store');
    Route::get('/document-templates/{documentTemplate}', [DocumentTemplateController::class, 'show'])
        ->name('document-templates.show');
    Route::get('/document-templates/{documentTemplate}/edit', [DocumentTemplateController::class, 'edit'])
        ->name('document-templates.edit');
    Route::patch('/document-templates/{documentTemplate}', [DocumentTemplateController::class, 'update'])
        ->name('document-templates.update');
    Route::delete('/document-templates/{documentTemplate}', [DocumentTemplateController::class, 'destroy'])
        ->name('document-templates.destroy');
    Route::get('/document-templates/preview', [DocumentTemplateController::class, 'preview'])
        ->name('document-templates.preview');
    Route::get('/document-templates/{documentTemplate}/preview', [DocumentTemplateController::class, 'preview'])
        ->name('document-templates.preview.with-id');
    Route::post('/document-templates/{documentTemplate}/generate', [DocumentTemplateController::class, 'generate'])
        ->name('document-templates.generate');
    Route::post('/document-templates/{documentTemplate}/generate-bulk', [DocumentTemplateController::class, 'generateBulk'])
        ->name('document-templates.generate-bulk');
    Route::get('/document-templates/active/list', [DocumentTemplateController::class, 'getActiveTemplates'])
        ->name('document-templates.active');

});

require __DIR__.'/auth.php';
