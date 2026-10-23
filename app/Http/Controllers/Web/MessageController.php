<?php
namespace App\Http\Controllers\Web;
use App\Actions\Messages\CreateConversation;
use App\Actions\Messages\ReplyToConversation;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
class MessageController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()->isStaff() && $request->user()->customer_account_id) {
            Conversation::whereNull('customer_account_id')->whereNull('order_id')->where('contact_email',$request->user()->email)->update(['customer_account_id'=>$request->user()->customer_account_id]);
        }
        return view('portal.messages.index',['conversations'=>Conversation::visibleTo($request->user())->with('order')->withCount('messages')->latest('updated_at')->paginate(20)]);
    }
    public function show(Conversation $conversation)
    {
        Gate::authorize('view',$conversation);
        return view('portal.messages.show',['conversation'=>$conversation->load('order'),'messages'=>$conversation->messages()->with('sender')->latest('id')->paginate(50)]);
    }
    public function create(Request $request, CreateConversation $action)
    {
        $data=$request->validate(['subject'=>['required','string','max:200'],'body'=>['required','string','min:5','max:10000']]);
        $conversation=$action->handle($request->user(),$data);
        return redirect()->route('portal.messages.show',$conversation)->with('status','Mensaje enviado.');
    }
    public function reply(Request $request, Conversation $conversation, ReplyToConversation $action)
    {
        $data=$request->validate(['body'=>['required','string','min:1','max:10000']]);
        $action->handle($request->user(),$conversation,$data['body']);
        return back()->with('status','Respuesta enviada.');
    }
    public function close(Request $request, Conversation $conversation)
    {
        Gate::authorize('manage-business');
        $data=$request->validate(['status'=>['required','in:open,closed']]);
        $conversation->update($data);
        \App\Services\Audit::record($request->user(),'conversation.status_changed',$conversation,$data);
        return back()->with('status','Estado de conversación actualizado.');
    }
    public function contact(Request $request, CreateConversation $action)
    {
        $data=$request->validate(['name'=>['required','string','max:120'],'email'=>['required','email','max:255'],'subject'=>['required','string','max:200'],'body'=>['required','string','min:10','max:10000'],'consent'=>['accepted'],'website'=>['nullable','max:0']]);
        $action->handle(null,$data);
        return redirect()->to(route('home').'#contacto')->with('contact_status','Consulta recibida. Para consultar la conversación, cree una cuenta y verifique este mismo correo.');
    }
}
