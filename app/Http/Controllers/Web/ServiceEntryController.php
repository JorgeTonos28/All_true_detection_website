<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceEntryController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(['service' => ['nullable', 'string', Rule::exists('services', 'code')->where('is_active', true)]]);
        if ($request->user()?->isStaff()) {
            return redirect()->route('portal.orders.index')->with('status', 'Está usando una cuenta del equipo ATD. Para solicitar servicios, entre con una cuenta de cliente.');
        }
        $service = $data['service'] ?? null;
        if ($service) {
            $request->session()->put('requested_service', $service);
        }
        $destination = route('portal.orders.create', $service ? ['service' => $service] : []);
        if (! $request->user()) {
            $request->session()->put('url.intended', $destination);

            return redirect()->route('login');
        }

        return redirect()->to($destination);
    }
}
