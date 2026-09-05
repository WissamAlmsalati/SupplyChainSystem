<?php

use App\Http\Controllers\Api\AppUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CafeBranchController;
use App\Http\Controllers\Api\CafeController;
use App\Http\Controllers\Api\CafeRegistrationController;
use App\Http\Controllers\Api\CafeDashboardController;
use App\Http\Controllers\Api\CafeMobileController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CartItemController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DelegateController;
use App\Http\Controllers\Api\DelegateMobileController;
use App\Http\Controllers\Api\DeliveryZoneController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderItemController;
use App\Http\Controllers\Api\OrderStatusLogController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PremiumFeatureController;
use App\Models\PremiumFeature;
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
| API v1 Routes
|--------------------------------------------------------------------------
|
| Authentication endpoints and read-only storefront endpoints are open.
| All other API routes require a valid Sanctum bearer token.
|
*/

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('cafe/register', [AuthController::class, 'registerCafe'])->name('cafe.register');
    Route::post('cafe/forgot-password', [PasswordResetController::class, 'sendOtp'])->name('cafe.forgot-password');
    Route::post('cafe/reset-password', [PasswordResetController::class, 'resetPassword'])->name('cafe.reset-password');

    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);

    Route::middleware(['auth:sanctum', 'permission'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('register', [AuthController::class, 'register']);
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('cafe/dashboard', [CafeDashboardController::class, 'index'])->middleware('cafe.ready')->name('cafe.dashboard');
        Route::get('premium-features', [PremiumFeatureController::class, 'index'])->name('premium-features');
        Route::put('premium-features/{premiumFeature}', [PremiumFeatureController::class, 'update'])->name('premium-features.update');

        Route::prefix('cafe')->name('cafe.')->group(function () {
            // Profile endpoints stay reachable before the cafe exists / is approved.
            Route::get('profile', [CafeMobileController::class, 'profile'])->name('profile');
            Route::post('profile', [CafeMobileController::class, 'storeCafe'])->name('profile.store');
            Route::put('profile', [CafeMobileController::class, 'updateProfile'])->name('profile.update');
        });

        Route::prefix('cafe')->name('cafe.')->middleware('cafe.ready')->group(function () {
            Route::get('orders', [CafeMobileController::class, 'orders'])->name('orders.index');
            Route::post('orders', [CafeMobileController::class, 'storeOrder'])->name('orders.store');
            Route::get('orders/{id}', [CafeMobileController::class, 'showOrder'])->name('orders.show');
            Route::put('orders/{id}/status', [CafeMobileController::class, 'updateOrderStatus'])->name('orders.status');
            Route::post('orders/{id}/cancel-request', [CafeMobileController::class, 'requestCancellation'])->name('orders.cancel-request');
            Route::get('branches', [CafeMobileController::class, 'branches'])->name('branches.index');
            Route::post('branches', [CafeMobileController::class, 'storeBranch'])->name('branches.store');
            Route::get('branches/{id}', [CafeMobileController::class, 'showBranch'])->name('branches.show');
            Route::get('branches/{id}/orders', [CafeMobileController::class, 'branchOrders'])->name('branches.orders');
            Route::put('branches/{id}', [CafeMobileController::class, 'updateBranch'])->name('branches.update');
            Route::delete('branches/{id}', [CafeMobileController::class, 'destroyBranch'])->name('branches.destroy');
            Route::get('delivery-zones', [CafeMobileController::class, 'deliveryZones'])->name('delivery-zones.index');
            Route::get('categories', [CafeMobileController::class, 'categories'])->name('categories.index');
            Route::get('products', [CafeMobileController::class, 'products'])->name('products.index');
            Route::get('products/{id}', [CafeMobileController::class, 'showProduct'])->name('products.show');
            Route::get('products/{id}/variants', [CafeMobileController::class, 'productVariants'])->name('products.variants');

            Route::get('cart', [CafeMobileController::class, 'cart'])->name('cart');
            Route::post('cart/items', [CafeMobileController::class, 'addCartItem'])->name('cart.items.store');
            Route::put('cart/items/{id}', [CafeMobileController::class, 'updateCartItem'])->name('cart.items.update');
            Route::delete('cart/items/{id}', [CafeMobileController::class, 'removeCartItem'])->name('cart.items.destroy');
            Route::delete('cart', [CafeMobileController::class, 'clearCart'])->name('cart.clear');
            Route::post('cart/checkout', [CafeMobileController::class, 'checkout'])->name('cart.checkout');

            Route::get('orders/{id}/delegate', [CafeMobileController::class, 'orderDelegate'])->name('orders.delegate');
        });

        Route::prefix('delegate')->name('delegate.')->group(function () {
            Route::post('location', [DelegateMobileController::class, 'updateLocation'])->name('location');
            Route::post('availability', [DelegateMobileController::class, 'setAvailability'])->name('availability');
            Route::get('orders', [DelegateMobileController::class, 'myOrders'])->name('orders');
            Route::get('orders/{id}', [DelegateMobileController::class, 'showOrder'])->name('orders.show');
            Route::post('orders/{id}/status', [DelegateMobileController::class, 'updateOrderStatus'])->name('orders.status');
        });

        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::put('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
        Route::apiResource('notifications', NotificationController::class)->only(['index', 'destroy']);
        Route::put('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

        Route::apiResources([
            'activity-logs' => ActivityLogController::class,
            'user-types' => UserTypeController::class,
            'permissions' => PermissionController::class,
            'delegates' => DelegateController::class,
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

        Route::post('warehouses/{warehouse}/expand-hex', [WarehouseController::class, 'expandHex'])->name('warehouses.expand-hex');
        Route::post('delegates/{delegate}/toggle-active', [DelegateController::class, 'toggleActive'])->name('delegates.toggle-active');
        Route::put('delegates/{delegate}/location', [DelegateController::class, 'updateLocation'])->name('delegates.location');

        // Write endpoints for the storefront resources are protected;
        // index/show remain public above.
        Route::apiResource('products', ProductController::class)->except(['index', 'show']);
        Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);

        Route::post('orders/{order}/assign-delegate', [OrderController::class, 'assignDelegate'])->name('orders.assign-delegate');

        Route::prefix('cafe-registrations')->name('cafe-registrations.')->group(function () {
            Route::get('pending', [CafeRegistrationController::class, 'pending'])->name('pending');
            Route::post('{cafe}/approve', [CafeRegistrationController::class, 'approve'])->name('approve');
            Route::post('{cafe}/reject', [CafeRegistrationController::class, 'reject'])->name('reject');
        });
    });
});
