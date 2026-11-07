<?php

namespace App\Services;

use App\Models\User;

class NotificationInbox
{
    public static function summary(User $user): array
    {
        return ['portalUnreadCount' => $user->unreadNotifications()->count(), 'portalNotices' => $user->unreadNotifications()->latest()->limit(8)->get()];
    }
}
