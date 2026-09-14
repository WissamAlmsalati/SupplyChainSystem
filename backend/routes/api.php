<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AppUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CafeDashboardController;
use App\Http\Controllers\Api\CafeMobileController;
use App\Http\Controllers\Api\CafeWalletController;
use App\Http\Controllers\Api\CustodyController;
use App\Http\Controllers\Api\DelegateCustodyController;
use App\Http\Controllers\Api\DelegateWalletController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WalletGatewayController;
use App\Http\Controllers\Api\WalletTopupController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CartItemController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DelegateController;
use App\Http\Controllers\Api\DelegateMobileController;
use App\Http\Controllers\Api\DeliveryZoneController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\FeaturedSectionController;
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
use App\Http\Controllers\Api\PromoController;
use App\Http\Controllers\Api\RecurringCartController;
use App\Http\Controllers\Api\StockMovementController;
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
    Route::post('cafe/verify-otp', [AuthController::class, 'verifyRegistrationOtp'])->name('cafe.verify-otp');
    Route::post('cafe/resend-otp', [AuthController::class, 'resendRegistrationOtp'])->name('cafe.resend-otp');
    Route::post('cafe/forgot-password', [PasswordResetController::class, 'sendOtp'])->name('cafe.forgot-password');
    Route::post('cafe/reset-password', [PasswordResetController::class, 'resetPassword'])->name('cafe.reset-password');

    // Payment gateway: signed provider callback and the sandbox checkout page.
    Route::post('wallet/gateway/callback', [WalletGatewayController::class, 'callback'])->name('wallet.gateway.callback');
    Route::get('wallet/gateway/sandbox/{token}', [WalletGatewayController::class, 'sandbox'])->name('wallet.gateway.sandbox');

    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);

    Route::middleware(['auth:sanctum', 'permission'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('register', [AuthController::class, 'register']);
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/monthly/{year}/{month}', [DashboardController::class, 'monthlyStats'])->name('dashboard.monthly');
        Route::get('cafe/dashboard', [CafeDashboardController::class, 'index'])->name('cafe.dashboard');
        Route::get('premium-features', [PremiumFeatureController::class, 'index'])->name('premium-features');
        Route::put('premium-features/{premiumFeature}', [PremiumFeatureController::class, 'update'])->name('premium-features.update');

        Route::prefix('cafe')->name('cafe.')->group(function () {
            // Profile endpoints stay reachable for every authenticated cafe user.
            Route::get('profile', [CafeMobileController::class, 'profile'])->name('profile');
            Route::put('profile', [CafeMobileController::class, 'updateProfile'])->name('profile.update');
            // Read-only feature flags for the cafe app UI (addresses toggle, etc.)
            Route::get('premium-features', [PremiumFeatureController::class, 'index'])->name('premium-features');
        });

        Route::prefix('cafe')->name('cafe.')->group(function () {
            Route::get('orders', [CafeMobileController::class, 'orders'])->name('orders.index');
            Route::post('orders', [CafeMobileController::class, 'storeOrder'])->name('orders.store');
            Route::get('orders/{id}', [CafeMobileController::class, 'showOrder'])->name('orders.show');
            Route::put('orders/{id}/status', [CafeMobileController::class, 'updateOrderStatus'])->name('orders.status');
            Route::post('orders/{id}/cancel-request', [CafeMobileController::class, 'requestCancellation'])->name('orders.cancel-request');
            Route::get('addresses', [CafeMobileController::class, 'addresses'])->name('addresses.index');
            Route::post('addresses', [CafeMobileController::class, 'storeAddress'])->name('addresses.store');
            Route::get('addresses/{id}', [CafeMobileController::class, 'showAddress'])->name('addresses.show');
            Route::get('addresses/{id}/orders', [CafeMobileController::class, 'addressOrders'])->name('addresses.orders');
            Route::put('addresses/{id}', [CafeMobileController::class, 'updateAddress'])->name('addresses.update');
            Route::delete('addresses/{id}', [CafeMobileController::class, 'destroyAddress'])->name('addresses.destroy');
            Route::get('delivery-zones', [CafeMobileController::class, 'deliveryZones'])->name('delivery-zones.index');
            Route::get('categories', [CafeMobileController::class, 'categories'])->name('categories.index');
            Route::get('products', [CafeMobileController::class, 'products'])->name('products.index');
            Route::get('products/filters', [CafeMobileController::class, 'productFilters'])->name('products.filters');
            Route::get('featured-sections', [CafeMobileController::class, 'featuredSections'])->name('featured-sections.index');
            Route::get('featured-sections/{id}/products', [CafeMobileController::class, 'featuredSectionProducts'])->name('featured-sections.products');
            Route::get('favorites', [FavoriteController::class, 'index'])->name('favorites.index');
            Route::get('favorites/ids', [FavoriteController::class, 'ids'])->name('favorites.ids');
            Route::post('favorites', [FavoriteController::class, 'store'])->name('favorites.store');
            Route::delete('favorites/{productId}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
            Route::get('products/{id}', [CafeMobileController::class, 'showProduct'])->name('products.show');
            Route::get('products/{id}/variants', [CafeMobileController::class, 'productVariants'])->name('products.variants');

            Route::get('cart', [CafeMobileController::class, 'cart'])->name('cart');
            Route::get('cart/check-stock', [CafeMobileController::class, 'checkStock'])->name('cart.check-stock');
            Route::post('cart/items', [CafeMobileController::class, 'addCartItem'])->name('cart.items.store');
            Route::put('cart/items/{id}', [CafeMobileController::class, 'updateCartItem'])->name('cart.items.update');
            Route::delete('cart/items/{id}', [CafeMobileController::class, 'removeCartItem'])->name('cart.items.destroy');
            Route::delete('cart', [CafeMobileController::class, 'clearCart'])->name('cart.clear');
            Route::post('cart/checkout', [CafeMobileController::class, 'checkout'])->name('cart.checkout');

            Route::get('wallet', [CafeWalletController::class, 'show'])->name('wallet.show');
            Route::get('wallet/transactions', [CafeWalletController::class, 'transactions'])->name('wallet.transactions');
            Route::get('wallet/topups', [CafeWalletController::class, 'topups'])->name('wallet.topups.index');
            Route::post('wallet/topups', [CafeWalletController::class, 'storeTopup'])->name('wallet.topups.store');
            Route::post('wallet/topups/gateway', [CafeWalletController::class, 'gatewayTopup'])->name('wallet.topups.gateway');
            Route::post('wallet/topups/{id}/cancel', [CafeWalletController::class, 'cancelTopup'])->name('wallet.topups.cancel');

            Route::get('recurring-carts', [RecurringCartController::class, 'index'])->name('recurring-carts.index');
            Route::post('recurring-carts', [RecurringCartController::class, 'store'])->name('recurring-carts.store');
            Route::get('recurring-carts/{id}', [RecurringCartController::class, 'show'])->name('recurring-carts.show');
            Route::put('recurring-carts/{id}', [RecurringCartController::class, 'update'])->name('recurring-carts.update');
            Route::delete('recurring-carts/{id}', [RecurringCartController::class, 'destroy'])->name('recurring-carts.destroy');
            Route::post('recurring-carts/{id}/order', [RecurringCartController::class, 'order'])->name('recurring-carts.order');

            Route::get('orders/{id}/delegate', [CafeMobileController::class, 'orderDelegate'])->name('orders.delegate');
            Route::get('promos', [PromoController::class, 'active'])->name('promos.index');
        });

        Route::prefix('delegate')->name('delegate.')->group(function () {
            Route::post('location', [DelegateMobileController::class, 'updateLocation'])->name('location');
            Route::post('availability', [DelegateMobileController::class, 'setAvailability'])->name('availability');
            Route::get('orders', [DelegateMobileController::class, 'myOrders'])->name('orders');
            Route::get('orders/{id}', [DelegateMobileController::class, 'showOrder'])->name('orders.show');
            Route::post('orders/{id}/status', [DelegateMobileController::class, 'updateOrderStatus'])->name('orders.status');
            Route::post('wallet/collect', [DelegateWalletController::class, 'collect'])->name('wallet.collect');
            Route::get('wallet/collections', [DelegateWalletController::class, 'collections'])->name('wallet.collections');
            Route::get('custody', [DelegateCustodyController::class, 'show'])->name('custody.show');
            Route::get('custody/settlements', [DelegateCustodyController::class, 'settlements'])->name('custody.settlements');
        });

        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::put('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
        Route::apiResource('notifications', NotificationController::class)->only(['index', 'show', 'destroy']);
        Route::put('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

        Route::apiResources([
            'user-types' => UserTypeController::class,
            'permissions' => PermissionController::class,
            'delegates' => DelegateController::class,
            'addresses' => AddressController::class,
            'delivery-zones' => DeliveryZoneController::class,
            'product-variants' => ProductVariantController::class,
            'product-images' => ProductImageController::class,
            'warehouses' => WarehouseController::class,
            'inventory' => InventoryController::class,
            'users' => AppUserController::class,
            'orders' => OrderController::class,
            'payments' => PaymentController::class,
            'promos' => PromoController::class,
        ]);

        Route::apiResource('activity-logs', ActivityLogController::class)->only(['index']);

        // Read-only: carts, order lines and status logs are written by their workflows.
        Route::apiResource('carts', CartController::class)->only(['index', 'show']);
        Route::apiResource('cart-items', CartItemController::class)->only(['index', 'show']);
        Route::apiResource('order-items', OrderItemController::class)->only(['index', 'show']);
        Route::apiResource('order-status-logs', OrderStatusLogController::class)->only(['index', 'show']);
        Route::apiResource('stock-movements', StockMovementController::class)->only(['index', 'show']);

        Route::post('featured-sections/reorder', [FeaturedSectionController::class, 'reorder'])->name('featured-sections.reorder');
        Route::post('featured-sections/preview', [FeaturedSectionController::class, 'preview'])->name('featured-sections.preview');
        Route::apiResource('featured-sections', FeaturedSectionController::class);

        Route::get('custody', [CustodyController::class, 'index'])->name('custody.index');
        Route::get('custody/{delegate}', [CustodyController::class, 'show'])->name('custody.show');
        Route::get('custody/{delegate}/entries', [CustodyController::class, 'entries'])->name('custody.entries');
        Route::post('custody/{delegate}/settle', [CustodyController::class, 'settle'])->name('custody.settle');
        Route::post('custody/{delegate}/adjust', [CustodyController::class, 'adjust'])->name('custody.adjust');

        Route::get('wallets/summary', [WalletController::class, 'summary'])->name('wallets.summary');
        Route::apiResource('wallets', WalletController::class)->only(['index', 'show']);
        Route::get('wallets/{wallet}/transactions', [WalletController::class, 'transactions'])->name('wallets.transactions');
        Route::post('wallets/{wallet}/adjust', [WalletController::class, 'adjust'])->name('wallets.adjust');
        Route::post('wallets/{wallet}/toggle-active', [WalletController::class, 'toggleActive'])->name('wallets.toggle-active');
        Route::apiResource('wallet-topups', WalletTopupController::class)->only(['index', 'show']);
        Route::post('wallet-topups/{wallet_topup}/approve', [WalletTopupController::class, 'approve'])->name('wallet-topups.approve');
        Route::post('wallet-topups/{wallet_topup}/reject', [WalletTopupController::class, 'reject'])->name('wallet-topups.reject');


        Route::post('warehouses/{warehouse}/expand-hex', [WarehouseController::class, 'expandHex'])->name('warehouses.expand-hex');
        Route::post('delegates/{delegate}/toggle-active', [DelegateController::class, 'toggleActive'])->name('delegates.toggle-active');
        Route::put('delegates/{delegate}/location', [DelegateController::class, 'updateLocation'])->name('delegates.location');

        // Write endpoints for the storefront resources are protected;
        // index/show remain public above.
        Route::apiResource('products', ProductController::class)->except(['index', 'show']);
        Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);

        Route::post('orders/{order}/assign-delegate', [OrderController::class, 'assignDelegate'])->name('orders.assign-delegate');
    });
});
