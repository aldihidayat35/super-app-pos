<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\InventoryLoss;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductCostHistory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RestockRequest;
use App\Models\ReturnDocument;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\StockMutation;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\SupplierScore;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Policies\BranchPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\GoodsReceiptPolicy;
use App\Policies\InventoryLossPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\ProductBrandPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductCostHistoryPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseRequestPolicy;
use App\Policies\RestockRequestPolicy;
use App\Policies\ReturnDocumentPolicy;
use App\Policies\RolePolicy;
use App\Policies\StockBatchPolicy;
use App\Policies\StockMutationPolicy;
use App\Policies\StockOpnamePolicy;
use App\Policies\StockPolicy;
use App\Policies\StockTransferPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\SupplierScorePolicy;
use App\Policies\SystemSettingPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use App\Policies\WarehouseLocationPolicy;
use App\Policies\WarehousePolicy;
use App\Policies\WorkLocationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn (User $user, string $ability): ?bool => $user->hasRole('super_admin') ? true : null);

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);
        Gate::policy(ProductBrand::class, ProductBrandPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(PurchaseRequest::class, PurchaseRequestPolicy::class);
        Gate::policy(GoodsReceipt::class, GoodsReceiptPolicy::class);
        Gate::policy(ProductCostHistory::class, ProductCostHistoryPolicy::class);
        Gate::policy(SupplierScore::class, SupplierScorePolicy::class);
        Gate::policy(RestockRequest::class, RestockRequestPolicy::class);
        Gate::policy(ReturnDocument::class, ReturnDocumentPolicy::class);
        Gate::policy(InventoryLoss::class, InventoryLossPolicy::class);
        Gate::policy(StockTransfer::class, StockTransferPolicy::class);
        Gate::policy(StockOpname::class, StockOpnamePolicy::class);
        Gate::policy(WarehouseLocation::class, WarehouseLocationPolicy::class);
        Gate::policy(Stock::class, StockPolicy::class);
        Gate::policy(StockMutation::class, StockMutationPolicy::class);
        Gate::policy(StockBatch::class, StockBatchPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(SystemSetting::class, SystemSettingPolicy::class);
        Gate::policy(WorkLocation::class, WorkLocationPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
    }
}
