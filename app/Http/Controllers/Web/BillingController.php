<?php
namespace App\Http\Controllers\Web;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
class BillingController extends Controller
{
    public function issue(Request $request, Order $order, IssueInvoice $action)
    {
        Gate::authorize('administer',$order);
        $data=$request->validate(['prices'=>['required','array'],'prices.*'=>['required','integer','min:1','max:100000000'],'tax_basis_points'=>['required','integer','min:0','max:10000'],'due_date'=>['nullable','date','after_or_equal:today']]);
        $action->handle($request->user(),$order,$data);
        return back()->with('status','Factura emitida. Los importes quedaron fijados.');
    }
    public function pay(Request $request, Invoice $invoice, RecordPayment $action)
    {
        Gate::authorize('administer',$invoice->order);
        $data=$request->validate(['amount'=>['required','integer','min:1','max:200000000000'],'method'=>['required',Rule::in(['bank_transfer','cash','check','other'])],'reference'=>['required','string','max:100'],'received_at'=>['nullable','date','before_or_equal:now']]);
        $action->handle($request->user(),$invoice,$data);
        return back()->with('status','Pago registrado y saldo actualizado.');
    }
    public function show(Invoice $invoice)
    {
        Gate::authorize('view',$invoice->order);
        return view('portal.invoice',['invoice'=>$invoice->load(['order.items','payments'])]);
    }
}
