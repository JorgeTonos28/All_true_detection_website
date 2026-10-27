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
            $people = is_array($this->input('people')) ? $this->input('people') : [];
            $this->merge(['items' => array_map(fn ($code) => ['service_code' => $code, 'quantity' => $people && in_array($code, PersonVerification::PERSON_SERVICES, true) ? count(array_filter($people, fn ($person) => is_array($person) && in_array($code, (array) ($person['service_codes'] ?? []), true))) : 1], $this->input('service_codes'))]);
        }
    }

    public function rules(): array
    {
        $items = is_array($this->input('items')) ? $this->input('items') : [];
        $codes = PersonVerification::selected($items);
        $api = $this->is('api/*');
        $personRules = PersonVerification::rules($items);
        $people = is_array($this->input('people')) ? $this->input('people') : [];
        if ($this->has('people')) {
            $personRules = PersonVerification::peopleRules($people);
        } elseif (! $api && array_intersect(array_column($items, 'service_code'), PersonVerification::PERSON_SERVICES) && ! $this->has('person')) {
            $personRules = ['people' => ['required', 'array']];
        }
        $key = $api ? $this->header('Idempotency-Key') : $this->input('idempotency_key');
        if (! $this->has('people') && $codes && ! $this->filled('person') && is_string($key) && DB::table('idempotency_keys')
            ->where('user_id', $this->user()->id)->where('key', $key)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('order_people')->whereColumn('order_people.order_id', 'idempotency_keys.order_id'))->exists()) {
            // Only a matching hash in CreateOrder can return this existing, pre-profile case.
            $personRules = ['person' => ['prohibited']];
        }

        $evidenceRules = [];
        if (! $api && $people) {
            foreach ($people as $index => $person) {
                $selected = is_array($person) && is_array($person['service_codes'] ?? null) ? $person['service_codes'] : [];
                $required = PersonVerification::requiredEvidence($selected);
                $evidenceRules['person_evidence.'.$index] = ['sometimes', 'array:'.implode(',', PersonVerification::PURPOSES)];
                foreach (array_diff(PersonVerification::PURPOSES, ['supporting']) as $purpose) {
                    $evidenceRules['person_evidence.'.$index.'.'.$purpose] = array_merge([in_array($purpose, $required, true) ? 'required' : 'nullable'], array_slice(PrivateUpload::rules(), 1));
                }
            }
        }

        return [
            'subject' => ['required', 'string', 'max:200'], 'details' => ['nullable', 'string', 'max:10000'],
            'external_reference' => ['nullable', 'string', 'max:100'], 'consent' => ['required', 'accepted'],
            'items' => ['required', 'array', 'min:1', 'max:12'], 'items.*' => ['array:service_code,quantity'],
            'items.*.service_code' => ['required', 'string', 'distinct', 'exists:services,code'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
            'documents' => ['sometimes', 'array', 'max:5'],
            'documents.*' => PrivateUpload::rules(),
            'person_evidence' => $api ? ['prohibited'] : ['sometimes', 'array', 'max:10'],
            'evidence' => $api || $people ? ['prohibited'] : ['sometimes', 'array:'.implode(',', PersonVerification::PURPOSES)],
            'evidence.identity_front' => array_merge([! $api && ! $people && array_intersect($codes, ['university-degree', 'exequatur']) ? 'required' : 'nullable'], array_slice(PrivateUpload::rules(), 1)),
            'evidence.identity_back' => PrivateUpload::rules(false),
            'evidence.selfie' => array_merge([! $api && ! $people && in_array('identity-verification', $codes, true) ? 'required' : 'nullable'], array_slice(PrivateUpload::rules(), 1)),
            'evidence.selfie_extra' => PrivateUpload::rules(false),
            'evidence.degree_document' => array_merge([! $api && ! $people && in_array('university-degree', $codes, true) ? 'required' : 'nullable'], array_slice(PrivateUpload::rules(), 1)),
            'evidence.exequatur_document' => array_merge([! $api && ! $people && in_array('exequatur', $codes, true) ? 'required' : 'nullable'], array_slice(PrivateUpload::rules(), 1)),
        ] + $personRules + $evidenceRules;
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
            'evidence.degree_document.required' => 'Adjunte el título universitario escaneado en PDF o fotografiado.',
            'evidence.exequatur_document.required' => 'Adjunte el exequátur escaneado en PDF o fotografiado.',
            'people.required' => 'Agregue al menos una persona y asígnele los servicios solicitados.',
            'people.*.identity_number.prohibited' => 'No aporte cédulas para servicios que no las necesitan.',
            'people.*.identity_number.regex' => 'La cédula debe contener 11 dígitos, con o sin guiones.',
            'person_evidence.*.identity_front.required' => 'Adjunte el frente de la cédula para cada persona con título o exequátur.',
            'person_evidence.*.selfie.required' => 'Adjunte una selfie frontal para cada persona con verificación de identidad.',
            'person_evidence.*.degree_document.required' => 'Adjunte el título universitario de cada persona que solicite validarlo.',
            'person_evidence.*.exequatur_document.required' => 'Adjunte el exequátur de cada persona que solicite validarlo.',
        ];
    }

    public function attributes(): array
    {
        return ['person.first_name' => 'nombre', 'person.last_name' => 'apellido', 'person.identity_number' => 'cédula', 'person.address' => 'dirección', 'person.purpose' => 'finalidad', 'person.authorization_confirmed' => 'autorización'];
    }
}
