<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\IdentitySession;
use App\Models\Setting;
use App\Services\Audit;
use App\Services\IdentitySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function index()
    {
        return view('portal.settings', ['settings' => Setting::where('key', '!=', 'identity_verification_mode')->orderBy('group')->orderBy('id')->get()->groupBy('group'),
            'identityReady' => IdentitySettings::ready(), 'identityMode' => IdentitySettings::mode(),
            'identityCounts' => IdentitySession::selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state'),
            'identityLastSync' => IdentitySession::max('last_synced_at')]);
    }

    public function update(Request $request)
    {
        $fields = Setting::where('type', '!=', 'file')->where('key', '!=', 'identity_verification_mode')->pluck('key')->all();
        $rules = [];
        foreach ($fields as $field) {
            $rules[$field] = str_ends_with($field, '_url') ? ['nullable', 'url:http,https', 'max:500'] : ['nullable', 'string', 'max:5000'];
        }
        $rules['email'] = ['nullable', 'email', 'max:255'];
        $rules['company_name'] = ['required', 'string', 'max:200'];
        $rules['identity_verification_mode'] = ['sometimes', 'in:manual,didit'];
        $rules['logo'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'];
        $rules['favicon'] = ['nullable', 'image', 'mimes:png', 'extensions:png', 'max:512', 'dimensions:max_width=512,max_height=512'];
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
