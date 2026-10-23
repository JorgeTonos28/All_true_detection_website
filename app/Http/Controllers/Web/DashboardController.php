<?php
namespace App\Http\Controllers\Web;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Order;
use Illuminate\Http\Request;
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $query=Order::visibleTo($request->user());
        return view('portal.dashboard',[
            'counts'=>(clone $query)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate','status'),
            'orders'=>(clone $query)->with(['account','invoice'])->latest('id')->limit(8)->get(),
            'conversationCount'=>Conversation::visibleTo($request->user())->where('status','open')->count(),
        ]);
    }
}
