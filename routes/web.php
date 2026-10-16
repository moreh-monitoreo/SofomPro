<?php

use App\Http\Controllers\AuthSiteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoansController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequestCreditController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\RequestsController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\DocumentTypeController;
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
})->name('site.index');
Route::get('quienes-somos', [AuthSiteController::class, 'whoami'])->name('site.whoami');
Route::get('prestamo-personal', [AuthSiteController::class, 'personalloan'])->name('site.personalloan');
Route::get('prestamo-garantia', [AuthSiteController::class, 'loanguarantee'])->name('site.loanguarantee');
Route::get('contacto', [AuthSiteController::class, 'contact'])->name('site.contact');
Route::get('prestamo-personal/solicitar', [AuthSiteController::class, 'requestcredit'])->name('site.requestcredit');

Route::post('request-credit/customer', [RequestCreditController::class, 'createcustomer']);
Route::post('request-credit/customer/{customer}/address', [RequestCreditController::class, 'addaddress']);
Route::post('request-credit/customer/{customer}', [RequestCreditController::class, 'updatecustomer']);
Route::post('request-credit/customer/{customer}/employment', [RequestCreditController::class, 'createemployment']);
Route::post('request-credit/customer/{customer}/employment/update', [RequestCreditController::class, 'updateemployment']);
Route::post('request-credit/customer/{customer}/loan', [RequestCreditController::class, 'storeloan']);

Route::middleware('auth')->group(function () {
    Route::get('dashboard', [HomeController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.update-avatar');

    Route::post('/create-checkout-session', [StripeController::class, 'createCheckoutSession']);

    Route::get('customers/{customer}/loans/{loan}/payments', [CustomerController::class, 'payments'])->name('loans.show.payments');
});

Route::middleware(['auth', 'role:Administrator|Advisor'])->group(function () {
    Route::get('loans', [LoansController::class, 'index'])->name('loans.index');
    Route::get('loans/{id}', [LoansController::class, 'show'])->name('loans.show')->where('id', '[0-9]+');
    Route::put('loans/{id}', [LoansController::class, 'update'])->name('loans.update')->where('id', '[0-9]+');
    Route::get('loans/{id}/payments', [LoansController::class, 'payments'])->name('loans.payments.index')->where('id', '[0-9]+');
    Route::post('loans/{id}/payments', [LoansController::class, 'createpayments'])->name('loans.payments.store')->where('id', '[0-9]+');
    Route::post('loans/{id}/documents', [LoansController::class, 'documents'])->name('loans.documents.store')->where('id', '[0-9]+');
    Route::get('loans/{id}/documents/{documentId}', [LoansController::class, 'showDocument'])->name('loans.documents.show')->whereNumber(['id', 'documentId']);
    Route::delete('loans/{id}/documents/{documentId}', [LoansController::class, 'destroyDocument'])->name('loans.documents.destroy')->whereNumber(['id', 'documentId']);
    Route::get('loans/{id}/plan-funerario', [LoansController::class, 'planFunerario'])->name('loans.plan-funerario')->where('id', '[0-9]+');
    Route::post('loans/{id}/account/password', [LoansController::class, 'resetAccountPassword'])->name('loans.account.password')->where('id', '[0-9]+');
    Route::get('loans/history', [LoansController::class, 'history'])->name('loans.history');

    Route::get('customers/{id}', [CustomerController::class, 'show'])->name('customers.show');

    Route::get('requests', [RequestsController::class, 'index'])->name('requests.index');
    Route::post('requests/search', [RequestsController::class, 'search'])->name('requests.search');

    Route::get('statistics/payments', [StatisticsController::class, 'payments'])->name('statistics.payments');

    Route::get('document-types', [DocumentTypeController::class, 'index'])->name('document-types.index');
    Route::post('document-types', [DocumentTypeController::class, 'store'])->name('document-types.store');
    Route::put('document-types/{id}', [DocumentTypeController::class, 'update'])->name('document-types.update');
    Route::delete('document-types/{id}', [DocumentTypeController::class, 'destroy'])->name('document-types.destroy');
});

Route::get('/success', [StripeController::class, 'success']);
Route::get('/cancel', [StripeController::class, 'cancel']);

require __DIR__.'/auth.php';
