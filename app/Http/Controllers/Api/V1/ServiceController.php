<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\IdentitySettings;
use App\Support\PersonVerification;

class ServiceController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Service::where('is_active', true)->orderBy('sort_order')->get(['code', 'name', 'description'])->map(fn ($service) => array_replace($service->toArray(), [
            'description' => $service->code === 'identity-verification' ? IdentitySettings::description() : $service->description,
            'identity_mode' => $service->code === 'identity-verification' ? IdentitySettings::mode() : null,
            'per_person' => in_array($service->code, PersonVerification::PERSON_SERVICES, true),
            'requires_identity_number' => in_array($service->code, PersonVerification::SERVICES, true),
            'required_evidence' => PersonVerification::requiredEvidence([$service->code], 2, IdentitySettings::mode()),
        ]))]);
    }
}
