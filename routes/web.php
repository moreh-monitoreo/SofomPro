<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\LoanProductController;
use App\Http\Controllers\Admin\PaymentCorrectionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\PostalCodeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\OverdueController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\AuthSiteController;
use App\Http\Controllers\ContactRequestController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoansController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequestCreditController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\RequestsController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\DocumentTypeController;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('site.index');
Route::get('quienes-somos', [AuthSiteController::class, 'whoami'])->name('site.whoami');
Route::get('prestamo-personal', [AuthSiteController::class, 'personalloan'])->name('site.personalloan');
Route::get('prestamo-garantia', [AuthSiteController::class, 'loanguarantee'])->name('site.loanguarantee');
Route::get('contacto', [AuthSiteController::class, 'contact'])->name('site.contact');
Route::post('contacto', [ContactRequestController::class, 'store'])->middleware('throttle:5,1')->name('site.contact.store');
Route::get('aviso-de-privacidad', [AuthSiteController::class, 'privacy'])->name('site.privacy');
Route::get('prestamo-personal/solicitar', [AuthSiteController::class, 'requestcredit'])->name('site.requestcredit');

Route::get('postal-codes/{code}', [PostalCodeController::class, 'show'])->middleware('throttle:60,1')->name('postal-codes.show')->where('code', '[0-9]{5}');

Route::prefix('request-credit')->middleware('throttle:60,1')->group(function () {
    Route::post('customer', [RequestCreditController::class, 'createcustomer']);

    Route::middleware('request.owner')->group(function () {
        Route::post('customer/{customer}/address', [RequestCreditController::class, 'addaddress']);
        Route::post('customer/{customer}', [RequestCreditController::class, 'updatecustomer']);
        Route::post('customer/{customer}/employment', [RequestCreditController::class, 'createemployment']);
        Route::post('customer/{customer}/employment/update', [RequestCreditController::class, 'updateemployment']);
        Route::post('customer/{customer}/loan', [RequestCreditController::class, 'storeloan']);
        Route::post('customer/{customer}/references', [RequestCreditController::class, 'references']);
        Route::post('customer/{customer}/documents', [RequestCreditController::class, 'documents']);
    });
});

Route::middleware(['auth', 'two-factor'])->group(function () {
    Route::get('dashboard', [HomeController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.update-avatar');

    Route::post('/create-checkout-session', [StripeController::class, 'createCheckoutSession']);

    Route::get('receipts/{loan}/{detail}', [ReceiptController::class, 'download'])->name('loans.payments.receipt')->whereNumber(['loan', 'detail']);
    Route::get('customers/{customer}/loans/{loan}/payments', [CustomerController::class, 'payments'])->name('loans.show.payments');
});

Route::middleware(['auth', 'two-factor', 'role:'.implode('|', User::STAFF_ROLES)])->group(function () {
    Route::get('loans', [LoansController::class, 'index'])->name('loans.index');
    Route::get('loans/{id}', [LoansController::class, 'show'])->name('loans.show')->where('id', '[0-9]+');
    Route::put('loans/{id}', [LoansController::class, 'update'])->name('loans.update')->where('id', '[0-9]+');
    Route::get('loans/{id}/payments', [LoansController::class, 'payments'])->name('loans.payments.index')->where('id', '[0-9]+');
    Route::post('loans/{id}/payments', [LoansController::class, 'createpayments'])->name('loans.payments.store')->where('id', '[0-9]+');
    Route::post('loans/{id}/documents', [LoansController::class, 'documents'])->name('loans.documents.store')->where('id', '[0-9]+');
    Route::get('loans/{id}/documents/{documentId}', [LoansController::class, 'showDocument'])->name('loans.documents.show')->whereNumber(['id', 'documentId']);
    Route::delete('loans/{id}/documents/{documentId}', [LoansController::class, 'destroyDocument'])->name('loans.documents.destroy')->whereNumber(['id', 'documentId']);
    Route::get('loans/{id}/plan-funerario', [LoansController::class, 'planFunerario'])->name('loans.plan-funerario')->where('id', '[0-9]+');
    Route::post('loans/{id}/account/invitation', [LoansController::class, 'inviteAccount'])->name('loans.account.invitation')->where('id', '[0-9]+');
    Route::get('loans/history', [LoansController::class, 'history'])->name('loans.history');
    Route::post('loans/{id}/preview', [PortfolioController::class, 'preview'])->name('loans.preview')->whereNumber('id');
    Route::post('loans/{id}/validation', [PortfolioController::class, 'startValidation'])->name('loans.validation')->whereNumber('id');
    Route::post('loans/{id}/validate', [PortfolioController::class, 'validateRequest'])->middleware('role:'.implode('|', User::VALIDATOR_ROLES))->name('loans.validate')->whereNumber('id');
    Route::post('loans/{id}/disburse', [PortfolioController::class, 'disburse'])->middleware('role:'.implode('|', User::APPROVER_ROLES))->name('loans.disburse')->whereNumber('id');
    Route::post('loans/{id}/payments/receive', [PortfolioController::class, 'receivePayment'])->name('loans.payments.receive')->whereNumber('id');
    Route::get('loans/{id}/payoff', [PortfolioController::class, 'payoff'])->name('loans.payoff')->whereNumber('id');
    Route::post('loans/{id}/payments/{detail}/register', [PortfolioController::class, 'registerPayment'])->name('loans.payments.register')->whereNumber(['id', 'detail']);

    Route::get('pipeline', [PipelineController::class, 'index'])->name('pipeline.index');
    Route::get('portfolio/overdue', [OverdueController::class, 'index'])->name('portfolio.overdue');
    Route::get('portfolio/overdue/export', [OverdueController::class, 'export'])->name('portfolio.overdue.export');

    Route::get('customers/{id}', [CustomerController::class, 'show'])->name('customers.show');

    Route::get('requests', [RequestsController::class, 'index'])->name('requests.index');
    Route::post('requests/search', [RequestsController::class, 'search'])->name('requests.search');

    Route::get('statistics/payments', [StatisticsController::class, 'payments'])->name('statistics.payments');
    Route::get('reports', [ReportController::class, 'index'])->middleware('role:'.implode('|', User::APPROVER_ROLES))->name('reports.index');
    Route::get('reports/aging/export', [ReportController::class, 'exportAging'])->middleware('role:'.implode('|', User::APPROVER_ROLES))->name('reports.aging.export');

    Route::get('document-types', [DocumentTypeController::class, 'index'])->name('document-types.index');
    Route::post('document-types', [DocumentTypeController::class, 'store'])->name('document-types.store');
    Route::put('document-types/{id}', [DocumentTypeController::class, 'update'])->name('document-types.update');
    Route::delete('document-types/{id}', [DocumentTypeController::class, 'destroy'])->name('document-types.destroy');
});

Route::middleware(['auth', 'two-factor', 'role:Administrator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('loan-products', [LoanProductController::class, 'index'])->name('loan-products.index');
    Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
    Route::put('loan-products/{type}', [LoanProductController::class, 'update'])->name('loan-products.update');
    Route::post('payments/{detail}/revert', [PaymentCorrectionController::class, 'revert'])->name('payments.revert')->whereNumber('detail');
    Route::post('movements/{movement}/revert', [PaymentCorrectionController::class, 'revertMovement'])->name('movements.revert')->whereNumber('movement');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update')->whereNumber('user');
    Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate')->whereNumber('user');
    Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate')->whereNumber('user');
    Route::post('users/{user}/two-factor/reset', [UserController::class, 'resetTwoFactor'])->name('users.two-factor.reset')->whereNumber('user');
    Route::post('users/{user}/invitation', [UserController::class, 'invite'])->name('users.invite')->whereNumber('user');
});

Route::middleware('auth')->prefix('two-factor')->name('two-factor.')->group(function () {
    Route::get('setup', [TwoFactorController::class, 'setup'])->name('setup');
    Route::post('setup', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('confirm');
    Route::get('challenge', [TwoFactorController::class, 'challenge'])->name('challenge');
    Route::post('challenge', [TwoFactorController::class, 'verify'])->middleware('throttle:6,1')->name('verify');
    Route::get('recovery-codes', [TwoFactorController::class, 'codes'])->name('codes');
    Route::post('recovery-codes', [TwoFactorController::class, 'regenerate'])->middleware(['two-factor', 'throttle:6,1'])->name('regenerate');
});

Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');
Route::get('/success', [StripeController::class, 'success']);
Route::get('/cancel', [StripeController::class, 'cancel']);

require __DIR__.'/auth.php';
