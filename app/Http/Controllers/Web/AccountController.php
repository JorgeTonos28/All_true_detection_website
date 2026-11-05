<?php

namespace App\Http\Controllers\Web;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(! $request->user()->isStaff() && $request->user()->customer_account_id, 403);

        return view('portal.account', ['account' => $request->user()->account]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        abort_unless(! $user->isStaff() && $user->customer_account_id, 403);
        $data = $request->validate(['account_type' => ['required', Rule::enum(AccountType::class)], 'name' => ['required', 'string', 'max:120'], 'company' => ['required', 'string', 'max:200'], 'billing_email' => ['required', 'email', 'max:255'], 'tax_id' => ['nullable', 'string', 'max:50'], 'billing_address' => ['nullable', 'string', 'max:1000']]);
        $wasUnclassified = ! $user->account->account_type;
        DB::transaction(function () use ($user, $data) {
            $user->update(['name' => $data['name']]);
            $user->account->update(['account_type' => $data['account_type'], 'name' => $data['company'], 'billing_email' => $data['billing_email'], 'tax_id' => $data['tax_id'] ?? null, 'billing_address' => $data['billing_address'] ?? null]);
            Audit::record($user, 'account.billing_updated', $user->account, ['account_type' => $data['account_type']]);
        });

        return ($wasUnclassified ? redirect()->route('portal.dashboard') : back())->with('status', 'Datos actualizados. Las facturas ya emitidas conservan los datos originales.');
    }
}
