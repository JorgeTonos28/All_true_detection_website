<?php
namespace App\Services;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
class Audit
{
    public static function record(?User $actor, string $action, Model $subject, array $metadata = []): void
    {
        AuditEvent::create(['actor_id' => $actor?->id, 'action' => $action, 'subject_type' => $subject->getTable(), 'subject_id' => (string) $subject->getKey(), 'metadata' => $metadata]);
    }
}
