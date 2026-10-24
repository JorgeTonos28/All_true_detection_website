<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class UserDirectory
{
    public function query(string $search = '', ?string $role = null, ?string $status = null): Builder
    {
        $query = User::query()->select([
            'id', 'name', 'email', 'role', 'is_active', 'customer_account_id',
            'email_verified_at', 'two_factor_confirmed_at', 'created_at', 'updated_at',
        ])->with('account:id,name,billing_email,tax_id,billing_address');

        $query->when($role, fn (Builder $query) => $query->where('role', $role));
        $query->when($status, fn (Builder $query) => $query->where('is_active', $status === 'active'));

        $words = preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY);
        if ($words) {
            $query->where(function (Builder $matches) use ($words): void {
                foreach ($words as $word) {
                    $pattern = self::pattern($word);
                    foreach (['id', 'name', 'email', 'role', 'created_at', 'updated_at', 'email_verified_at', 'two_factor_confirmed_at', 'customer_account_id'] as $column) {
                        $matches->orWhereRaw("LOWER(users.{$column}) LIKE ? ESCAPE '!'", [$pattern]);
                    }
                    $matches->orWhereHas('account', function (Builder $account) use ($pattern): void {
                        $account->where(function (Builder $fields) use ($pattern): void {
                            foreach (['name', 'billing_email', 'tax_id', 'billing_address'] as $column) {
                                $fields->orWhereRaw("LOWER(customer_accounts.{$column}) LIKE ? ESCAPE '!'", [$pattern]);
                            }
                        });
                    });
                    foreach (Role::cases() as $role) {
                        if (mb_stripos($role->label(), $word) !== false) {
                            $matches->orWhere('role', $role->value);
                        }
                    }
                    foreach (['Activo' => true, 'Desactivado' => false] as $label => $active) {
                        if (mb_stripos($label, $word) !== false) {
                            $matches->orWhere('is_active', $active);
                        }
                    }
                    foreach (['Verificado' => ['email_verified_at', true], 'Por verificar' => ['email_verified_at', false], '2FA activa' => ['two_factor_confirmed_at', true], 'Sin 2FA' => ['two_factor_confirmed_at', false]] as $label => [$column, $present]) {
                        if (mb_stripos($label, $word) !== false) {
                            $matches->whereNull($column, 'or', $present);
                        }
                    }
                }
            });
        }

        return $query->orderBy('name')->orderBy('id');
    }

    public static function pattern(string $word): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($word)).'%';
    }
}
