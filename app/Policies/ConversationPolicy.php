<?php
namespace App\Policies;
use App\Models\Conversation;
use App\Models\User;
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool { return $user->is_active && Conversation::query()->visibleTo($user)->whereKey($conversation->id)->exists(); }
    public function reply(User $user, Conversation $conversation): bool { return $this->view($user, $conversation) && $conversation->status === 'open'; }
}
