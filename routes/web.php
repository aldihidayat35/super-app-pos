<?php

use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\CustomerAccessController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerSettingsController;
use App\Http\Controllers\Admin\DocumentSequenceController;
use App\Http\Controllers\Admin\PartyImportController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProductBarcodeController;
use App\Http\Controllers\Admin\ProductBrandController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserLocationController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Attendance\AttendanceRequestController;
use App\Http\Controllers\Attendance\CheckController as AttendanceCheckController;
use App\Http\Controllers\Attendance\CorrectionController as AttendanceCorrectionController;
use App\Http\Controllers\Attendance\EmployeeController as AttendanceEmployeeController;
use App\Http\Controllers\Attendance\ScheduleController as AttendanceScheduleController;
use App\Http\Controllers\Attendance\WorkShiftController as AttendanceWorkShiftController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordConfirmationController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\B2B\B2bAuthenticatedSessionController;
use App\Http\Controllers\B2B\B2bPasswordResetLinkController;
use App\Http\Controllers\B2B\CartController as B2bCartController;
use App\Http\Controllers\B2B\CatalogController as B2bCatalogController;
use App\Http\Controllers\B2B\CheckoutController as B2bCheckoutController;
use App\Http\Controllers\B2B\ComplaintController as B2bComplaintController;
use App\Http\Controllers\B2B\DashboardController as B2bDashboardController;
use App\Http\Controllers\B2B\OrderController as B2bOrderController;
use App\Http\Controllers\B2B\ProfileController as B2bProfileController;
use App\Http\Controllers\B2B\ReorderController as B2bReorderController;
use App\Http\Controllers\B2B\ShipmentTrackingController as B2bShipmentTrackingController;
use App\Http\Controllers\Control\AnomalyController;
use App\Http\Controllers\Control\ApprovalInboxController;
use App\Http\Controllers\Control\AuditLogController;
use App\Http\Controllers\Control\SecurityAuditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Notifications\AlertRuleController;
use App\Http\Controllers\Notifications\NotificationChannelController;
use App\Http\Controllers\Notifications\NotificationLogController;
use App\Http\Controllers\Notifications\NotificationRecipientController;
use App\Http\Controllers\Notifications\NotificationScheduleController;
use App\Http\Controllers\Notifications\NotificationTemplateController;
use App\Http\Controllers\Notifications\SecureDailyReportController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Pricing\HppHistoryController;
use App\Http\Controllers\Pricing\MarginSimulatorController;
use App\Http\Controllers\Pricing\PriceApprovalController;
use App\Http\Controllers\Pricing\PriceHistoryController;
use App\Http\Controllers\Pricing\PriceRuleController;
use App\Http\Controllers\Pricing\ProductPriceController;
use App\Http\Controllers\Pricing\SpecialPriceController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Purchasing\PurchaseOrderPrintController;
use App\Http\Controllers\Purchasing\PurchaseRequestController;
use App\Http\Controllers\Receivables\ReceivableController;
use App\Http\Controllers\Reports\AttendanceReportController;
use App\Http\Controllers\Reports\LossReportController;
use App\Http\Controllers\Reports\OwnerDashboardController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Reports\ReportExportController;
use App\Http\Controllers\Reports\RetailDashboardController;
use App\Http\Controllers\Reports\SupplierPerformanceController;
use App\Http\Controllers\Retail\CashShiftController;
use App\Http\Controllers\Retail\PosController;
use App\Http\Controllers\Retail\PosSaleController;
use App\Http\Controllers\Retail\RestockRequestController;
use App\Http\Controllers\Returns\InventoryLossController;
use App\Http\Controllers\Returns\ReturnController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentProofController;
use App\Http\Controllers\System\HealthController;
use App\Http\Controllers\Warehouse\B2bOrderController as WarehouseB2bOrderController;
use App\Http\Controllers\Warehouse\GoodsReceiptController;
use App\Http\Controllers\Warehouse\LocationTransferController;
use App\Http\Controllers\Warehouse\StockBatchController;
use App\Http\Controllers\Warehouse\StockCardController;
use App\Http\Controllers\Warehouse\StockController;
use App\Http\Controllers\Warehouse\StockMutationController;
use App\Http\Controllers\Warehouse\StockOpnameController;
use App\Http\Controllers\Warehouse\StockReservationController;
use App\Http\Controllers\Warehouse\StockTransferController;
use App\Http\Controllers\Warehouse\WarehouseDashboardController;
use App\Http\Controllers\Warehouse\WarehouseLocationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::get('/reports/daily/{token}', [SecureDailyReportController::class, 'show'])
    ->middleware('throttle:20,1')
    ->name('reports.daily.secure');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/langganan/login', [B2bAuthenticatedSessionController::class, 'create'])->name('langganan.login');
    Route::post('/langganan/login', [B2bAuthenticatedSessionController::class, 'store'])->name('langganan.login.store');
    Route::get('/langganan/forgot-password', [B2bPasswordResetLinkController::class, 'create'])->name('langganan.password.request');
    Route::post('/langganan/forgot-password', [B2bPasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('langganan.password.email');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware(['auth', 'active.user', 'b2b.customer'])->prefix('langganan')->name('langganan.')->group(function (): void {
    Route::redirect('/', '/langganan/dashboard')->name('home');
    Route::get('/dashboard', B2bDashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');
    Route::get('/katalog', [B2bCatalogController::class, 'index'])
        ->middleware('permission:b2b_orders.view|b2b_orders.create')
        ->name('katalog.index');
    Route::get('/katalog/{product}', [B2bCatalogController::class, 'show'])
        ->middleware('permission:b2b_orders.view|b2b_orders.create')
        ->name('katalog.show');
    Route::post('/keranjang/add', [B2bCatalogController::class, 'add'])
        ->middleware('permission:b2b_orders.create')
        ->name('keranjang.add');
    Route::get('/keranjang', [B2bCartController::class, 'index'])
        ->middleware('permission:b2b_orders.create')
        ->name('keranjang.index');
    Route::put('/keranjang', [B2bCartController::class, 'update'])
        ->middleware('permission:b2b_orders.create')
        ->name('keranjang.update');
    Route::delete('/keranjang/items/{item}', [B2bCartController::class, 'destroy'])
        ->middleware('permission:b2b_orders.create')
        ->name('keranjang.items.destroy');
    Route::post('/keranjang/checkout', [B2bCartController::class, 'submit'])
        ->middleware('permission:b2b_orders.create')
        ->name('keranjang.checkout');
    Route::get('/checkout', [B2bCheckoutController::class, 'show'])
        ->middleware('permission:b2b_orders.create')
        ->name('checkout.show');
    Route::post('/checkout', [B2bCheckoutController::class, 'store'])
        ->middleware('permission:b2b_orders.create')
        ->name('checkout.store');
    Route::get('/profil', [B2bProfileController::class, 'edit'])
        ->middleware('permission:customers.view_own')
        ->name('profil.edit');
    Route::put('/profil', [B2bProfileController::class, 'update'])
        ->middleware('role:langganan_owner')
        ->name('profil.update');
    Route::get('/reorder', [B2bReorderController::class, 'index'])
        ->middleware('permission:b2b_orders.create')
        ->name('reorder.index');
    Route::post('/reorder', [B2bReorderController::class, 'store'])
        ->middleware('permission:b2b_orders.create')
        ->name('reorder.store');
    Route::get('/orders', [B2bOrderController::class, 'index'])
        ->middleware('permission:b2b_orders.view')
        ->name('orders.index');
    Route::get('/orders/{order}', [B2bOrderController::class, 'show'])
        ->middleware('permission:b2b_orders.view')
        ->name('orders.show');
    Route::post('/orders/{order}/cancel', [B2bOrderController::class, 'cancel'])
        ->middleware('permission:b2b_orders.create')
        ->name('orders.cancel');
    Route::post('/orders/{order}/receive', [B2bOrderController::class, 'receive'])
        ->middleware('permission:b2b_orders.create')
        ->name('orders.receive');
    Route::get('/shipments/{shipment}', [B2bShipmentTrackingController::class, 'show'])
        ->middleware('permission:b2b_orders.view')
        ->name('shipments.show');
    Route::post('/shipments/{shipment}/confirm', [B2bShipmentTrackingController::class, 'confirm'])
        ->middleware('permission:b2b_orders.create')
        ->name('shipments.confirm');
    Route::get('/complaints', [B2bComplaintController::class, 'index'])
        ->middleware('permission:b2b_orders.view|complaints.view')
        ->name('complaints.index');
    Route::post('/complaints', [B2bComplaintController::class, 'store'])
        ->middleware('permission:b2b_orders.create|complaints.create')
        ->name('complaints.store');
    Route::post('/logout', [B2bAuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', 'active.user'])->group(function (): void {
    Route::get('/invoices', [InvoiceController::class, 'index'])
        ->middleware('permission:invoices.view|receivables.view')
        ->name('invoices.index');
    Route::post('/invoices/from-b2b/{order}', [InvoiceController::class, 'issueFromOrder'])
        ->middleware('permission:invoices.create|b2b_orders.approve')
        ->name('invoices.issue-b2b');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])
        ->middleware('permission:invoices.view|receivables.view')
        ->name('invoices.show');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])
        ->middleware('permission:invoices.view|receivables.view')
        ->name('invoices.pdf');
    Route::get('/payments/create', [PaymentController::class, 'create'])
        ->middleware('permission:payments.create')
        ->name('payments.create');
    Route::post('/payments', [PaymentController::class, 'store'])
        ->middleware('permission:payments.create')
        ->name('payments.store');
    Route::get('/payments/{payment}/verify', [PaymentController::class, 'verifyForm'])
        ->middleware('permission:payments.verify|approvals.approve')
        ->name('payments.verify');
    Route::post('/payments/{payment}/verify', [PaymentController::class, 'verify'])
        ->middleware('permission:payments.verify|approvals.approve')
        ->name('payments.verify.store');
    Route::get('/payments/{payment}/proof', [PaymentController::class, 'proof'])
        ->middleware('signed')
        ->name('payments.proof');
});

Route::middleware(['auth', 'active.user', 'internal.access', 'work.location'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');
    Route::get('/owner/dashboard', OwnerDashboardController::class)
        ->middleware('permission:dashboard.view|reports.view')
        ->name('owner.dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->middleware('password.confirm')
        ->name('profile.password.update');

    Route::get('/confirm-password', [PasswordConfirmationController::class, 'create'])->name('password.confirm');
    Route::post('/confirm-password', [PasswordConfirmationController::class, 'store'])->name('password.confirm.store');

    Route::get('/verify-email', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::prefix('receivables')->name('receivables.')->group(function (): void {
        Route::get('/dashboard', [ReceivableController::class, 'dashboard'])
            ->middleware('permission:receivables.view')
            ->name('dashboard');
        Route::get('/', [ReceivableController::class, 'index'])
            ->middleware('permission:receivables.view')
            ->name('index');
        Route::get('/payments/create', [ReceivableController::class, 'paymentCreate'])
            ->middleware('permission:receivables.pay|payments.create')
            ->name('payments.create');
        Route::post('/payments', [ReceivableController::class, 'paymentStore'])
            ->middleware('permission:receivables.pay|payments.create')
            ->name('payments.store');
        Route::get('/reminders', [ReceivableController::class, 'reminders'])
            ->middleware('permission:receivables.view|receivables.remind')
            ->name('reminders');
        Route::post('/reminders', [ReceivableController::class, 'storeReminder'])
            ->middleware('permission:receivables.remind|receivables.pay')
            ->name('reminders.store');
        Route::get('/credit-limits', [ReceivableController::class, 'creditLimits'])
            ->middleware('permission:receivables.manage_limits|customers.manage_settings')
            ->name('credit-limits');
        Route::put('/credit-limits/{creditLimit}', [ReceivableController::class, 'updateCreditLimit'])
            ->middleware('permission:receivables.manage_limits|customers.manage_settings')
            ->name('credit-limits.update');
        Route::get('/customers/{customer}', [ReceivableController::class, 'customer'])
            ->middleware('permission:receivables.view')
            ->name('customers.show');
        Route::get('/{receivable}/adjustments', [ReceivableController::class, 'adjustments'])
            ->middleware('permission:receivables.adjust|receivables.approve')
            ->name('adjustments');
        Route::post('/{receivable}/adjustments', [ReceivableController::class, 'storeAdjustment'])
            ->middleware('permission:receivables.adjust')
            ->name('adjustments.store');
        Route::post('/credit-notes/{creditNote}/approve', [ReceivableController::class, 'approveAdjustment'])
            ->middleware('permission:receivables.approve|approvals.approve')
            ->name('credit-notes.approve');
    });

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:admin.users.view')
            ->name('users.index');
        Route::get('/users/export', [UserController::class, 'export'])
            ->middleware('permission:admin.users.export')
            ->name('users.export');
        Route::get('/users/create', [UserController::class, 'create'])
            ->middleware('permission:admin.users.create')
            ->name('users.create');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:admin.users.create')
            ->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])
            ->middleware('permission:admin.users.view')
            ->name('users.show');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])
            ->middleware('permission:admin.users.update')
            ->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])
            ->middleware('permission:admin.users.update')
            ->name('users.update');
        Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])
            ->middleware('permission:admin.users.update')
            ->name('users.deactivate');
        Route::post('/users/{user}/password-reset', [UserController::class, 'sendPasswordReset'])
            ->middleware('permission:admin.users.reset_password')
            ->name('users.password-reset');
        Route::get('/users/{user}/locations', [UserLocationController::class, 'edit'])
            ->middleware('permission:admin.users.assign_locations')
            ->name('users.locations.edit');
        Route::put('/users/{user}/locations', [UserLocationController::class, 'update'])
            ->middleware('permission:admin.users.assign_locations')
            ->name('users.locations.update');

        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware('permission:admin.roles.view')
            ->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])
            ->middleware('permission:admin.roles.create')
            ->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('permission:admin.roles.create')
            ->name('roles.store');
        Route::get('/roles/{role}', [RoleController::class, 'show'])
            ->middleware('permission:admin.roles.view')
            ->name('roles.show');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])
            ->middleware('permission:admin.roles.update')
            ->name('roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:admin.roles.update')
            ->name('roles.update');
        Route::post('/roles/{role}/duplicate', [RoleController::class, 'duplicate'])
            ->middleware('permission:admin.roles.create')
            ->name('roles.duplicate');
        Route::put('/roles/{role}/permissions', [RoleController::class, 'updatePermissions'])
            ->middleware('permission:admin.roles.update')
            ->name('roles.permissions.update');

        Route::get('/permissions', [PermissionController::class, 'index'])
            ->middleware('permission:admin.permissions.view')
            ->name('permissions.index');

        Route::get('/system/health', HealthController::class)
            ->middleware('permission:system.health.view')
            ->name('system.health');

        Route::prefix('notifications')->name('notifications.')->group(function (): void {
            Route::get('/channels', [NotificationChannelController::class, 'index'])
                ->middleware('permission:notifications.view')
                ->name('channels.index');
            Route::post('/channels', [NotificationChannelController::class, 'store'])
                ->middleware('permission:notifications.update')
                ->name('channels.store');
            Route::put('/channels/{channel}', [NotificationChannelController::class, 'update'])
                ->middleware('permission:notifications.update')
                ->name('channels.update');
            Route::post('/channels/{channel}/test', [NotificationChannelController::class, 'test'])
                ->middleware('permission:notifications.send')
                ->name('channels.test');

            Route::get('/templates', [NotificationTemplateController::class, 'index'])
                ->middleware('permission:notifications.view')
                ->name('templates.index');
            Route::post('/templates', [NotificationTemplateController::class, 'store'])
                ->middleware('permission:notifications.update')
                ->name('templates.store');
            Route::put('/templates/{template}', [NotificationTemplateController::class, 'update'])
                ->middleware('permission:notifications.update')
                ->name('templates.update');
            Route::post('/templates/{template}/preview', [NotificationTemplateController::class, 'preview'])
                ->middleware('permission:notifications.view')
                ->name('templates.preview');

            Route::get('/schedules', [NotificationScheduleController::class, 'index'])
                ->middleware('permission:notifications.view')
                ->name('schedules.index');
            Route::post('/schedules', [NotificationScheduleController::class, 'store'])
                ->middleware('permission:notifications.update')
                ->name('schedules.store');
            Route::put('/schedules/{schedule}', [NotificationScheduleController::class, 'update'])
                ->middleware('permission:notifications.update')
                ->name('schedules.update');
            Route::post('/schedules/{schedule}/run', [NotificationScheduleController::class, 'run'])
                ->middleware('permission:notifications.send')
                ->name('schedules.run');

            Route::get('/recipients', [NotificationRecipientController::class, 'index'])
                ->middleware('permission:notifications.view')
                ->name('recipients.index');
            Route::post('/recipients', [NotificationRecipientController::class, 'store'])
                ->middleware('permission:notifications.update')
                ->name('recipients.store');
            Route::put('/recipients/{recipient}', [NotificationRecipientController::class, 'update'])
                ->middleware('permission:notifications.update')
                ->name('recipients.update');

            Route::get('/logs', [NotificationLogController::class, 'index'])
                ->middleware('permission:notifications.view|audit.view')
                ->name('logs.index');
            Route::post('/logs/{log}/retry', [NotificationLogController::class, 'retry'])
                ->middleware('permission:notifications.send')
                ->name('logs.retry');
            Route::post('/report-tokens/{token}/revoke', [SecureDailyReportController::class, 'revoke'])
                ->middleware('permission:notifications.update')
                ->name('report-tokens.revoke');

            Route::get('/alerts', [AlertRuleController::class, 'index'])
                ->middleware('permission:notifications.view|audit.view')
                ->name('alerts.index');
            Route::post('/alerts', [AlertRuleController::class, 'store'])
                ->middleware('permission:notifications.update|audit.resolve')
                ->name('alerts.store');
            Route::put('/alerts/{alert}', [AlertRuleController::class, 'update'])
                ->middleware('permission:notifications.update|audit.resolve')
                ->name('alerts.update');
            Route::post('/alerts/{alert}/preview', [AlertRuleController::class, 'preview'])
                ->middleware('permission:notifications.view|audit.view')
                ->name('alerts.preview');
        });

        Route::resource('warehouses', WarehouseController::class)
            ->except(['destroy'])
            ->middlewareFor(['index', 'show'], 'permission:admin.warehouses.view')
            ->middlewareFor(['create', 'store'], 'permission:admin.warehouses.create')
            ->middlewareFor(['edit', 'update'], 'permission:admin.warehouses.update');
        Route::patch('/warehouses/{warehouse}/deactivate', [WarehouseController::class, 'deactivate'])
            ->middleware('permission:admin.warehouses.update')
            ->name('warehouses.deactivate');

        Route::resource('branches', BranchController::class)
            ->except(['destroy'])
            ->middlewareFor(['index', 'show'], 'permission:admin.branches.view')
            ->middlewareFor(['create', 'store'], 'permission:admin.branches.create')
            ->middlewareFor(['edit', 'update'], 'permission:admin.branches.update');
        Route::patch('/branches/{branch}/deactivate', [BranchController::class, 'deactivate'])
            ->middleware('permission:admin.branches.update')
            ->name('branches.deactivate');

        Route::get('/settings/general', [SystemSettingController::class, 'edit'])
            ->middleware('permission:admin.settings.view')
            ->name('settings.general.edit');
        Route::put('/settings/general', [SystemSettingController::class, 'update'])
            ->middleware('permission:admin.settings.update')
            ->name('settings.general.update');
        Route::get('/settings/document-numbers', [DocumentSequenceController::class, 'index'])
            ->middleware('permission:admin.settings.view')
            ->name('settings.document-numbers.index');
        Route::put('/settings/document-numbers', [DocumentSequenceController::class, 'update'])
            ->middleware('permission:admin.settings.update')
            ->name('settings.document-numbers.update');

        Route::resource('product-categories', ProductCategoryController::class)
            ->except(['show', 'destroy'])
            ->middlewareFor(['index'], 'permission:products.view')
            ->middlewareFor(['create', 'store'], 'permission:products.create')
            ->middlewareFor(['edit', 'update'], 'permission:products.update');
        Route::patch('/product-categories/{product_category}/deactivate', [ProductCategoryController::class, 'deactivate'])
            ->middleware('permission:products.update')
            ->name('product-categories.deactivate');

        Route::resource('product-brands', ProductBrandController::class)
            ->except(['show', 'destroy'])
            ->middlewareFor(['index'], 'permission:products.view')
            ->middlewareFor(['create', 'store'], 'permission:products.create')
            ->middlewareFor(['edit', 'update'], 'permission:products.update');
        Route::patch('/product-brands/{product_brand}/deactivate', [ProductBrandController::class, 'deactivate'])
            ->middleware('permission:products.update')
            ->name('product-brands.deactivate');

        Route::resource('units', UnitController::class)
            ->except(['show', 'destroy'])
            ->middlewareFor(['index'], 'permission:products.view')
            ->middlewareFor(['create', 'store'], 'permission:products.create')
            ->middlewareFor(['edit', 'update'], 'permission:products.update');
        Route::patch('/units/{unit}/deactivate', [UnitController::class, 'deactivate'])
            ->middleware('permission:products.update')
            ->name('units.deactivate');

        Route::get('/products/export', [ProductController::class, 'export'])
            ->middleware('permission:products.export')
            ->name('products.export');
        Route::get('/products/barcodes', [ProductBarcodeController::class, 'index'])
            ->middleware('permission:products.print_barcode')
            ->name('products.barcodes.index');
        Route::get('/products/barcodes/pdf', [ProductBarcodeController::class, 'pdf'])
            ->middleware('permission:products.print_barcode')
            ->name('products.barcodes.pdf');
        Route::get('/products/import', [ProductImportController::class, 'index'])
            ->middleware('permission:products.import')
            ->name('products.import.index');
        Route::get('/products/import/template', [ProductImportController::class, 'template'])
            ->middleware('permission:products.export')
            ->name('products.import.template');
        Route::post('/products/import/preview', [ProductImportController::class, 'preview'])
            ->middleware('permission:products.import')
            ->name('products.import.preview');
        Route::post('/products/import/commit', [ProductImportController::class, 'commit'])
            ->middleware('permission:products.import')
            ->name('products.import.commit');
        Route::resource('products', ProductController::class)
            ->except(['destroy'])
            ->middlewareFor(['index', 'show'], 'permission:products.view')
            ->middlewareFor(['create', 'store'], 'permission:products.create')
            ->middlewareFor(['edit', 'update'], 'permission:products.update');
        Route::patch('/products/{product}/deactivate', [ProductController::class, 'deactivate'])
            ->middleware('permission:products.update')
            ->name('products.deactivate');

        Route::get('/parties/{type}/import', [PartyImportController::class, 'index'])
            ->middleware('permission:suppliers.import|customers.import')
            ->name('parties.import.index');
        Route::get('/parties/{type}/import/template', [PartyImportController::class, 'template'])
            ->middleware('permission:suppliers.import|customers.import')
            ->name('parties.import.template');
        Route::post('/parties/{type}/import/preview', [PartyImportController::class, 'preview'])
            ->middleware('permission:suppliers.import|customers.import')
            ->name('parties.import.preview');
        Route::post('/parties/{type}/import/commit', [PartyImportController::class, 'commit'])
            ->middleware('permission:suppliers.import|customers.import')
            ->name('parties.import.commit');

        Route::get('/suppliers/export', [SupplierController::class, 'export'])
            ->middleware('permission:suppliers.export')
            ->name('suppliers.export');
        Route::resource('suppliers', SupplierController::class)
            ->except(['destroy'])
            ->middlewareFor(['index', 'show'], 'permission:suppliers.view')
            ->middlewareFor(['create', 'store'], 'permission:suppliers.create')
            ->middlewareFor(['edit', 'update'], 'permission:suppliers.update');
        Route::patch('/suppliers/{supplier}/deactivate', [SupplierController::class, 'deactivate'])
            ->middleware('permission:suppliers.update')
            ->name('suppliers.deactivate');

        Route::get('/customers/export', [CustomerController::class, 'export'])
            ->middleware('permission:customers.export')
            ->name('customers.export');
        Route::get('/customers/{customer}/access', [CustomerAccessController::class, 'edit'])
            ->middleware('permission:customers.manage_access')
            ->name('customers.access.edit');
        Route::put('/customers/{customer}/access', [CustomerAccessController::class, 'update'])
            ->middleware('permission:customers.manage_access')
            ->name('customers.access.update');
        Route::post('/customers/{customer}/access/reset-password', [CustomerAccessController::class, 'sendReset'])
            ->middleware('permission:customers.manage_access')
            ->name('customers.access.reset-password');
        Route::get('/customers/{customer}/settings', [CustomerSettingsController::class, 'edit'])
            ->middleware('permission:customers.manage_settings')
            ->name('customers.settings.edit');
        Route::put('/customers/{customer}/settings', [CustomerSettingsController::class, 'update'])
            ->middleware('permission:customers.manage_settings')
            ->name('customers.settings.update');
        Route::resource('customers', CustomerController::class)
            ->except(['destroy'])
            ->middlewareFor(['index', 'show'], 'permission:customers.view|customers.view_own')
            ->middlewareFor(['create', 'store'], 'permission:customers.create')
            ->middlewareFor(['edit', 'update'], 'permission:customers.update');
        Route::patch('/customers/{customer}/deactivate', [CustomerController::class, 'deactivate'])
            ->middleware('permission:customers.update')
            ->name('customers.deactivate');
    });

    Route::prefix('attendance')->name('attendance.')->group(function (): void {
        Route::get('/employees', [AttendanceEmployeeController::class, 'index'])->middleware('permission:attendance.view')->name('employees.index');
        Route::get('/employees/create', [AttendanceEmployeeController::class, 'create'])->middleware('permission:attendance.update')->name('employees.create');
        Route::post('/employees', [AttendanceEmployeeController::class, 'store'])->middleware('permission:attendance.update')->name('employees.store');
        Route::get('/employees/{employee}/edit', [AttendanceEmployeeController::class, 'edit'])->middleware('permission:attendance.update')->name('employees.edit');
        Route::put('/employees/{employee}', [AttendanceEmployeeController::class, 'update'])->middleware('permission:attendance.update')->name('employees.update');
        Route::patch('/employees/{employee}/deactivate', [AttendanceEmployeeController::class, 'deactivate'])->middleware('permission:attendance.update')->name('employees.deactivate');

        Route::get('/work-shifts', [AttendanceWorkShiftController::class, 'index'])->middleware('permission:attendance.view')->name('work-shifts.index');
        Route::get('/work-shifts/create', [AttendanceWorkShiftController::class, 'create'])->middleware('permission:attendance.update')->name('work-shifts.create');
        Route::post('/work-shifts', [AttendanceWorkShiftController::class, 'store'])->middleware('permission:attendance.update')->name('work-shifts.store');
        Route::get('/work-shifts/{workShift}/edit', [AttendanceWorkShiftController::class, 'edit'])->middleware('permission:attendance.update')->name('work-shifts.edit');
        Route::put('/work-shifts/{workShift}', [AttendanceWorkShiftController::class, 'update'])->middleware('permission:attendance.update')->name('work-shifts.update');

        Route::get('/schedules', [AttendanceScheduleController::class, 'index'])->middleware('permission:attendance.view')->name('schedules.index');
        Route::post('/schedules', [AttendanceScheduleController::class, 'store'])->middleware('permission:attendance.update')->name('schedules.store');

        Route::get('/check', [AttendanceCheckController::class, 'show'])->middleware('permission:attendance.check')->name('check.show');
        Route::post('/check/in', [AttendanceCheckController::class, 'checkIn'])->middleware('permission:attendance.check')->name('check.in');
        Route::post('/check/out', [AttendanceCheckController::class, 'checkOut'])->middleware('permission:attendance.check')->name('check.out');

        Route::get('/requests', [AttendanceRequestController::class, 'index'])->middleware('permission:attendance.check|attendance.approve')->name('requests.index');
        Route::post('/requests', [AttendanceRequestController::class, 'store'])->middleware('permission:attendance.check')->name('requests.store');
        Route::post('/requests/{attendanceRequest}/approve', [AttendanceRequestController::class, 'approve'])->middleware('permission:attendance.approve')->name('requests.approve');
        Route::post('/requests/{attendanceRequest}/reject', [AttendanceRequestController::class, 'reject'])->middleware('permission:attendance.approve')->name('requests.reject');

        Route::get('/corrections', [AttendanceCorrectionController::class, 'index'])->middleware('permission:attendance.update|attendance.approve')->name('corrections.index');
        Route::post('/corrections', [AttendanceCorrectionController::class, 'store'])->middleware('permission:attendance.update')->name('corrections.store');
        Route::post('/corrections/{correction}/approve', [AttendanceCorrectionController::class, 'approve'])->middleware('permission:attendance.approve')->name('corrections.approve');
        Route::post('/corrections/{correction}/reject', [AttendanceCorrectionController::class, 'reject'])->middleware('permission:attendance.approve')->name('corrections.reject');
    });

    Route::get('/approvals', [ApprovalInboxController::class, 'index'])
        ->middleware('permission:approvals.view')
        ->name('approvals.index');
    Route::get('/approvals/{approval}', [ApprovalInboxController::class, 'show'])
        ->middleware('permission:approvals.view')
        ->name('approvals.show');
    Route::post('/approvals/{approval}/approve', [ApprovalInboxController::class, 'approve'])
        ->middleware('permission:approvals.approve')
        ->name('approvals.approve');
    Route::post('/approvals/{approval}/reject', [ApprovalInboxController::class, 'reject'])
        ->middleware('permission:approvals.approve')
        ->name('approvals.reject');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view')
        ->name('audit-logs.index');
    Route::get('/audit-logs/export', [AuditLogController::class, 'export'])
        ->middleware('permission:audit.export')
        ->name('audit-logs.export');
    Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])
        ->middleware('permission:audit.view')
        ->name('audit-logs.show');
    Route::get('/audit/anomalies', [AnomalyController::class, 'index'])
        ->middleware('permission:audit.view')
        ->name('audit.anomalies.index');
    Route::post('/audit/anomalies/{anomaly}/resolve', [AnomalyController::class, 'resolve'])
        ->middleware('permission:audit.resolve')
        ->name('audit.anomalies.resolve');
    Route::get('/audit/security', [SecurityAuditController::class, 'index'])
        ->middleware('permission:audit.view')
        ->name('audit.security.index');

    Route::prefix('warehouse')->name('warehouse.')->group(function (): void {
        Route::get('/dashboard', WarehouseDashboardController::class)
            ->middleware('permission:stock.view')
            ->name('dashboard');

        Route::get('/locations', [WarehouseLocationController::class, 'index'])
            ->middleware('permission:stock.view')
            ->name('locations.index');
        Route::get('/locations/create', [WarehouseLocationController::class, 'create'])
            ->middleware('permission:stock.create')
            ->name('locations.create');
        Route::post('/locations', [WarehouseLocationController::class, 'store'])
            ->middleware('permission:stock.create')
            ->name('locations.store');
        Route::get('/locations/{location}/edit', [WarehouseLocationController::class, 'edit'])
            ->middleware('permission:stock.update')
            ->name('locations.edit');
        Route::put('/locations/{location}', [WarehouseLocationController::class, 'update'])
            ->middleware('permission:stock.update')
            ->name('locations.update');
        Route::patch('/locations/{location}/deactivate', [WarehouseLocationController::class, 'deactivate'])
            ->middleware('permission:stock.update')
            ->name('locations.deactivate');

        Route::get('/stocks', [StockController::class, 'index'])
            ->middleware('permission:stock.view')
            ->name('stocks.index');
        Route::get('/stocks/export', [StockController::class, 'export'])
            ->middleware('permission:reports.export|stock.view')
            ->name('stocks.export');

        Route::get('/stock-card', [StockCardController::class, 'index'])
            ->middleware('permission:stock.view')
            ->name('stock-card.index');
        Route::get('/stock-card/export', [StockCardController::class, 'export'])
            ->middleware('permission:reports.export|stock.view')
            ->name('stock-card.export');

        Route::get('/stock-mutations/{stockMutation}', [StockMutationController::class, 'show'])
            ->middleware('permission:stock.view')
            ->name('stock-mutations.show');

        Route::get('/b2b-orders', [WarehouseB2bOrderController::class, 'index'])
            ->middleware('permission:b2b_orders.view')
            ->name('b2b-orders.index');
        Route::get('/b2b-orders/{order}/review', [WarehouseB2bOrderController::class, 'review'])
            ->middleware('permission:b2b_orders.view|b2b_orders.approve')
            ->name('b2b-orders.review');
        Route::post('/b2b-orders/{order}/reserve', [WarehouseB2bOrderController::class, 'reserve'])
            ->middleware('permission:b2b_orders.approve')
            ->name('b2b-orders.reserve');
        Route::post('/b2b-orders/{order}/reject', [WarehouseB2bOrderController::class, 'reject'])
            ->middleware('permission:b2b_orders.approve')
            ->name('b2b-orders.reject');
        Route::post('/b2b-orders/{order}/pack', [WarehouseB2bOrderController::class, 'pack'])
            ->middleware('permission:b2b_orders.approve')
            ->name('b2b-orders.pack');
        Route::post('/b2b-orders/{order}/ship', [WarehouseB2bOrderController::class, 'ship'])
            ->middleware('permission:b2b_orders.approve')
            ->name('b2b-orders.ship');

        Route::get('/reservations', [StockReservationController::class, 'index'])
            ->middleware('permission:b2b_orders.view|stock.view')
            ->name('reservations.index');
        Route::post('/reservations/expire', [StockReservationController::class, 'expire'])
            ->middleware('permission:b2b_orders.approve')
            ->name('reservations.expire');
        Route::post('/reservations/{reservation}/release', [StockReservationController::class, 'release'])
            ->middleware('permission:b2b_orders.approve')
            ->name('reservations.release');

        Route::get('/goods-receipts/export', [GoodsReceiptController::class, 'export'])
            ->middleware('permission:reports.export|goods_receipts.view')
            ->name('goods-receipts.export');
        Route::get('/goods-receipts/{goodsReceipt}/print', [GoodsReceiptController::class, 'print'])
            ->middleware('permission:goods_receipts.view')
            ->name('goods-receipts.print');
        Route::post('/goods-receipts/{goodsReceipt}/post', [GoodsReceiptController::class, 'post'])
            ->middleware('permission:goods_receipts.create')
            ->name('goods-receipts.post');
        Route::resource('goods-receipts', GoodsReceiptController::class)
            ->except(['destroy'])
            ->middlewareFor(['index', 'show'], 'permission:goods_receipts.view')
            ->middlewareFor(['create', 'store', 'edit', 'update'], 'permission:goods_receipts.create');

        Route::get('/location-transfers', [LocationTransferController::class, 'index'])
            ->middleware('permission:stock_transfers.view|stock.view')
            ->name('location-transfers.index');
        Route::post('/location-transfers', [LocationTransferController::class, 'store'])
            ->middleware('permission:stock_transfers.create|stock.create')
            ->name('location-transfers.store');

        Route::get('/batches', [StockBatchController::class, 'index'])
            ->middleware('permission:stock.view')
            ->name('batches.index');

        Route::get('/stock-opnames', [StockOpnameController::class, 'index'])
            ->middleware('permission:stock_adjustments.view')
            ->name('stock-opnames.index');
        Route::post('/stock-opnames', [StockOpnameController::class, 'store'])
            ->middleware('permission:stock_adjustments.create')
            ->name('stock-opnames.store');
        Route::post('/stock-opnames/{stockOpname}/start', [StockOpnameController::class, 'start'])
            ->middleware('permission:stock_adjustments.create')
            ->name('stock-opnames.start');
        Route::get('/stock-opnames/{stockOpname}/count', [StockOpnameController::class, 'count'])
            ->middleware('permission:stock_adjustments.create')
            ->name('stock-opnames.count');
        Route::post('/stock-opnames/{stockOpname}/count/{item}', [StockOpnameController::class, 'countItem'])
            ->middleware('permission:stock_adjustments.create')
            ->name('stock-opnames.count-item');
        Route::post('/stock-opnames/{stockOpname}/import', [StockOpnameController::class, 'import'])
            ->middleware('permission:stock_adjustments.create')
            ->name('stock-opnames.import');
        Route::post('/stock-opnames/{stockOpname}/submit', [StockOpnameController::class, 'submit'])
            ->middleware('permission:stock_adjustments.create')
            ->name('stock-opnames.submit');
        Route::get('/stock-opnames/{stockOpname}/variance/export', [StockOpnameController::class, 'exportVariance'])
            ->middleware('permission:reports.export|stock_adjustments.view')
            ->name('stock-opnames.variance.export');
        Route::get('/stock-opnames/{stockOpname}/variance', [StockOpnameController::class, 'variance'])
            ->middleware('permission:stock_adjustments.view')
            ->name('stock-opnames.variance');
        Route::get('/stock-opnames/{stockOpname}/approval', [StockOpnameController::class, 'approval'])
            ->middleware('permission:stock_adjustments.view|stock_adjustments.approve')
            ->name('stock-opnames.approval');
        Route::post('/stock-opnames/{stockOpname}/approve', [StockOpnameController::class, 'approve'])
            ->middleware('permission:stock_adjustments.approve')
            ->name('stock-opnames.approve');
        Route::post('/stock-opnames/{stockOpname}/reject', [StockOpnameController::class, 'reject'])
            ->middleware('permission:stock_adjustments.approve')
            ->name('stock-opnames.reject');
        Route::post('/stock-opnames/{stockOpname}/complete', [StockOpnameController::class, 'complete'])
            ->middleware('permission:stock_adjustments.approve')
            ->name('stock-opnames.complete');
        Route::get('/stock-opnames/{stockOpname}/report', [StockOpnameController::class, 'report'])
            ->middleware('permission:stock_adjustments.view')
            ->name('stock-opnames.report');
        Route::get('/stock-opnames/{stockOpname}', [StockOpnameController::class, 'show'])
            ->middleware('permission:stock_adjustments.view')
            ->name('stock-opnames.show');

        Route::get('/stock-transfers/create', [StockTransferController::class, 'create'])
            ->middleware('permission:stock_transfers.create')
            ->name('stock-transfers.create');
        Route::post('/stock-transfers', [StockTransferController::class, 'store'])
            ->middleware('permission:stock_transfers.create')
            ->name('stock-transfers.store');
        Route::get('/stock-transfers', [StockTransferController::class, 'index'])
            ->middleware('permission:stock_transfers.view')
            ->name('stock-transfers.index');
        Route::post('/stock-transfers/{stockTransfer}/approve', [StockTransferController::class, 'approve'])
            ->middleware('permission:stock_transfers.approve')
            ->name('stock-transfers.approve');
        Route::get('/stock-transfers/{stockTransfer}/packing', [StockTransferController::class, 'packing'])
            ->middleware('permission:stock_transfers.pack|stock_transfers.create')
            ->name('stock-transfers.packing');
        Route::post('/stock-transfers/{stockTransfer}/packing', [StockTransferController::class, 'pack'])
            ->middleware('permission:stock_transfers.pack|stock_transfers.create')
            ->name('stock-transfers.pack');
        Route::get('/stock-transfers/{stockTransfer}/ship', [StockTransferController::class, 'shipForm'])
            ->middleware('permission:stock_transfers.ship|stock_transfers.create')
            ->name('stock-transfers.ship-form');
        Route::post('/stock-transfers/{stockTransfer}/ship', [StockTransferController::class, 'ship'])
            ->middleware('permission:stock_transfers.ship|stock_transfers.create')
            ->name('stock-transfers.ship');
        Route::post('/stock-transfers/{stockTransfer}/complete', [StockTransferController::class, 'complete'])
            ->middleware('permission:stock_transfers.receive|stock_transfers.approve')
            ->name('stock-transfers.complete');
        Route::post('/stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])
            ->middleware('permission:stock_transfers.create|stock_transfers.approve')
            ->name('stock-transfers.cancel');
        Route::get('/stock-transfers/{stockTransfer}/print', [StockTransferController::class, 'print'])
            ->middleware('permission:stock_transfers.view')
            ->name('stock-transfers.print');
        Route::get('/stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])
            ->middleware('permission:stock_transfers.view')
            ->name('stock-transfers.show');
    });

    Route::prefix('purchasing')->name('purchasing.')->group(function (): void {
        Route::get('/requests', [PurchaseRequestController::class, 'index'])
            ->middleware('permission:purchase_orders.view|purchase_orders.create')
            ->name('requests.index');
        Route::post('/requests', [PurchaseRequestController::class, 'store'])
            ->middleware('permission:purchase_orders.create|stock.create')
            ->name('requests.store');
        Route::post('/requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])
            ->middleware('permission:purchase_orders.approve')
            ->name('requests.approve');
        Route::post('/requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])
            ->middleware('permission:purchase_orders.approve')
            ->name('requests.reject');
        Route::post('/requests/{purchaseRequest}/convert', [PurchaseRequestController::class, 'convert'])
            ->middleware('permission:purchase_orders.create')
            ->name('requests.convert');

        Route::get('/purchase-orders/export', [PurchaseOrderController::class, 'export'])
            ->middleware('permission:reports.export|purchase_orders.view')
            ->name('purchase-orders.export');
        Route::get('/purchase-orders/{purchaseOrder}/print', PurchaseOrderPrintController::class)
            ->middleware('permission:purchase_orders.view')
            ->name('purchase-orders.print');
        Route::get('/purchase-orders/{purchaseOrder}/export', [PurchaseOrderController::class, 'exportOne'])
            ->middleware('permission:purchase_orders.view')
            ->name('purchase-orders.export-one');
        Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])
            ->middleware('permission:purchase_orders.create')
            ->name('purchase-orders.submit');
        Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
            ->middleware('permission:purchase_orders.approve')
            ->name('purchase-orders.approve');
        Route::post('/purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])
            ->middleware('permission:purchase_orders.create')
            ->name('purchase-orders.send');
        Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->middleware('permission:purchase_orders.create')
            ->name('purchase-orders.cancel');
        Route::resource('purchase-orders', PurchaseOrderController::class)
            ->except(['destroy'])
            ->middlewareFor(['index', 'show'], 'permission:purchase_orders.view')
            ->middlewareFor(['create', 'store', 'edit', 'update'], 'permission:purchase_orders.create');
    });

    Route::prefix('pricing')->name('pricing.')->group(function (): void {
        Route::get('/rules', [PriceRuleController::class, 'index'])
            ->middleware('permission:prices.view')
            ->name('rules.index');
        Route::post('/rules', [PriceRuleController::class, 'store'])
            ->middleware('permission:prices.update')
            ->name('rules.store');
        Route::get('/product-prices', [ProductPriceController::class, 'index'])
            ->middleware('permission:prices.view')
            ->name('product-prices.index');
        Route::get('/product-prices/export', [ProductPriceController::class, 'export'])
            ->middleware('permission:reports.export|prices.view')
            ->name('product-prices.export');
        Route::post('/product-prices', [ProductPriceController::class, 'store'])
            ->middleware('permission:prices.update')
            ->name('product-prices.store');
        Route::get('/special-prices', [SpecialPriceController::class, 'index'])
            ->middleware('permission:prices.view')
            ->name('special-prices.index');
        Route::post('/special-prices', [SpecialPriceController::class, 'store'])
            ->middleware('permission:prices.update')
            ->name('special-prices.store');
        Route::get('/history', [PriceHistoryController::class, 'index'])
            ->middleware('permission:prices.view')
            ->name('history.index');
        Route::get('/history/export', [PriceHistoryController::class, 'export'])
            ->middleware('permission:reports.export|prices.view')
            ->name('history.export');
        Route::get('/approvals', [PriceApprovalController::class, 'index'])
            ->middleware('permission:prices.approve|approvals.view')
            ->name('approvals.index');
        Route::post('/approvals/{approval}/approve', [PriceApprovalController::class, 'approve'])
            ->middleware('permission:prices.approve')
            ->name('approvals.approve');
        Route::post('/approvals/{approval}/reject', [PriceApprovalController::class, 'reject'])
            ->middleware('permission:prices.approve')
            ->name('approvals.reject');
        Route::get('/simulator', [MarginSimulatorController::class, 'index'])
            ->middleware('permission:prices.view')
            ->name('simulator.index');
        Route::get('/hpp-history', [HppHistoryController::class, 'index'])
            ->middleware('permission:goods_receipts.view|stock.view|purchase_orders.view')
            ->name('hpp-history.index');
        Route::get('/hpp-history/export', [HppHistoryController::class, 'export'])
            ->middleware('permission:reports.export|goods_receipts.view')
            ->name('hpp-history.export');
    });

    Route::prefix('reports')->name('reports.')->group(function (): void {
        Route::get('/daily', [ReportController::class, 'show'])
            ->defaults('type', 'daily')
            ->middleware('permission:reports.view')
            ->name('daily.index');
        Route::get('/warehouse', [ReportController::class, 'show'])
            ->defaults('type', 'warehouse')
            ->middleware('permission:reports.view|stock.view')
            ->name('warehouse.index');
        Route::get('/retail', [ReportController::class, 'show'])
            ->defaults('type', 'retail')
            ->middleware('permission:reports.view|cash_shifts.view|pos.view')
            ->name('retail.index');
        Route::get('/b2b', [ReportController::class, 'show'])
            ->defaults('type', 'b2b')
            ->middleware('permission:reports.view|b2b_orders.view')
            ->name('b2b.index');
        Route::get('/pricing', [ReportController::class, 'show'])
            ->defaults('type', 'pricing')
            ->middleware('permission:reports.view|prices.view')
            ->name('pricing.index');
        Route::get('/suppliers', SupplierPerformanceController::class)
            ->middleware('permission:reports.view|suppliers.view')
            ->name('suppliers.index');
        Route::get('/losses', LossReportController::class)
            ->middleware('permission:reports.view|losses.view')
            ->name('losses.index');
        Route::get('/attendance', [AttendanceReportController::class, 'attendance'])
            ->middleware('permission:reports.view|attendance.view')
            ->name('attendance.index');
        Route::get('/shift-productivity', [AttendanceReportController::class, 'productivity'])
            ->middleware('permission:reports.view|attendance.view')
            ->name('shift-productivity.index');
        Route::get('/receivables', [ReportController::class, 'show'])
            ->defaults('type', 'receivables')
            ->middleware('permission:reports.view|receivables.view')
            ->name('receivables.index');
        Route::get('/audit-notifications', [ReportController::class, 'show'])
            ->defaults('type', 'audit_notifications')
            ->middleware('permission:reports.view|audit.view')
            ->name('audit-notifications.index');
        Route::get('/exports', [ReportExportController::class, 'index'])
            ->middleware('permission:reports.export|audit.export')
            ->name('exports.index');
        Route::post('/exports', [ReportExportController::class, 'store'])
            ->middleware('permission:reports.export|audit.export')
            ->name('exports.store');
        Route::get('/exports/{export}/download', [ReportExportController::class, 'download'])
            ->middleware('permission:reports.export|audit.export')
            ->name('exports.download');
    });

    Route::get('/returns/export', [ReturnController::class, 'export'])
        ->middleware('permission:reports.export|returns.view')
        ->name('returns.export');
    Route::get('/returns/create', [ReturnController::class, 'create'])
        ->middleware('permission:returns.create')
        ->name('returns.create');
    Route::post('/returns', [ReturnController::class, 'store'])
        ->middleware('permission:returns.create')
        ->name('returns.store');
    Route::get('/returns', [ReturnController::class, 'index'])
        ->middleware('permission:returns.view')
        ->name('returns.index');
    Route::get('/returns/{return}/inspection', [ReturnController::class, 'inspection'])
        ->middleware('permission:returns.inspect')
        ->name('returns.inspection');
    Route::post('/returns/{return}/inspection', [ReturnController::class, 'inspect'])
        ->middleware('permission:returns.inspect')
        ->name('returns.inspect');
    Route::get('/returns/{return}/approval', [ReturnController::class, 'approval'])
        ->middleware('permission:returns.view|returns.approve')
        ->name('returns.approval');
    Route::post('/returns/{return}/approve', [ReturnController::class, 'approve'])
        ->middleware('permission:returns.approve')
        ->name('returns.approve');
    Route::get('/returns/{return}/settlement', [ReturnController::class, 'settlement'])
        ->middleware('permission:returns.settle')
        ->name('returns.settlement');
    Route::post('/returns/{return}/settlement', [ReturnController::class, 'settle'])
        ->middleware('permission:returns.settle')
        ->name('returns.settle');
    Route::get('/returns/{return}', [ReturnController::class, 'show'])
        ->middleware('permission:returns.view')
        ->name('returns.show');

    Route::get('/shipments/create', [ShipmentController::class, 'create'])
        ->middleware('permission:shipments.create|b2b_orders.approve')
        ->name('shipments.create');
    Route::post('/shipments', [ShipmentController::class, 'store'])
        ->middleware('permission:shipments.create|b2b_orders.approve')
        ->name('shipments.store');
    Route::get('/shipments', [ShipmentController::class, 'index'])
        ->middleware('permission:shipments.view|b2b_orders.view')
        ->name('shipments.index');
    Route::post('/shipments/{shipment}/post', [ShipmentController::class, 'post'])
        ->middleware('permission:shipments.update|b2b_orders.approve')
        ->name('shipments.post');
    Route::get('/shipments/{shipment}/proof', [ShipmentProofController::class, 'show'])
        ->middleware('permission:shipments.update|b2b_orders.view')
        ->name('shipments.proof');
    Route::post('/shipments/{shipment}/proof', [ShipmentProofController::class, 'store'])
        ->middleware('permission:shipments.update|b2b_orders.approve')
        ->name('shipments.proof.store');
    Route::get('/shipments/{shipment}', [ShipmentController::class, 'show'])
        ->middleware('permission:shipments.view|b2b_orders.view')
        ->name('shipments.show');

    Route::prefix('retail')->name('retail.')->group(function (): void {
        Route::get('/dashboard', RetailDashboardController::class)
            ->middleware('permission:dashboard.view|cash_shifts.view|reports.view')
            ->name('dashboard');

        Route::get('/receivables', [ReceivableController::class, 'retail'])
            ->middleware('permission:receivables.view')
            ->name('receivables.index');

        Route::get('/shifts/open', [CashShiftController::class, 'open'])
            ->middleware('permission:cash_shifts.create')
            ->name('shifts.open');
        Route::post('/shifts/open', [CashShiftController::class, 'store'])
            ->middleware('permission:cash_shifts.create')
            ->name('shifts.store');
        Route::get('/shifts/current', [CashShiftController::class, 'current'])
            ->middleware('permission:cash_shifts.view')
            ->name('shifts.current');
        Route::get('/shifts/export', [CashShiftController::class, 'export'])
            ->middleware('permission:reports.export|cash_shifts.view')
            ->name('shifts.export');
        Route::get('/shifts', [CashShiftController::class, 'index'])
            ->middleware('permission:cash_shifts.view')
            ->name('shifts.index');
        Route::get('/shifts/{shift}/expenses', [CashShiftController::class, 'expenses'])
            ->middleware('permission:cash_shifts.create')
            ->name('shifts.expenses');
        Route::post('/shifts/{shift}/expenses', [CashShiftController::class, 'storeExpense'])
            ->middleware('permission:cash_shifts.create')
            ->name('shifts.expenses.store');
        Route::get('/shifts/{shift}/close', [CashShiftController::class, 'close'])
            ->middleware('permission:cash_shifts.create')
            ->name('shifts.close');
        Route::post('/shifts/{shift}/close', [CashShiftController::class, 'submitClose'])
            ->middleware('permission:cash_shifts.create')
            ->name('shifts.close.submit');
        Route::get('/shifts/{shift}/approval', [CashShiftController::class, 'approval'])
            ->middleware('permission:cash_shifts.approve')
            ->name('shifts.approval');
        Route::post('/shifts/{shift}/approve', [CashShiftController::class, 'approve'])
            ->middleware('permission:cash_shifts.approve')
            ->name('shifts.approve');
        Route::post('/shifts/{shift}/reject', [CashShiftController::class, 'reject'])
            ->middleware('permission:cash_shifts.approve')
            ->name('shifts.reject');
        Route::get('/shifts/{shift}/report', [CashShiftController::class, 'report'])
            ->middleware('permission:cash_shifts.view')
            ->name('shifts.report');
        Route::get('/pos', [PosController::class, 'index'])
            ->middleware('permission:pos.view')
            ->name('pos.index');
        Route::post('/pos', [PosController::class, 'store'])
            ->middleware('permission:pos.create')
            ->name('pos.store');
        Route::get('/pos/checkout', [PosController::class, 'checkout'])
            ->middleware('permission:pos.create')
            ->name('pos.checkout');
        Route::get('/pos/holds', [PosController::class, 'holds'])
            ->middleware('permission:pos.create')
            ->name('pos.holds');
        Route::post('/pos/holds', [PosController::class, 'storeHold'])
            ->middleware('permission:pos.create')
            ->name('pos.holds.store');
        Route::post('/pos/holds/{hold}/resume', [PosController::class, 'resumeHold'])
            ->middleware('permission:pos.create')
            ->name('pos.holds.resume');
        Route::post('/pos/holds/{hold}/cancel', [PosController::class, 'cancelHold'])
            ->middleware('permission:pos.create')
            ->name('pos.holds.cancel');
        Route::get('/sales/{sale}', [PosSaleController::class, 'show'])
            ->middleware('permission:pos.view')
            ->name('sales.show');
        Route::get('/sales/{sale}/print', [PosSaleController::class, 'print'])
            ->middleware('permission:pos.view')
            ->name('sales.print');
        Route::get('/sales/{sale}/void', [PosSaleController::class, 'voidForm'])
            ->middleware('permission:pos.void')
            ->name('sales.void');
        Route::post('/sales/{sale}/void', [PosSaleController::class, 'void'])
            ->middleware('permission:pos.void')
            ->name('sales.void.store');
        Route::get('/sales/{sale}/return', [PosSaleController::class, 'returnForm'])
            ->middleware('permission:returns.create|pos.void')
            ->name('sales.return');
        Route::post('/sales/{sale}/return', [PosSaleController::class, 'return'])
            ->middleware('permission:returns.create|pos.void')
            ->name('sales.return.store');
        Route::get('/restock-requests', [RestockRequestController::class, 'index'])
            ->middleware('permission:stock_transfers.view|stock_transfers.create')
            ->name('restock-requests.index');
        Route::post('/restock-requests', [RestockRequestController::class, 'store'])
            ->middleware('permission:stock_transfers.create')
            ->name('restock-requests.store');
        Route::post('/restock-requests/{restockRequest}/approve', [RestockRequestController::class, 'approve'])
            ->middleware('permission:stock_transfers.approve')
            ->name('restock-requests.approve');
        Route::post('/restock-requests/{restockRequest}/reject', [RestockRequestController::class, 'reject'])
            ->middleware('permission:stock_transfers.approve')
            ->name('restock-requests.reject');
        Route::post('/restock-requests/{restockRequest}/convert', [RestockRequestController::class, 'convert'])
            ->middleware('permission:stock_transfers.approve')
            ->name('restock-requests.convert');
        Route::get('/stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receiveForm'])
            ->middleware('permission:stock_transfers.receive')
            ->name('stock-transfers.receive-form');
        Route::post('/stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive'])
            ->middleware('permission:stock_transfers.receive')
            ->name('stock-transfers.receive');
    });

    Route::get('/warehouse/losses', [InventoryLossController::class, 'index'])
        ->middleware('permission:losses.view')
        ->name('warehouse.losses.index');
    Route::post('/warehouse/losses', [InventoryLossController::class, 'store'])
        ->middleware('permission:losses.create')
        ->name('warehouse.losses.store');
    Route::post('/warehouse/losses/{loss}/approve', [InventoryLossController::class, 'approve'])
        ->middleware('permission:returns.approve')
        ->name('warehouse.losses.approve');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::get('/system/health', HealthController::class)
    ->middleware('health.access')
    ->name('system.health');
