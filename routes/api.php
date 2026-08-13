<?php

use App\Http\Controllers\Api\AppUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CafeBranchController;
use App\Http\Controllers\Api\CafeController;
use App\Http\Controllers\Api\CafeDashboardController;
use App\Http\Controllers\Api\CafeMobileController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CartItemController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeliveryZoneController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderItemController;
use App\Http\Controllers\Api\OrderStatusLogController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseOrderItemController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UserTypeController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| Authentication endpoints and read-only storefront endpoints are open.
| All other API routes require a valid Sanctum bearer token.
|
*/

Route::post('login', [AuthController::class, 'login']);

Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);
Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{category}', [CategoryController::class, 'show']);

Route::middleware(['auth:sanctum', 'permission'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::post('register', [AuthController::class, 'register']);
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('cafe/dashboard', [CafeDashboardController::class, 'index'])->name('cafe.dashboard');

    Route::prefix('cafe')->name('cafe.')->group(function () {
        Route::get('profile', [CafeMobileController::class, 'profile'])->name('profile');
        Route::put('profile', [CafeMobileController::class, 'updateProfile'])->name('profile.update');
        Route::get('orders', [CafeMobileController::class, 'orders'])->name('orders.index');
        Route::post('orders', [CafeMobileController::class, 'storeOrder'])->name('orders.store');
        Route::get('orders/{id}', [CafeMobileController::class, 'showOrder'])->name('orders.show');
        Route::put('orders/{id}/status', [CafeMobileController::class, 'updateOrderStatus'])->name('orders.status');
        Route::get('branches', [CafeMobileController::class, 'branches'])->name('branches.index');
        Route::post('branches', [CafeMobileController::class, 'storeBranch'])->name('branches.store');
        Route::get('branches/{id}', [CafeMobileController::class, 'showBranch'])->name('branches.show');
        Route::get('branches/{id}/orders', [CafeMobileController::class, 'branchOrders'])->name('branches.orders');
        Route::put('branches/{id}', [CafeMobileController::class, 'updateBranch'])->name('branches.update');
        Route::get('categories', [CafeMobileController::class, 'categories'])->name('categories.index');
        Route::get('products', [CafeMobileController::class, 'products'])->name('products.index');
        Route::get('products/{id}', [CafeMobileController::class, 'showProduct'])->name('products.show');
        Route::get('products/{id}/variants', [CafeMobileController::class, 'productVariants'])->name('products.variants');
    });

    Route::apiResources([
        'activity-logs' => ActivityLogController::class,
        'user-types' => UserTypeController::class,
        'permissions' => PermissionController::class,
        'cafes' => CafeController::class,
        'cafe-branches' => CafeBranchController::class,
        'delivery-zones' => DeliveryZoneController::class,
        'suppliers' => SupplierController::class,
        'product-variants' => ProductVariantController::class,
        'product-images' => ProductImageController::class,
        'warehouses' => WarehouseController::class,
        'inventory' => InventoryController::class,
        'users' => AppUserController::class,
        'carts' => CartController::class,
        'cart-items' => CartItemController::class,
        'orders' => OrderController::class,
        'order-items' => OrderItemController::class,
        'order-status-logs' => OrderStatusLogController::class,
        'payments' => PaymentController::class,
        'purchase-orders' => PurchaseOrderController::class,
        'purchase-order-items' => PurchaseOrderItemController::class,
    ]);

    // Write endpoints for the storefront resources are protected;
    // index/show remain public above.
    Route::apiResource('products', ProductController::class)->except(['index', 'show']);
    Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
});
