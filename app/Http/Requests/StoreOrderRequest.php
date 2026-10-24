<?php

namespace App\Http\Requests;

use App\Support\PersonVerification;
use App\Support\PrivateUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

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
        $items = is_array($this->input('items')) ? $this->input('items') : [];
        $codes = PersonVerification::selected($items);
        $api = $this->is('api/*');
        $personRules = PersonVerification::rules($items);
        $key = $api ? $this->header('Idempotency-Key') : $this->input('idempotency_key');
        if ($codes && ! $this->filled('person') && is_string($key) && DB::table('idempotency_keys')
            ->where('user_id', $this->user()->id)->where('key', $key)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('order_people')->whereColumn('order_people.order_id', 'idempotency_keys.order_id'))->exists()) {
            // Only a matching hash in CreateOrder can return this existing, pre-profile case.
            $personRules = ['person' => ['prohibited']];
        }

        return [
            'subject' => ['required', 'string', 'max:200'], 'details' => ['required', 'string', 'min:10', 'max:10000'],
            'external_reference' => ['nullable', 'string', 'max:100'], 'consent' => ['required', 'accepted'],
            'items' => ['required', 'array', 'min:1', 'max:12'], 'items.*' => ['array:service_code,quantity'],
            'items.*.service_code' => ['required', 'string', 'distinct', 'exists:services,code'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
            'documents' => ['sometimes', 'array', 'max:5'],
            'documents.*' => PrivateUpload::rules(),
            'evidence' => $api ? ['prohibited'] : ['sometimes', 'array:identity_front,identity_back,selfie,selfie_extra'],
            'evidence.identity_front' => array_merge([! $api && array_intersect($codes, ['university-degree', 'exequatur']) ? 'required' : 'nullable'], array_slice(PrivateUpload::rules(), 1)),
            'evidence.identity_back' => PrivateUpload::rules(false),
            'evidence.selfie' => array_merge([! $api && in_array('identity-verification', $codes, true) ? 'required' : 'nullable'], array_slice(PrivateUpload::rules(), 1)),
            'evidence.selfie_extra' => PrivateUpload::rules(false),
        ] + $personRules;
    }

    public function messages(): array
    {
        return [
            'person.required' => 'Complete la ficha de la persona que será verificada.',
            'person.*.required' => 'Complete el campo :attribute de la persona.',
            'person.identity_number.regex' => 'La cédula debe contener 11 dígitos, con o sin guiones.',
            'person.authorization_confirmed.accepted' => 'Confirme la autorización de la persona para tratar sus datos e imágenes.',
            'person.degree.required' => 'Complete los datos del título universitario.',
            'person.exequatur.required' => 'Complete los datos del exequátur.',
            'evidence.identity_front.required' => 'Adjunte el frente de la cédula para la validación de título o exequátur.',
            'evidence.selfie.required' => 'Adjunte una selfie frontal para la verificación de identidad.',
        ];
    }

    public function attributes(): array
    {
        return ['person.first_name' => 'nombre', 'person.last_name' => 'apellido', 'person.identity_number' => 'cédula', 'person.address' => 'dirección', 'person.purpose' => 'finalidad', 'person.authorization_confirmed' => 'autorización'];
    }
}
