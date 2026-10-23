<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\WebhookDelivery;
class OperationsController extends Controller
{
    public function __invoke()
    {
        return view('portal.operations',['events'=>AuditEvent::with('actor')->latest('id')->paginate(40),'deliveries'=>WebhookDelivery::with('order')->whereNull('delivered_at')->latest('id')->limit(30)->get()]);
    }
}
