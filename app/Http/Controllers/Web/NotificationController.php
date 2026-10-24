<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\NotificationInbox;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function summary(Request $request)
    {
        return response()->json(['html' => view('portal.notification-menu', NotificationInbox::summary($request->user()))->render()]);
    }

    public function index(Request $request)
    {
        $data = $request->validate(['unread' => ['nullable', 'in:1']]);

        return view('portal.notifications', ['notifications' => $request->user()->notifications()->when($data['unread'] ?? false, fn ($q) => $q->whereNull('read_at'))->latest()->paginate(25)->withQueryString()]);
    }

    public function open(Request $request, string $notification)
    {
        $notice = $request->user()->notifications()->findOrFail($notification);
        $path = $notice->data['path'] ?? '/portal';
        abort_unless(is_string($path) && preg_match('#^/portal(?:/|\?|$)#', $path) && ! str_contains($path, '\\') && ! str_contains($path, "\n") && ! str_contains($path, "\r"), 422);
        $notice->markAsRead();

        return redirect()->to($path);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Sus notificaciones quedaron marcadas como atendidas.');
    }
}
