<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Support\PersonVerification;

class ServiceController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Service::where('is_active', true)->orderBy('sort_order')->get(['code', 'name', 'description'])->map(fn ($service) => $service->toArray() + [
            'per_person' => in_array($service->code, PersonVerification::PERSON_SERVICES, true),
            'requires_identity_number' => in_array($service->code, PersonVerification::SERVICES, true),
            'required_evidence' => PersonVerification::requiredEvidence([$service->code]),
        ])]);
    }
}
