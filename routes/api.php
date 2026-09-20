<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\AbandonedCheckoutController;
use App\Http\Controllers\CourierNoteController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SiteSettingController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\CourierSettingController;
use App\Http\Controllers\DeliverySettingController;
use App\Http\Controllers\HomeSectionController;
use App\Http\Controllers\VisualSearchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\StaffController;

// Public routes
Route::get('/track-order', [OrderController::class, 'trackByPhone']);
Route::post('/abandoned-checkout/save', [AbandonedCheckoutController::class, 'save']);
Route::post('/abandoned-checkout/delete', [AbandonedCheckoutController::class, 'delete']);
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/banners', [BannerController::class, 'index']);
Route::get('/site-settings', [SiteSettingController::class, 'index']);
Route::get('/districts', fn() => response()->json([]));
Route::get('/upazilas', fn() => response()->json([]));
Route::get('/delivery-settings', [DeliverySettingController::class, 'index']);
Route::get('/home-sections', [HomeSectionController::class, 'index']);
Route::get('/menu-items', fn() => response()->json([]));
Route::get('/pages', [PageController::class, 'index']);
Route::get('/pages/{slug}', [PageController::class, 'show']);
Route::get('/courier-settings', [CourierSettingController::class, 'index']);

// Guest order & public visual search
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{id}', [OrderController::class, 'show']);
Route::post('/search-by-image', [VisualSearchController::class, 'search']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/products/bulk-delete', [ProductController::class, 'bulkDestroy']);
    Route::post('/categories/bulk-delete', [CategoryController::class, 'bulkDestroy']);
    Route::post('/orders/bulk-delete', [OrderController::class, 'bulkDestroy']);

    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::post('/products/{id}/duplicate', [ProductController::class, 'duplicate']);

    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::put('/orders/{id}', [OrderController::class, 'update']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);
    Route::put('/order-items/{id}', [OrderController::class, 'updateItem']);
    Route::delete('/order-items/{id}', [OrderController::class, 'deleteItem']);
    Route::get('/user/orders', [OrderController::class, 'userOrders']);
    Route::get('/user/orders/{id}', [OrderController::class, 'userOrderShow']);
    Route::put('/user/orders/{id}/cancel', [OrderController::class, 'cancel']);

    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{productId}', [WishlistController::class, 'destroy']);

    Route::post('/site-settings', [SiteSettingController::class, 'update']);

    Route::post('/banners', [BannerController::class, 'store']);
    Route::put('/banners/{id}', [BannerController::class, 'update']);
    Route::delete('/banners/{id}', [BannerController::class, 'destroy']);

    Route::post('/upload-image', [ImageController::class, 'upload']);
    Route::delete('/delete-image', [ImageController::class, 'delete']);

    Route::put('/courier-settings/{id}', [CourierSettingController::class, 'update']);
    Route::post('/courier-settings/{id}/set-default', [CourierSettingController::class, 'setDefault']);
    Route::post('/courier/book', [CourierSettingController::class, 'book']);

    Route::get('/courier-notes/{orderId}', [CourierNoteController::class, 'index']);
    Route::post('/courier-notes', [CourierNoteController::class, 'store']);

    Route::put('/home-sections/{id}', [HomeSectionController::class, 'update']);
    Route::post('/home-sections', [HomeSectionController::class, 'store']);
    Route::post('/delivery-settings', [DeliverySettingController::class, 'store']);
    Route::put('/delivery-settings/{id}', [DeliverySettingController::class, 'update']);

    Route::post('/pages', [PageController::class, 'store']);
    Route::delete('/pages/{id}', [PageController::class, 'destroy']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::get('/abandoned-checkouts', [AbandonedCheckoutController::class, 'index']);
    Route::delete('/abandoned-checkouts/{id}', [AbandonedCheckoutController::class, 'destroy']);

    // Customer Management
    Route::get('/admin/customers', [CustomerController::class, 'index']);
    Route::get('/admin/customers/orders', [CustomerController::class, 'orders']);

    // Anti-Bot & Blacklist Management
    Route::get('/admin/blocked-entities', [OrderController::class, 'blockedEntities']);
    Route::post('/admin/blocked-entities', [OrderController::class, 'blockEntity']);
    Route::delete('/admin/blocked-entities/{id}', [OrderController::class, 'unblockEntity']);

    // Staff & Role-Based Access Control
    Route::get('/admin/staff', [StaffController::class, 'index']);
    Route::post('/admin/staff', [StaffController::class, 'store']);
    Route::put('/admin/staff/{id}', [StaffController::class, 'update']);
    Route::delete('/admin/staff/{id}', [StaffController::class, 'destroy']);
});