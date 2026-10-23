<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Messages\ReplyToConversation;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function index(Order $order)
    {
        Gate::authorize('view', $order);

        return MessageResource::collection($order->conversation->messages()->with('sender')->orderBy('id')->paginate(50));
    }

    public function store(Request $request, Order $order, ReplyToConversation $action)
    {
        Gate::authorize('view', $order);
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:10000']]);

        return (new MessageResource($action->handle($request->user(), $order->conversation, $data['body'])->load('sender')))->response()->setStatusCode(201);
    }
}
