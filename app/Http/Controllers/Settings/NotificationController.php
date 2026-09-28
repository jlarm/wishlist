<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotificationPreferencesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Show the user's notification preferences.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Notifications', [
            'preferences' => [
                'notify_price_drops' => $user->notify_price_drops,
                'notify_gift_purchases' => $user->notify_gift_purchases,
                'notify_occasion_reminders' => $user->notify_occasion_reminders,
            ],
        ]);
    }

    /**
     * Update the user's notification preferences.
     */
    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Set explicitly rather than mass-assign: the User model keeps a minimal
        // fillable list so sensitive columns (e.g. is_admin) can't be flipped.
        $user->notify_price_drops = $request->boolean('notify_price_drops');
        $user->notify_gift_purchases = $request->boolean('notify_gift_purchases');
        $user->notify_occasion_reminders = $request->boolean('notify_occasion_reminders');
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification preferences updated.')]);

        return to_route('notifications.edit');
    }
}
