<?php

namespace App\Http\Requests;

use App\Support\PrivateUpload;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && ! $this->user()->isStaff() && (bool) $this->user()->customer_account_id;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('items') && is_array($this->input('service_codes'))) {
            $this->merge(['items' => array_map(fn ($code) => ['service_code' => $code, 'quantity' => 1], $this->input('service_codes'))]);
        }
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:200'], 'details' => ['required', 'string', 'min:10', 'max:10000'],
            'external_reference' => ['nullable', 'string', 'max:100'], 'consent' => ['required', 'accepted'],
            'items' => ['required', 'array', 'min:1', 'max:11'], 'items.*' => ['array:service_code,quantity'],
            'items.*.service_code' => ['required', 'string', 'distinct', 'exists:services,code'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
            'documents' => ['sometimes', 'array', 'max:5'],
            'documents.*' => PrivateUpload::rules(),
        ];
    }
}
