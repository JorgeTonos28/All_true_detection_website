<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function index()
    {
        return view('portal.settings', ['settings' => Setting::orderBy('group')->orderBy('id')->get()->groupBy('group')]);
    }

    public function update(Request $request)
    {
        $fields = Setting::where('type', '!=', 'file')->pluck('key')->all();
        $rules = [];
        foreach ($fields as $field) {
            $rules[$field] = str_ends_with($field, '_url') ? ['nullable', 'url:http,https', 'max:500'] : ['nullable', 'string', 'max:5000'];
        }
        $rules['email'] = ['nullable', 'email', 'max:255'];
        $rules['company_name'] = ['required', 'string', 'max:200'];
        $rules['logo'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'];
        $rules['favicon'] = ['nullable', 'image', 'mimes:png', 'extensions:png', 'max:512', 'dimensions:max_width=512,max_height=512'];
        $data = $request->validate($rules);
        DB::transaction(function () use ($request, $fields, $data) {
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    Setting::put($field, $data[$field]);
                }
            }
            foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path'] as $input => $key) {
                if ($request->hasFile($input)) {
                    Setting::put($key, $request->file($input)->store('brand', 'public'));
                }
            }
            Audit::record($request->user(), 'business.settings_updated', (new Setting)->forceFill(['id' => 0]), ['fields' => array_keys($data)]);
        });

        return back()->with('status','Información del negocio actualizada.');
    }
}
