<?php
/**
 * Notifications API Routes
 *
 * Defines API routes for notification operations.
 *
 * @since 2.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\Notifications
 */

use ArtisanPackUI\CMSFramework\Modules\Notifications\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Notification API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the NotificationServiceProvider and are
| prefixed with "api/v1" and use the "api" middleware group.
|
*/

Route::middleware(['auth:sanctum'])->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('api.notifications.unreadCount');
    Route::get('/{id}', [NotificationController::class, 'show'])->name('api.notifications.show');
    Route::post('/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('api.notifications.markAsRead');
    Route::post('/{id}/dismiss', [NotificationController::class, 'dismiss'])->name('api.notifications.dismiss');
    Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.markAllAsRead');
    Route::post('/dismiss-all', [NotificationController::class, 'dismissAll'])->name('api.notifications.dismissAll');
});
