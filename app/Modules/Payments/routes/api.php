<?php

use App\Http\Controllers\Payments\AppointmentFeeController;
use App\Http\Controllers\Payments\BillableServiceController;
use App\Http\Controllers\Payments\FakeCheckoutController;
use App\Http\Controllers\Payments\GatewayCallbackController;
use App\Http\Controllers\Payments\InsuranceClaimController;
use App\Http\Controllers\Payments\InvoiceController;
use App\Http\Controllers\Payments\OnlinePaymentController;
use App\Http\Controllers\Payments\PackageController;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Payments\RefundController;
use App\Http\Controllers\Payments\WalletController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payments & Billing API (prefix /billing) — service catalog, invoices, payments, refunds
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('billing')->name('billing.')->group(function (): void {

    Route::prefix('services')->name('services.')->controller(BillableServiceController::class)->group(function (): void {
        Route::middleware(['access:services.read_all|services.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{service}', 'show')->whereUuid('service')->name('show');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('/', 'store')->middleware('access:services.create')->name('store');
            Route::put('{service}', 'update')->whereUuid('service')->middleware('access:services.write')->name('update');
            Route::patch('{service}/archive', 'archive')->whereUuid('service')->middleware('access:services.write')->name('archive');
            Route::patch('{service}/unarchive', 'unarchive')->whereUuid('service')->middleware('access:services.write')->name('unarchive');
            Route::delete('{service}', 'destroy')->whereUuid('service')->middleware('access:services.delete')->name('destroy');
        });
        Route::patch('{service}/restore', 'restore')->whereUuid('service')->middleware(['access:services.delete', 'throttle:sensitive'])->name('restore');
    });
    Route::bulkActions('services', 'services')->middleware('access:services.write|services.delete');
    Route::dataTransfer('services', 'services');

    Route::prefix('invoices')->name('invoices.')->controller(InvoiceController::class)->group(function (): void {
        Route::middleware(['access:invoices.read_all|invoices.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('outstanding', 'outstanding')->name('outstanding');
            Route::get('{invoice}', 'show')->whereUuid('invoice')->name('show');
            Route::get('{invoice}/coverage', 'coverage')->whereUuid('invoice')->name('coverage');
        });
        Route::middleware(['access:invoices.create', 'throttle:write'])->group(function (): void {
            Route::post('/', 'store')->name('store');
            Route::post('charge', 'charge')->name('charge');
            Route::put('{invoice}', 'update')->whereUuid('invoice')->name('update');
        });
        Route::middleware('throttle:sensitive')->group(function (): void {
            Route::post('{invoice}/issue', 'issue')->whereUuid('invoice')->middleware(['access:invoices.write&invoices.issue', EnsureIdempotency::ALIAS])->name('issue');
            Route::post('{invoice}/remind', 'remind')->whereUuid('invoice')->middleware('access:invoices.write|payments.create')->name('remind');
            Route::post('{invoice}/cancel', 'cancel')->whereUuid('invoice')->middleware('access:invoices.write&invoices.cancel')->name('cancel');
        });
    });
    Route::dataTransfer('invoices', 'invoices');

    // Patient balance: money the clinic holds for a patient (deposits, paying invoices from it, pay-backs, adjustments)
    Route::prefix('patients/{patient}/wallet')->name('wallet.')->controller(WalletController::class)->whereUuid('patient')->group(function (): void {
        Route::get('/', 'show')->middleware(['access:wallet.read_all|wallet.read_own', 'throttle:read'])->name('show');
        Route::post('deposits', 'deposit')->middleware(['access:wallet.read_all&wallet.deposit', 'throttle:sensitive', EnsureIdempotency::ALIAS])->name('deposit');
        Route::post('withdrawals', 'withdraw')->middleware(['access:wallet.read_all&wallet.withdraw', 'throttle:sensitive', EnsureIdempotency::ALIAS])->name('withdraw');
        Route::post('adjustments', 'adjust')->middleware(['access:wallet.read_all&wallet.adjust', 'throttle:sensitive'])->name('adjust');
    });
    Route::post('invoices/{invoice}/pay-from-balance', [WalletController::class, 'payInvoice'])->whereUuid('invoice')
        ->middleware(['access:payments.create&wallet.read_all', 'throttle:sensitive', EnsureIdempotency::ALIAS])->name('invoices.pay-from-balance');

    // Fees for missed and late-cancelled visits (charged by the no-show / cancellation events; waived by admins and accountants)
    Route::prefix('appointment-fees')->name('appointment-fees.')->controller(AppointmentFeeController::class)->group(function (): void {
        Route::middleware(['access:appointment_fees.read_all|appointment_fees.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{fee}', 'show')->whereUuid('fee')->name('show');
        });
        Route::post('{fee}/waive', 'waive')->whereUuid('fee')->middleware(['access:appointment_fees.read_all&appointment_fees.waive', 'throttle:sensitive'])->name('waive');
    });
    Route::dataTransfer('appointment-fees', 'appointment_fees');
    Route::get('document-fees', [AppointmentFeeController::class, 'documentFees'])
        ->middleware(['access:written_documents.create|appointment_fees.read_all', 'throttle:read'])->name('document-fees');
    Route::get('appointments/{appointment}/cancellation-fee', [AppointmentFeeController::class, 'cancellationFee'])->whereUuid('appointment')
        ->middleware(['access:appointments.cancel', 'throttle:read'])->name('appointments.cancellation-fee');

    // Packages: the catalog, and the packages patients bought (sessions used instead of billing visits)
    Route::controller(PackageController::class)->group(function (): void {
        Route::middleware(['access:packages.read_all', 'throttle:read'])->group(function (): void {
            Route::get('packages', 'index')->name('packages.index');
            Route::get('packages/{package}', 'show')->whereUuid('package')->name('packages.show');
        });
        Route::post('packages', 'store')->middleware(['access:packages.create', 'throttle:write'])->name('packages.store');
        Route::put('packages/{package}', 'update')->whereUuid('package')->middleware(['access:packages.write', 'throttle:write'])->name('packages.update');
        Route::get('patient-packages', 'sold')->middleware(['access:patient_packages.read_all|patient_packages.read_own', 'throttle:read'])->name('patient-packages.index');
        Route::post('patients/{patient}/packages', 'sell')->whereUuid('patient')
            ->middleware(['access:patient_packages.create&packages.read_all', 'throttle:sensitive', EnsureIdempotency::ALIAS])->name('patient-packages.sell');
    });

    // Online payments: the payer is sent to the gateway's page; money is booked once the gateway confirms.
    Route::post('invoices/{invoice}/online-payments', [OnlinePaymentController::class, 'start'])->whereUuid('invoice')
        ->middleware(['access:online_payments.create', 'throttle:sensitive', EnsureIdempotency::ALIAS])->name('invoices.online-payments');
    Route::prefix('online-payments')->name('online-payments.')->controller(OnlinePaymentController::class)
        ->middleware(['access:online_payments.read_all|online_payments.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{payment}', 'show')->whereUuid('payment')->name('show');
        });

    Route::prefix('payments')->name('payments.')->controller(PaymentController::class)->group(function (): void {
        Route::middleware(['access:payments.read_all|payments.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('daily-summary', 'dailySummary')->name('daily-summary');
            Route::get('{payment}', 'show')->whereUuid('payment')->name('show');
        });
        Route::middleware('throttle:sensitive')->group(function (): void {
            Route::post('/', 'store')->middleware(['access:payments.create', EnsureIdempotency::ALIAS])->name('store');
            Route::post('{payment}/void', 'void')->whereUuid('payment')->middleware('access:payments.write&payments.void')->name('void');
        });
    });
    Route::dataTransfer('payments', 'payments');

    Route::prefix('refunds')->name('refunds.')->controller(RefundController::class)->group(function (): void {
        Route::middleware(['access:refunds.read_all|refunds.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{refund}', 'show')->whereUuid('refund')->name('show');
        });
        Route::middleware('throttle:sensitive')->group(function (): void {
            Route::post('/', 'store')->middleware('access:refunds.create&refunds.request')->name('store');
            Route::post('{refund}/approve', 'approve')->whereUuid('refund')->middleware('access:refunds.write&refunds.approve')->name('approve');
            Route::post('{refund}/reject', 'reject')->whereUuid('refund')->middleware('access:refunds.write&refunds.approve')->name('reject');
            Route::post('{refund}/process', 'process')->whereUuid('refund')->middleware(['access:refunds.write&refunds.process', EnsureIdempotency::ALIAS])->name('process');
        });
    });
    Route::bulkActions('refunds', 'refunds')->middleware('access:refunds.approve');
    Route::dataTransfer('refunds', 'refunds');

    Route::prefix('insurance-claims')->name('claims.')->controller(InsuranceClaimController::class)->group(function (): void {
        Route::middleware(['access:claims.read_all|claims.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{claim}', 'show')->whereUuid('claim')->name('show');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('/', 'store')->middleware(['access:claims.create', EnsureIdempotency::ALIAS])->name('store');
            Route::patch('{claim}/submit', 'submit')->whereUuid('claim')->middleware('access:claims.write')->name('submit');
            Route::patch('{claim}/cancel', 'cancel')->whereUuid('claim')->middleware('access:claims.write')->name('cancel');
        });
        Route::middleware('throttle:sensitive')->group(function (): void {
            Route::patch('{claim}/decision', 'decide')->whereUuid('claim')->middleware('access:claims.write&claims.decide')->name('decide');
            Route::post('remittances', 'remittance')->middleware(['access:claims.create&claims.remit', EnsureIdempotency::ALIAS])->name('remittance');
        });
    });
    Route::bulkActions('insurance-claims', 'claims')->middleware('access:claims.write');
    Route::dataTransfer('insurance-claims', 'claims');
});

// Gateway callbacks (no sign-in): the signed webhook and the payer's way back. Both only make the server look again.
Route::prefix('payments')->middleware('throttle:sensitive')->group(function (): void {
    Route::post('webhooks/tap', [GatewayCallbackController::class, 'webhook'])->name('webhooks.tap');
    Route::get('online/return', [GatewayCallbackController::class, 'return'])->name('online-payments.return');
});

// The fake gateway's test checkout (local only, signed links).
if (config('payments.online.driver') === 'fake' && ! app()->isProduction()) {
    Route::prefix('payments/fake-checkout')->middleware(['signed', 'throttle:public'])->controller(FakeCheckoutController::class)->group(function (): void {
        Route::get('{charge}', 'show')->name('fake-checkout.show');
        Route::get('{charge}/{outcome}', 'decide')->whereIn('outcome', ['pay', 'decline'])->name('fake-checkout.decide');
    });
}
