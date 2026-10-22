<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $settings = Setting::query()
            ->orderBy('group')
            ->orderBy('id')
            ->get()
            ->groupBy('group');

        $values = Setting::values();
        $logoUrl = filled($values['logo_path'] ?? null)
            ? Storage::url($values['logo_path'])
            : asset('assets/images/brand-lockup-dark.png');

        return view('admin.dashboard', [
            'settings' => $settings,
            'values' => $values,
            'logoUrl' => $logoUrl,
            'adminUser' => Auth::user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $socialFields = [
            'instagram_url',
            'tiktok_url',
            'facebook_url',
            'youtube_url',
            'x_url',
        ];

        $fields = Setting::query()
            ->where('type', '!=', 'file')
            ->pluck('key')
            ->all();

        $rules = collect($fields)
            ->mapWithKeys(fn (string $field): array => [
                $field => in_array($field, $socialFields, true)
                    ? ['nullable', 'url', 'max:500']
                    : ['nullable', 'string', 'max:5000'],
            ])
            ->all();

        $adminUser = $request->user();

        $data = $request->validate(array_merge($rules, [
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($adminUser->id)],
            'admin_password' => ['nullable', 'string', 'min:8', 'max:255', 'confirmed'],
        ]));

        foreach ($fields as $field) {
            Setting::put($field, $data[$field] ?? null);
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('brand', 'public');
            Setting::put('logo_path', $path);
        }

        $adminUser->email = $data['admin_email'];

        if (filled($data['admin_password'] ?? null)) {
            $adminUser->password = $data['admin_password'];
        }

        $adminUser->save();

        return back()->with('status', 'Información actualizada correctamente.');
    }
}
