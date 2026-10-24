<?php

namespace App\Support;

use Illuminate\Http\Request;

class PortalNavigation
{
    public static function back(Request $request): string
    {
        $name = $request->route()?->getName() ?? '';

        return match (true) {
            $name === 'portal.dashboard' => route('home'),
            str_starts_with($name, 'portal.orders.') && $name !== 'portal.orders.index' => route('portal.orders.index'),
            str_starts_with($name, 'portal.messages.') && $name !== 'portal.messages.index' => route('portal.messages.index'),
            str_starts_with($name, 'portal.users.') && $name !== 'portal.users.index' => route('portal.users.index'),
            str_starts_with($name, 'portal.invoices.') => route('portal.orders.index'),
            str_starts_with($name, 'portal.integrations.') && $name !== 'portal.integrations.index' => route('portal.integrations.index'),
            default => route('portal.dashboard'),
        };
    }
}
