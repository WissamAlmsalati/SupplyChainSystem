<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AddressImageController;
use App\Http\Controllers\Api\AppUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CafeProfileImageController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CartItemController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustodyController;
use App\Http\Controllers\Api\CustomerDashboardController;
use App\Http\Controllers\Api\CustomerMobileController;
use App\Http\Controllers\Api\CustomerWalletController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DelegateController;
use App\Http\Controllers\Api\DelegateCustodyController;
use App\Http\Controllers\Api\DelegateMobileController;
use App\Http\Controllers\Api\DelegateWalletController;
use App\Http\Controllers\Api\DeliveryZoneController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\FeaturedSectionController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderItemController;
use App\Http\Controllers\Api\OrderStatusLogController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PlaceholderController;
use App\Http\Controllers\Api\PremiumFeatureController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\PromoController;
use App\Http\Controllers\Api\RecurringCartController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\UserTypeController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WalletGatewayController;
use App\Http\Controllers\Api\WalletTopupController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| Updates are PATCH: each one changes some fields and leaves the rest alone,
| which is not what PUT means. PUT stays accepted beside it for older clients.
|
| Authentication endpoints and read-only storefront endpoints are open.
| All other API routes require a valid Sanctum bearer token.
|
*/

// What every app needs about the signed-in user, registered under each app's
// own prefix so an app's whole surface reads /api/v1/{app}/...: the customer
// app never has to call a path outside /customer/, nor the delegate app one
// outside /delegate/. The flat /me, /logout and /notifications stay for the
// dashboard and for clients already deployed.
$account = function () {
    Route::get('me', [AuthController::class, 'me'])->name('me');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    // The FCM token of this install, so there is somewhere to push to.
    Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::delete('devices', [DeviceController::class, 'destroy'])->name('devices.destroy');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::match(['patch', 'put'], 'notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::apiResource('notifications', NotificationController::class)->only(['index', 'show', 'destroy']);
    Route::match(['patch', 'put'], 'notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
};

Route::prefix('v1')->middleware('throttle:api')->group(function () use ($account) {
    // One action, a door per app. `roles` tells it which user types to search,
    // because a phone number is only unique within a type — the same number can
    // be a customer and a delegate, and a lookup without the type is a coin toss.
    // Plain /login stays as an unscoped alias for clients not yet moved over.
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('customer/login', [AuthController::class, 'login'])->defaults('roles', ['customer'])->middleware('throttle:auth')->name('customer.login');
    Route::post('delegate/login', [AuthController::class, 'login'])->defaults('roles', ['delegate'])->middleware('throttle:auth')->name('delegate.login');
    Route::post('admin/login', [AuthController::class, 'login'])->defaults('roles', ['admin', 'super_admin'])->middleware('throttle:auth')->name('admin.login');
    Route::get('placeholder/{kind}', [PlaceholderController::class, 'show'])->name('placeholder');
    // OTP endpoints share the "otp" limiter: 6-digit codes must not be guessable.
    Route::middleware('throttle:otp')->group(function () {
        Route::post('customer/register', [AuthController::class, 'registerCustomer'])->name('customer.register');
        Route::post('customer/verify-otp', [AuthController::class, 'verifyRegistrationOtp'])->name('customer.verify-otp');
        Route::post('customer/resend-otp', [AuthController::class, 'resendRegistrationOtp'])->name('customer.resend-otp');
        Route::post('customer/forgot-password', [PasswordResetController::class, 'sendOtp'])->name('customer.forgot-password');
        Route::post('customer/reset-password', [PasswordResetController::class, 'resetPassword'])->name('customer.reset-password');
    });

    // Payment gateway: signed provider callback and the sandbox checkout page.
    Route::post('wallet/gateway/callback', [WalletGatewayController::class, 'callback'])->name('wallet.gateway.callback');
    Route::get('wallet/gateway/sandbox/{token}', [WalletGatewayController::class, 'sandbox'])->name('wallet.gateway.sandbox');

    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);

    // 'idempotent' acts only on a POST that carries an Idempotency-Key header.
    Route::middleware(['auth:sanctum', 'permission', 'idempotent'])->group(function () use ($account) {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('register', [AuthController::class, 'register']);
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        // No module code: the controller decides group by group, and refuses the app roles.
        Route::get('search', SearchController::class)->name('search');
        Route::get('dashboard/monthly/{year}/{month}', [DashboardController::class, 'monthlyStats'])->name('dashboard.monthly');
        Route::get('customer/dashboard', [CustomerDashboardController::class, 'index'])->name('customer.dashboard');
        Route::get('premium-features', [PremiumFeatureController::class, 'index'])->name('premium-features');
        Route::match(['patch', 'put'], 'premium-features/{premiumFeature}', [PremiumFeatureController::class, 'update'])->name('premium-features.update');

        Route::prefix('customer')->name('customer.')->group(function () use ($account) {
            $account();

            // Profile endpoints stay reachable for every authenticated customer user.
            Route::get('profile', [CustomerMobileController::class, 'profile'])->name('profile');
            Route::match(['patch', 'put'], 'profile', [CustomerMobileController::class, 'updateProfile'])->name('profile.update');
            // Pictures of the cafe itself. Scoped to the signed-in account, so
            // these sit with the other profile routes rather than under a code.
            Route::post('profile/images', [CafeProfileImageController::class, 'store'])->name('profile.images.store');
            Route::match(['patch', 'put'], 'profile/images/{imageId}', [CafeProfileImageController::class, 'update'])->name('profile.images.update');
            Route::delete('profile/images/{imageId}', [CafeProfileImageController::class, 'destroy'])->name('profile.images.destroy');
            // Read-only feature flags for the customer app UI (addresses toggle, etc.)
            Route::get('premium-features', [PremiumFeatureController::class, 'index'])->name('premium-features');
        });

        Route::prefix('customer')->name('customer.')->group(function () {
            Route::get('orders', [CustomerMobileController::class, 'orders'])->name('orders.index');
            Route::post('orders', [CustomerMobileController::class, 'storeOrder'])->name('orders.store');
            Route::get('orders/{id}', [CustomerMobileController::class, 'showOrder'])->name('orders.show');
            Route::match(['patch', 'put'], 'orders/{id}/status', [CustomerMobileController::class, 'updateOrderStatus'])->name('orders.status');
            Route::post('orders/{id}/cancel-request', [CustomerMobileController::class, 'requestCancellation'])->name('orders.cancel-request');
            Route::get('addresses', [CustomerMobileController::class, 'addresses'])->name('addresses.index');
            Route::post('addresses', [CustomerMobileController::class, 'storeAddress'])->name('addresses.store');
            Route::get('addresses/{id}', [CustomerMobileController::class, 'showAddress'])->name('addresses.show');
            Route::get('addresses/{id}/orders', [CustomerMobileController::class, 'addressOrders'])->name('addresses.orders');
            Route::match(['patch', 'put'], 'addresses/{id}', [CustomerMobileController::class, 'updateAddress'])->name('addresses.update');
            Route::post('addresses/{id}/images', [AddressImageController::class, 'store'])->name('addresses.images.store');
            Route::match(['patch', 'put'], 'addresses/{id}/images/{imageId}', [AddressImageController::class, 'update'])->name('addresses.images.update');
            Route::delete('addresses/{id}/images/{imageId}', [AddressImageController::class, 'destroy'])->name('addresses.images.destroy');
            Route::delete('addresses/{id}', [CustomerMobileController::class, 'destroyAddress'])->name('addresses.destroy');
            Route::get('delivery-zones', [CustomerMobileController::class, 'deliveryZones'])->name('delivery-zones.index');
            Route::get('categories', [CustomerMobileController::class, 'categories'])->name('categories.index');
            Route::get('products', [CustomerMobileController::class, 'products'])->name('products.index');
            Route::get('products/filters', [CustomerMobileController::class, 'productFilters'])->name('products.filters');
            Route::get('featured-sections', [CustomerMobileController::class, 'featuredSections'])->name('featured-sections.index');
            Route::get('featured-sections/{id}/products', [CustomerMobileController::class, 'featuredSectionProducts'])->name('featured-sections.products');
            Route::get('favorites', [FavoriteController::class, 'index'])->name('favorites.index');
            Route::get('favorites/ids', [FavoriteController::class, 'ids'])->name('favorites.ids');
            Route::post('favorites', [FavoriteController::class, 'store'])->name('favorites.store');
            Route::delete('favorites/{productId}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
            Route::get('products/{id}', [CustomerMobileController::class, 'showProduct'])->name('products.show');
            Route::get('products/{id}/variants', [CustomerMobileController::class, 'productVariants'])->name('products.variants');

            Route::get('cart', [CustomerMobileController::class, 'cart'])->name('cart');
            Route::get('cart/check-stock', [CustomerMobileController::class, 'checkStock'])->name('cart.check-stock');
            Route::post('cart/items', [CustomerMobileController::class, 'addCartItem'])->name('cart.items.store');
            Route::match(['patch', 'put'], 'cart/items/{id}', [CustomerMobileController::class, 'updateCartItem'])->name('cart.items.update');
            Route::delete('cart/items/{id}', [CustomerMobileController::class, 'removeCartItem'])->name('cart.items.destroy');
            Route::delete('cart', [CustomerMobileController::class, 'clearCart'])->name('cart.clear');
            Route::post('cart/checkout', [CustomerMobileController::class, 'checkout'])->name('cart.checkout');

            Route::get('wallet', [CustomerWalletController::class, 'show'])->name('wallet.show');
            Route::get('wallet/transactions', [CustomerWalletController::class, 'transactions'])->name('wallet.transactions');
            Route::get('wallet/topups', [CustomerWalletController::class, 'topups'])->name('wallet.topups.index');
            Route::post('wallet/topups', [CustomerWalletController::class, 'storeTopup'])->name('wallet.topups.store');
            Route::post('wallet/topups/gateway', [CustomerWalletController::class, 'gatewayTopup'])->name('wallet.topups.gateway');
            Route::post('wallet/topups/{id}/cancel', [CustomerWalletController::class, 'cancelTopup'])->name('wallet.topups.cancel');

            Route::get('recurring-carts', [RecurringCartController::class, 'index'])->name('recurring-carts.index');
            Route::post('recurring-carts', [RecurringCartController::class, 'store'])->name('recurring-carts.store');
            Route::get('recurring-carts/{id}', [RecurringCartController::class, 'show'])->name('recurring-carts.show');
            Route::match(['patch', 'put'], 'recurring-carts/{id}', [RecurringCartController::class, 'update'])->name('recurring-carts.update');
            Route::delete('recurring-carts/{id}', [RecurringCartController::class, 'destroy'])->name('recurring-carts.destroy');
            Route::post('recurring-carts/{id}/order', [RecurringCartController::class, 'order'])->name('recurring-carts.order');

            Route::get('orders/{id}/delegate', [CustomerMobileController::class, 'orderDelegate'])->name('orders.delegate');
            Route::get('orders/{id}/invoice', [CustomerMobileController::class, 'orderInvoice'])->name('orders.invoice');
            Route::get('wallet/statement', [CustomerWalletController::class, 'statement'])->name('wallet.statement');
            Route::get('promos', [PromoController::class, 'active'])->name('promos.index');
        });

        Route::prefix('delegate')->name('delegate.')->group(function () use ($account) {
            $account();

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

        // Announcements from the dashboard to customers/delegates/admins (NOTIFICATIONS_SEND).
        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::delete('devices', [DeviceController::class, 'destroy'])->name('devices.destroy');
        Route::post('notifications/send', [NotificationController::class, 'send'])->name('notifications.send');
        Route::get('notifications/sent', [NotificationController::class, 'sent'])->name('notifications.sent');
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::match(['patch', 'put'], 'notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
        Route::apiResource('notifications', NotificationController::class)->only(['index', 'show', 'destroy']);
        Route::match(['patch', 'put'], 'notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

        // Pictures of an address, for the office. The names are three segments,
        // so they need an entry in CheckPermission::ROUTE_MAP: a name that does
        // not split into resource.action yields no code, and a route with no
        // code is not checked at all.
        Route::post('addresses/{id}/images', [AddressImageController::class, 'store'])->name('addresses.images.store');
        Route::match(['patch', 'put'], 'addresses/{id}/images/{imageId}', [AddressImageController::class, 'update'])->name('addresses.images.update');
        Route::delete('addresses/{id}/images/{imageId}', [AddressImageController::class, 'destroy'])->name('addresses.images.destroy');

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
        Route::match(['patch', 'put'], 'delegates/{delegate}/location', [DelegateController::class, 'updateLocation'])->name('delegates.location');

        // Write endpoints for the storefront resources are protected;
        // index/show remain public above.
        Route::apiResource('products', ProductController::class)->except(['index', 'show']);
        Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);

        Route::post('orders/{order}/assign-delegate', [OrderController::class, 'assignDelegate'])->name('orders.assign-delegate');
        Route::get('orders/{order}/invoice', [ReportController::class, 'invoice'])->name('orders.invoice');

        // Recorded, never edited: a return moves stock and money, so a mistake
        // is corrected by an adjustment that leaves its own trace.
        Route::get('returns', [ReturnController::class, 'index'])->name('returns.index');
        Route::post('returns', [ReturnController::class, 'store'])->name('returns.store');
        Route::get('returns/{orderReturn}', [ReturnController::class, 'show'])->name('returns.show');
        Route::post('returns/{orderReturn}/pay-refund', [ReturnController::class, 'payRefund'])->name('returns.pay-refund');

        // Reports: JSON for the dashboard, ?format=pdf|xlsx for downloads. All need REPORTS_VIEW.
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('sales', [ReportController::class, 'sales'])->name('sales');
            Route::get('profit', [ReportController::class, 'profit'])->name('profit');
            Route::get('delegates', [ReportController::class, 'delegates'])->name('delegates');
            Route::get('inventory', [ReportController::class, 'inventory'])->name('inventory');
            Route::get('orders', [ReportController::class, 'orders'])->name('orders');
            Route::get('custody/{delegate}', [ReportController::class, 'custody'])->name('custody');
            Route::get('wallet/{wallet}', [ReportController::class, 'wallet'])->name('wallet');
        });
    });
});
