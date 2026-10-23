<?php
namespace App\Actions\Messages;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\PortalActivity;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
class ReplyToConversation
{
    public function handle(User $actor, Conversation $conversation, string $body): Message
    {
        return DB::transaction(function () use ($actor, $conversation, $body) {
            $conversation = Conversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('reply', $conversation);
            $message = $conversation->messages()->create(['sender_id'=>$actor->id,'body'=>$body]);
            $conversation->touch();
            Audit::record($actor,'message.sent',$message);
            $notification = new PortalActivity('Nuevo mensaje en ATD', '/portal/messages/'.$conversation->public_id);
            if ($actor->isStaff()) {
                if ($conversation->customer_account_id) {
                    foreach (User::where('customer_account_id',$conversation->customer_account_id)->where('role','client')->where('is_active',true)->whereNotNull('email_verified_at')->get() as $client) { $client->notify($notification); }
                } else { Notification::route('mail',$conversation->contact_email)->notify($notification); }
            } else {
                foreach (User::where('role','admin')->where('is_active',true)->whereNotNull('email_verified_at')->get() as $admin) { $admin->notify($notification); }
                $assignee = $conversation->order?->assignee;
                if ($assignee && ! $assignee->isAdmin() && $assignee->is_active) { $assignee->notify($notification); }
            }
            return $message;
        });
    }
}
