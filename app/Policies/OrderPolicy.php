<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->is_active && Order::query()->visibleTo($user)->whereKey($order->id)->exists();
    }

    public function update(User $user, Order $order): bool
    {
        return $user->isStaff() && $this->view($user, $order);
    }

    public function administer(User $user, Order $order): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function viewResults(User $user, Order $order): bool
    {
        return $this->view($user, $order) && ($user->isStaff() || $order->canReleaseResults());
    }
}
