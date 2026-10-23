<?php

namespace App\Actions\Messages;

use App\Models\Conversation;
use App\Models\User;
use App\Notifications\PortalActivity;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;

class CreateConversation
{
    public function handle(?User $actor, array $data): Conversation
    {
        abort_if($actor && (! $actor->is_active || ! $actor->hasVerifiedEmail() || $actor->isStaff()), 403);

        return DB::transaction(function () use ($actor, $data) {
            $conversation = Conversation::create([
                'customer_account_id' => $actor?->customer_account_id, 'subject' => $data['subject'],
                'contact_name' => $actor?->name ?? $data['name'], 'contact_email' => $actor?->email ?? mb_strtolower(trim($data['email'])),
            ]);
            $conversation->messages()->create(['sender_id' => $actor?->id, 'body' => $data['body']]);
            Audit::record($actor, 'conversation.created', $conversation);
            foreach (User::where('role', 'admin')->where('is_active', true)->whereNotNull('email_verified_at')->get() as $admin) {
                $admin->notify(new PortalActivity('Nueva consulta en ATD', '/portal/messages/'.$conversation->public_id));
            }

            return $conversation;
        });
    }
}
