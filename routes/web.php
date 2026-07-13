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
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordConfirmationController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Pricing\HppHistoryController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Purchasing\PurchaseOrderPrintController;
use App\Http\Controllers\Purchasing\PurchaseRequestController;
use App\Http\Controllers\Reports\LossReportController;
use App\Http\Controllers\Reports\SupplierPerformanceController;
use App\Http\Controllers\Retail\RestockRequestController;
use App\Http\Controllers\Returns\InventoryLossController;
use App\Http\Controllers\Returns\ReturnController;
use App\Http\Controllers\System\HealthController;
use App\Http\Controllers\Warehouse\GoodsReceiptController;
use App\Http\Controllers\Warehouse\LocationTransferController;
use App\Http\Controllers\Warehouse\StockBatchController;
use App\Http\Controllers\Warehouse\StockCardController;
use App\Http\Controllers\Warehouse\StockController;
use App\Http\Controllers\Warehouse\StockMutationController;
use App\Http\Controllers\Warehouse\StockOpnameController;
use App\Http\Controllers\Warehouse\StockTransferController;
use App\Http\Controllers\Warehouse\WarehouseDashboardController;
use App\Http\Controllers\Warehouse\WarehouseLocationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware(['auth', 'active.user', 'work.location'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

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
        Route::get('/hpp-history', [HppHistoryController::class, 'index'])
            ->middleware('permission:goods_receipts.view|stock.view|purchase_orders.view')
            ->name('hpp-history.index');
        Route::get('/hpp-history/export', [HppHistoryController::class, 'export'])
            ->middleware('permission:reports.export|goods_receipts.view')
            ->name('hpp-history.export');
    });

    Route::prefix('reports')->name('reports.')->group(function (): void {
        Route::get('/suppliers', SupplierPerformanceController::class)
            ->middleware('permission:reports.view|suppliers.view')
            ->name('suppliers.index');
        Route::get('/losses', LossReportController::class)
            ->middleware('permission:reports.view|losses.view')
            ->name('losses.index');
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

    Route::prefix('retail')->name('retail.')->group(function (): void {
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
