<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderFilters
{
    public static function validate(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)], 'q' => ['nullable', 'string', 'max:100'],
            'billing' => ['nullable', 'in:pending,paid,unquoted,credit'], 'mine' => ['nullable', 'in:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when(filled($request->input('from')), ['after_or_equal:from'])],
        ]);
    }

    public static function apply(Builder $query, array $data, User $user): Builder
    {
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (filled($data['q'] ?? null)) {
            $pattern = UserDirectory::pattern(trim($data['q']));
            $query->where(fn ($matches) => $matches->whereRaw("LOWER(subject) LIKE ? ESCAPE '!'", [$pattern])->orWhere('public_id', $data['q'])
                ->orWhereRaw("LOWER(external_reference) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('account', fn ($account) => $account->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])));
        }
        if (($data['billing'] ?? null) === 'unquoted') {
            $query->whereDoesntHave('invoice')->whereDoesntHave('people', fn ($people) => $people->whereHas('identitySession', fn ($sessions) => $sessions->where('access_channel', 'api_credit')));
        } elseif (($data['billing'] ?? null) === 'credit') {
            $query->whereHas('people', fn ($people) => $people->whereHas('identitySession', fn ($sessions) => $sessions->where('access_channel', 'api_credit')));
        } elseif (! empty($data['billing'])) {
            $query->whereHas('invoice', fn ($invoice) => $invoice->where('status', $data['billing'] === 'paid' ? 'paid' : 'unpaid'));
        }
        if (! empty($data['mine']) && $user->isStaff()) {
            $query->where('assigned_to', $user->id);
        }
        $timezone = config('portal.display_timezone');
        if (! empty($data['from'])) {
            $query->where('created_at', '>=', CarbonImmutable::createFromFormat('!Y-m-d', $data['from'], $timezone)->utc());
        }
        if (! empty($data['to'])) {
            $query->where('created_at', '<', CarbonImmutable::createFromFormat('!Y-m-d', $data['to'], $timezone)->addDay()->utc());
        }

        return $query;
    }
}
