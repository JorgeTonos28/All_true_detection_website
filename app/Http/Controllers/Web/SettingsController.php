<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BusinessBankAccount;
use App\Models\IdentitySession;
use App\Models\Service;
use App\Models\Setting;
use App\Services\Audit;
use App\Services\BusinessBillingSettings;
use App\Services\IdentitySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'brand');
        $tab = in_array($tab, ['brand', 'contact', 'content', 'billing', 'identity'], true) ? $tab : 'brand';

        return view('portal.settings', ['tab' => $tab, 'bankAccounts' => BusinessBankAccount::orderBy('sort_order')->orderBy('id')->get(),
            'services' => $tab === 'billing' ? Service::where('is_active', true)->with('optionPrices')->orderBy('sort_order')->get() : collect(), 'pricingEnabled' => BusinessBillingSettings::pricingEnabled(),
            'settings' => Setting::whereNotIn('key', ['identity_verification_mode', 'catalog_pricing_enabled'])->orderBy('group')->orderBy('id')->get()->groupBy('group'),
            'identityReady' => IdentitySettings::ready(), 'identityMode' => IdentitySettings::mode(),
            'identityCounts' => IdentitySession::selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state'),
            'identityLastSync' => IdentitySession::max('last_synced_at')]);
    }

    public function update(Request $request)
    {
        $section = $request->validate(['section' => 'sometimes|in:brand,contact,content,billing,identity,all'])['section'] ?? 'all';
        if ($section === 'billing') {
            $input = $request->only(['catalog_pricing_enabled', 'service_prices', 'service_option_prices', 'bank_accounts']);
            if ($request->boolean('bank_accounts_present') && ! array_key_exists('bank_accounts', $input)) {
                $input['bank_accounts'] = [];
            }
            app(BusinessBillingSettings::class)->save($request->user(), $input);

            return back()->with('status', 'Precios y cuentas bancarias actualizados.');
        }
        $groups = ['brand' => ['Marca'], 'contact' => ['Contacto', 'Redes'], 'content' => ['Home', 'SEO'], 'identity' => []];
        $fields = Setting::where('type', '!=', 'file')->whereNotIn('key', ['identity_verification_mode', 'catalog_pricing_enabled'])
            ->when($section !== 'all', fn ($query) => $query->whereIn('group', $groups[$section]))->pluck('key')->all();
        $rules = [];
        foreach ($fields as $field) {
            $rules[$field] = str_ends_with($field, '_url') ? ['nullable', 'url:http,https', 'max:500'] : ['nullable', 'string', 'max:5000'];
        }
        if (in_array('email', $fields, true)) {
            $rules['email'] = ['nullable', 'email', 'max:255'];
        }
        if (in_array('company_name', $fields, true)) {
            $rules['company_name'] = ['required', 'string', 'max:200'];
        }
        if (in_array($section, ['all', 'identity'], true)) {
            $rules['identity_verification_mode'] = ['sometimes', 'in:manual,didit'];
        }
        if (in_array($section, ['all', 'brand'], true)) {
            $rules['logo'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'];
            $rules['favicon'] = ['nullable', 'image', 'mimes:png', 'extensions:png', 'max:512', 'dimensions:max_width=512,max_height=512'];
        }
        $data = $request->validate($rules);
        if (($data['identity_verification_mode'] ?? null) === 'didit' && ! IdentitySettings::ready()) {
            throw ValidationException::withMessages(['identity_verification_mode' => 'Configure las credenciales, aplicación, workflow y aprobaciones Didit antes de activar el modo automático.']);
        }
        DB::transaction(function () use ($request, $fields, $data) {
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    Setting::put($field, $data[$field]);
                }
            }
            if (isset($data['identity_verification_mode'])) {
                Setting::put('identity_verification_mode', $data['identity_verification_mode']);
            }
            foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path'] as $input => $key) {
                if ($request->hasFile($input)) {
                    Setting::put($key, $request->file($input)->store('brand', 'public'));
                }
            }
            Audit::record($request->user(), 'business.settings_updated', (new Setting)->forceFill(['id' => 0]), ['fields' => array_keys($data)]);
        });

        return back()->with('status', 'Información del negocio actualizada.');
    }
}
