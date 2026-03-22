<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PropertyTypeController;
use App\Http\Controllers\Admin\SlabController;

// Admin Routes
Route::prefix('admin')->group(function () {
    // Authentication Routes
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [AuthController::class, 'login'])->name('admin.login.submit');
    Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');
    
    // Protected Admin Routes
    Route::middleware(['auth:web'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        
        // User Management
        Route::get('/users', [App\Http\Controllers\Admin\UserController::class, 'index'])->name('admin.users');
        Route::post('/users', [App\Http\Controllers\Admin\UserController::class, 'store'])->name('admin.users.store');
        Route::get('/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'show'])->name('admin.users.show');
        Route::get('/users/{user}/downline', [App\Http\Controllers\Admin\UserController::class, 'getDownlineByLevel'])->name('admin.users.downline');
        Route::put('/users/{user}/status', [App\Http\Controllers\Admin\UserController::class, 'updateStatus'])->name('admin.users.status');
        Route::post('/users/{user}/change-password', [App\Http\Controllers\Admin\UserController::class, 'changePassword'])->name('admin.users.change-password');
        Route::delete('/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('admin.users.destroy');
        Route::put('/users/{user}/referral-code', [App\Http\Controllers\Admin\UserController::class, 'updateReferralCode'])->name('admin.users.referral-code.update');
        Route::post('/users/{user}/withdraw', [App\Http\Controllers\Admin\UserController::class, 'withdrawAdminAmount'])->name('admin.users.withdraw');
        Route::get('/users-graph', [App\Http\Controllers\Admin\UserGraphController::class, 'index'])->name('admin.users.graph');
        Route::get('/users-graph/data', [App\Http\Controllers\Admin\UserGraphController::class, 'getTreeData'])->name('admin.users.graph.data');
        Route::get('/slab-upgrades', [App\Http\Controllers\Admin\UserController::class, 'slabUpgrades'])->name('admin.slab-upgrades');
        
        // Project Management
        Route::get('/projects', [App\Http\Controllers\Admin\ProjectController::class, 'index'])->name('admin.projects');
        Route::get('/projects/create', [App\Http\Controllers\Admin\ProjectController::class, 'create'])->name('admin.projects.create');
        Route::post('/projects', [App\Http\Controllers\Admin\ProjectController::class, 'store'])->name('admin.projects.store');
        // Import routes must be before {project} route to avoid route conflicts
        Route::get('/projects/import', [App\Http\Controllers\Admin\ProjectController::class, 'showImport'])->name('admin.projects.import');
        Route::post('/projects/import', [App\Http\Controllers\Admin\ProjectController::class, 'import'])->name('admin.projects.import.store');
        Route::get('/projects/{project}', [App\Http\Controllers\Admin\ProjectController::class, 'show'])->name('admin.projects.show');
        Route::get('/projects/{project}/view', [App\Http\Controllers\Admin\ProjectController::class, 'view'])->name('admin.projects.view');
        Route::post('/projects/{project}/plots', [App\Http\Controllers\Admin\ProjectController::class, 'storePlots'])->name('admin.projects.plots.store');
        Route::put('/projects/{project}/grid-batches/{gridBatchId}', [App\Http\Controllers\Admin\ProjectController::class, 'updateGridBatch'])->name('admin.projects.grid-batches.update');
        Route::delete('/projects/{project}/grid-batches/{gridBatchId}', [App\Http\Controllers\Admin\ProjectController::class, 'deleteGridBatch'])->name('admin.projects.grid-batches.delete');
        Route::get('/projects/{project}/edit', [App\Http\Controllers\Admin\ProjectController::class, 'edit'])->name('admin.projects.edit');
        Route::put('/projects/{id}', [App\Http\Controllers\Admin\ProjectController::class, 'update'])->name('admin.projects.update')->where('id', '[0-9]+');
        Route::delete('/projects/{project}', [App\Http\Controllers\Admin\ProjectController::class, 'destroy'])->name('admin.projects.destroy');
        Route::post('/projects/{project}/restore', [App\Http\Controllers\Admin\ProjectController::class, 'restore'])->name('admin.projects.restore');
        Route::get('/projects/{project}/export', [App\Http\Controllers\Admin\ProjectController::class, 'export'])->name('admin.projects.export');

        // Slabs & Property Types
        Route::get('/slabs', [SlabController::class, 'index'])->name('admin.slabs.index');
        Route::post('/slabs', [SlabController::class, 'store'])->name('admin.slabs.store');
        Route::get('/slabs/{slab}/edit', [SlabController::class, 'edit'])->name('admin.slabs.edit');
        Route::put('/slabs/{slab}', [SlabController::class, 'update'])->name('admin.slabs.update');
        Route::delete('/slabs/{slab}', [SlabController::class, 'destroy'])->name('admin.slabs.destroy');

        Route::get('/property-types', [PropertyTypeController::class, 'index'])->name('admin.property-types.index');
        Route::post('/property-types', [PropertyTypeController::class, 'store'])->name('admin.property-types.store');
        Route::delete('/property-types/{propertyType}', [PropertyTypeController::class, 'destroy'])->name('admin.property-types.destroy');

        Route::get('/measurement-units', [App\Http\Controllers\Admin\MeasurementUnitController::class, 'index'])->name('admin.measurement-units.index');
        Route::post('/measurement-units', [App\Http\Controllers\Admin\MeasurementUnitController::class, 'store'])->name('admin.measurement-units.store');
        Route::put('/measurement-units/{measurementUnit}', [App\Http\Controllers\Admin\MeasurementUnitController::class, 'update'])->name('admin.measurement-units.update');
        Route::delete('/measurement-units/{measurementUnit}', [App\Http\Controllers\Admin\MeasurementUnitController::class, 'destroy'])->name('admin.measurement-units.destroy');
        
        // KYC Management
        Route::get('/kyc', [App\Http\Controllers\Admin\KycController::class, 'index'])->name('admin.kyc');
        Route::get('/kyc/pending', [App\Http\Controllers\Admin\KycController::class, 'pending'])->name('admin.kyc.pending');
        Route::get('/kyc/{id}/details', [App\Http\Controllers\Admin\KycController::class, 'show'])->name('admin.kyc.show');
        Route::put('/kyc/{id}/approve', [App\Http\Controllers\Admin\KycController::class, 'approve'])->name('admin.kyc.approve');
        Route::put('/kyc/{id}/reject', [App\Http\Controllers\Admin\KycController::class, 'reject'])->name('admin.kyc.reject');
        
        // Wallet Management
        Route::get('/wallet', [App\Http\Controllers\Admin\WalletController::class, 'index'])->name('admin.wallet');
        Route::get('/wallet/deposits', [App\Http\Controllers\Admin\WalletController::class, 'deposits'])->name('admin.wallet.deposits');
        Route::get('/wallet/withdrawals', [App\Http\Controllers\Admin\WalletController::class, 'withdrawals'])->name('admin.wallet.withdrawals');
        Route::get('/wallet/withdrawals/{id}', [App\Http\Controllers\Admin\WalletController::class, 'showWithdrawal'])->name('admin.wallet.withdrawals.show');
        Route::put('/wallet/deposits/{id}/approve', [App\Http\Controllers\Admin\WalletController::class, 'approveDeposit'])->name('admin.wallet.deposits.approve');
        Route::put('/wallet/withdrawals/{id}/approve', [App\Http\Controllers\Admin\WalletController::class, 'approveWithdrawal'])->name('admin.wallet.withdrawals.approve');
        Route::put('/wallet/deposits/{id}/reject', [App\Http\Controllers\Admin\WalletController::class, 'rejectDeposit'])->name('admin.wallet.deposits.reject');
        Route::put('/wallet/withdrawals/{id}/reject', [App\Http\Controllers\Admin\WalletController::class, 'rejectWithdrawal'])->name('admin.wallet.withdrawals.reject');
        
        // Reports
        Route::get('/reports', [App\Http\Controllers\Admin\ReportController::class, 'index'])->name('admin.reports');
        Route::get('/reports/sales', [App\Http\Controllers\Admin\ReportController::class, 'sales'])->name('admin.reports.sales');
        Route::get('/reports/users', [App\Http\Controllers\Admin\ReportController::class, 'users'])->name('admin.reports.users');
        
        // Profile Management
        Route::get('/profile', [App\Http\Controllers\Admin\ProfileController::class, 'show'])->name('admin.profile.show');
        Route::put('/profile', [App\Http\Controllers\Admin\ProfileController::class, 'update'])->name('admin.profile.update');

        // Settings
        Route::get('/settings', [App\Http\Controllers\Admin\SettingController::class, 'index'])->name('admin.settings');
        Route::put('/settings', [App\Http\Controllers\Admin\SettingController::class, 'update'])->name('admin.settings.update');
        Route::post('/settings/reset-data', [App\Http\Controllers\Admin\SettingController::class, 'resetData'])->name('admin.settings.reset-data');
        Route::post('/settings/force-logout-all', [App\Http\Controllers\Admin\SettingController::class, 'forceLogoutAll'])->name('admin.settings.force-logout-all');
        Route::post('/settings/slider/add', [App\Http\Controllers\Admin\SettingController::class, 'addSliderImage'])->name('admin.settings.add-slider-image');
        Route::post('/settings/slider/delete', [App\Http\Controllers\Admin\SettingController::class, 'deleteSliderImage'])->name('admin.settings.delete-slider-image');
        Route::post('/settings/slider/save', [App\Http\Controllers\Admin\SettingController::class, 'saveSliderImages'])->name('admin.settings.save-slider-images');
        
        // Content Management
        Route::get('/content/about-us', [App\Http\Controllers\Admin\ContentController::class, 'aboutUs'])->name('admin.content.about-us');
        Route::put('/content/about-us', [App\Http\Controllers\Admin\ContentController::class, 'updateAboutUs'])->name('admin.content.about-us.update');
        Route::get('/content/contact-us', [App\Http\Controllers\Admin\ContentController::class, 'contactUs'])->name('admin.content.contact-us');
        Route::put('/content/contact-us', [App\Http\Controllers\Admin\ContentController::class, 'updateContactUs'])->name('admin.content.contact-us.update');
        Route::get('/content/privacy-policy', [App\Http\Controllers\Admin\ContentController::class, 'privacyPolicy'])->name('admin.content.privacy-policy');
        Route::put('/content/privacy-policy', [App\Http\Controllers\Admin\ContentController::class, 'updatePrivacyPolicy'])->name('admin.content.privacy-policy.update');
        Route::get('/content/terms-conditions', [App\Http\Controllers\Admin\ContentController::class, 'termsConditions'])->name('admin.content.terms-conditions');
        Route::put('/content/terms-conditions', [App\Http\Controllers\Admin\ContentController::class, 'updateTermsConditions'])->name('admin.content.terms-conditions.update');
        
        // Contact Inquiries Management
        Route::get('/contact-inquiries', [App\Http\Controllers\Admin\ContactInquiryController::class, 'index'])->name('admin.contact-inquiries.index');
        Route::get('/contact-inquiries/{contactInquiry}', [App\Http\Controllers\Admin\ContactInquiryController::class, 'show'])->name('admin.contact-inquiries.show');
        Route::post('/contact-inquiries/{contactInquiry}/resolve', [App\Http\Controllers\Admin\ContactInquiryController::class, 'resolve'])->name('admin.contact-inquiries.resolve');
        Route::post('/contact-inquiries/{contactInquiry}/reopen', [App\Http\Controllers\Admin\ContactInquiryController::class, 'reopen'])->name('admin.contact-inquiries.reopen');
        
        // Payment Methods Management
        Route::resource('payment-methods', App\Http\Controllers\Admin\PaymentMethodController::class)->names([
            'index' => 'admin.payment-methods.index',
            'create' => 'admin.payment-methods.create',
            'store' => 'admin.payment-methods.store',
            'edit' => 'admin.payment-methods.edit',
            'update' => 'admin.payment-methods.update',
            'destroy' => 'admin.payment-methods.destroy',
        ]);
        
        // Payment Requests Management
        Route::get('/payment-requests', [App\Http\Controllers\Admin\PaymentRequestController::class, 'index'])->name('admin.payment-requests.index');
        Route::get('/payment-requests/{id}', [App\Http\Controllers\Admin\PaymentRequestController::class, 'show'])->name('admin.payment-requests.show');
        Route::post('/payment-requests/{id}/approve', [App\Http\Controllers\Admin\PaymentRequestController::class, 'approve'])->name('admin.payment-requests.approve');
        Route::post('/payment-requests/{id}/reject', [App\Http\Controllers\Admin\PaymentRequestController::class, 'reject'])->name('admin.payment-requests.reject');

        // Bookings (Sales) – received vs pending, record instalment, deal done/failed
        Route::get('/bookings', [App\Http\Controllers\Admin\BookingController::class, 'index'])->name('admin.bookings.index');
        Route::get('/bookings/{id}', [App\Http\Controllers\Admin\BookingController::class, 'show'])->name('admin.bookings.show');
        Route::post('/bookings/{id}/record-payment', [App\Http\Controllers\Admin\BookingController::class, 'recordPayment'])->name('admin.bookings.record-payment');
        Route::post('/bookings/{id}/mark-deal-done', [App\Http\Controllers\Admin\BookingController::class, 'markDealDone'])->name('admin.bookings.mark-deal-done');
        Route::post('/bookings/{id}/mark-deal-failed', [App\Http\Controllers\Admin\BookingController::class, 'markDealFailed'])->name('admin.bookings.mark-deal-failed');
    });
});

// Redirect root to admin login
Route::get('/', function () {
    return redirect()->route('admin.login');
});
