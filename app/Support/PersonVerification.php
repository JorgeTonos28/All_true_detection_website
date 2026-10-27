<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersonVerification
{
    public const SERVICES = ['university-degree', 'exequatur', 'identity-verification'];

    public const PERSON_SERVICES = ['polygraph', 'deception-detection', 'profile-verification', 'applied-ai', 'home-visits', 'investigations', 'drug-screening', 'psychometric', 'university-degree', 'exequatur', 'identity-verification'];

    public const PURPOSES = ['supporting', 'identity_front', 'identity_back', 'selfie', 'selfie_extra', 'degree_document', 'exequatur_document'];

    public const LABELS = ['supporting' => 'Documento de apoyo', 'identity_front' => 'Cédula: frente', 'identity_back' => 'Cédula: reverso', 'selfie' => 'Selfie frontal', 'selfie_extra' => 'Selfie adicional', 'degree_document' => 'Título universitario escaneado o fotografiado', 'exequatur_document' => 'Exequátur escaneado o fotografiado'];

    public const OUTCOMES = ['verified' => 'Verificado', 'not_verified' => 'No verificado', 'inconclusive' => 'No concluyente'];

    public static function selected(array $items): array
    {
        return array_values(array_intersect(self::SERVICES, array_filter(array_column($items, 'service_code'), 'is_string')));
    }

    public static function rules(array $items): array
    {
        $codes = self::selected($items);
        if (! $codes) {
            return ['person' => ['prohibited']];
        }

        return self::profileRules($codes);
    }

    public static function profileRules(array $codes, string $prefix = 'person'): array
    {
        $codes = array_values(array_filter($codes, 'is_string'));
        $identity = (bool) array_intersect($codes, self::SERVICES);
        $address = $identity || in_array('home-visits', $codes, true);
        $rules = [
            'person' => ['required', 'array:first_name,last_name,identity_number,address,birth_date,phone,email,purpose,authorization_confirmed,degree,exequatur'.($prefix !== 'person' ? ',service_codes' : '')],
            'person.first_name' => ['required', 'string', 'max:100'],
            'person.last_name' => ['required', 'string', 'max:100'],
            // Format only. This does NOT assert authenticity or registry validity.
            'person.identity_number' => [$identity ? 'required' : 'prohibited', 'string', 'regex:/^[0-9]{3}-?[0-9]{7}-?[0-9]$/D'],
            'person.address' => [$address ? 'required' : 'nullable', 'string', 'max:1000'],
            'person.birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'person.phone' => ['nullable', 'string', 'max:40'],
            'person.email' => ['nullable', 'email:rfc', 'max:254'],
            'person.purpose' => [$identity ? 'required' : 'nullable', 'string', 'min:5', 'max:1000'],
            'person.authorization_confirmed' => ['required', 'accepted'],
            'person.degree' => [in_array('university-degree', $codes, true) ? 'required' : 'prohibited', 'array:institution,degree_name,graduation_year,registration_number'],
            'person.degree.institution' => ['required_with:person.degree', 'string', 'max:200'],
            'person.degree.degree_name' => ['required_with:person.degree', 'string', 'max:200'],
            'person.degree.graduation_year' => ['nullable', 'integer', 'min:1900', 'max:'.date('Y')],
            'person.degree.registration_number' => ['nullable', 'string', 'max:100'],
            'person.exequatur' => [in_array('exequatur', $codes, true) ? 'required' : 'prohibited', 'array:profession,number,issuing_authority,issued_on'],
            'person.exequatur.profession' => ['required_with:person.exequatur', 'string', 'max:200'],
            'person.exequatur.number' => ['nullable', 'string', 'max:100'],
            'person.exequatur.issuing_authority' => ['nullable', 'string', 'max:200'],
            'person.exequatur.issued_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];

        return collect($rules)->mapWithKeys(fn ($rule, $key) => [str_replace('person', $prefix, $key) => array_map(fn ($value) => is_string($value) ? str_replace('person.', $prefix.'.', $value) : $value, $rule)])->all();
    }

    public static function peopleRules(array $people): array
    {
        $rules = ['people' => ['required', 'array', 'list', 'min:1', 'max:10'], 'person' => ['prohibited']];
        foreach (array_slice($people, 0, 10, true) as $index => $person) {
            if (! is_int($index)) {
                continue;
            }
            $codes = is_array($person) && is_array($person['service_codes'] ?? null) ? $person['service_codes'] : [];
            $rules += self::profileRules($codes, 'people.'.$index);
            $rules['people.'.$index.'.service_codes'] = ['required', 'array', 'list', 'min:1', 'max:11'];
            $rules['people.'.$index.'.service_codes.*'] = ['required', 'string', 'distinct', Rule::in(self::PERSON_SERVICES)];
        }

        return $rules;
    }

    public static function people(array $data): array
    {
        if (! array_key_exists('people', $data)) {
            return [];
        }
        $input = is_array($data['people']) ? $data['people'] : [];
        $people = Validator::make($data, self::peopleRules($input))->validate()['people'];
        $quantities = [];
        $identities = [];
        foreach ($people as $index => &$person) {
            foreach ($person['service_codes'] as $code) {
                $quantities[$code] = ($quantities[$code] ?? 0) + 1;
            }
            if (! empty($person['identity_number'])) {
                $person['identity_number'] = str_replace('-', '', $person['identity_number']);
                if (in_array($person['identity_number'], $identities, true)) {
                    throw ValidationException::withMessages(['people.'.$index.'.identity_number' => 'Esta cédula ya figura en otra persona de la solicitud. Combine sus servicios en una sola ficha.']);
                }
                $identities[] = $person['identity_number'];
            }
            $person['authorization_confirmed'] = true;
            sort($person['service_codes']);
        }
        unset($person);
        foreach ($data['items'] as $index => $item) {
            if (in_array($item['service_code'], self::PERSON_SERVICES, true)) {
                if (($quantities[$item['service_code']] ?? 0) !== (int) $item['quantity']) {
                    throw ValidationException::withMessages(['items.'.$index.'.quantity' => 'La cantidad debe coincidir con las personas que reciben este servicio.']);
                }
                unset($quantities[$item['service_code']]);
            }
        }
        if ($quantities) {
            throw ValidationException::withMessages(['people' => 'Los servicios de cada persona deben pertenecer a la solicitud.']);
        }

        return $people;
    }

    public static function profile(array $data): ?array
    {
        $items = $data['items'];
        $validated = Validator::make(['person' => $data['person'] ?? null], self::rules($items))->validate();
        foreach ($items as $index => $item) {
            if (in_array($item['service_code'], self::SERVICES, true) && (int) $item['quantity'] !== 1) {
                throw ValidationException::withMessages(['items.'.$index.'.quantity' => 'Cada solicitud corresponde a una persona; use cantidad 1 y una orden distinta por cada persona.']);
            }
        }
        $profile = $validated['person'] ?? null;
        if ($profile) {
            if (isset($profile['identity_number'])) {
                $profile['identity_number'] = str_replace('-', '', $profile['identity_number']);
            }
            $profile['authorization_confirmed'] = true;
        }

        return $profile;
    }

    public static function validateEvidence(array $codes, string $purpose, UploadedFile $file): void
    {
        Validator::make(['purpose' => $purpose], ['purpose' => ['required', Rule::in(self::PURPOSES)]])->validate();
        if ($purpose === 'supporting') {
            return;
        }
        $service = match ($purpose) {
            'degree_document' => 'university-degree', 'exequatur_document' => 'exequatur',
            'selfie', 'selfie_extra' => 'identity-verification', default => null,
        };
        if (! array_intersect($codes, self::SERVICES) || ($service && ! in_array($service, $codes, true))) {
            throw ValidationException::withMessages(['purpose' => 'La categoría de evidencia no corresponde a los servicios de esta solicitud.']);
        }
        $extensions = str_starts_with($purpose, 'selfie') ? ['jpg', 'jpeg', 'png', 'webp'] : ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
        if (! in_array(strtolower($file->getClientOriginalExtension()), $extensions, true)) {
            throw ValidationException::withMessages(['file' => 'La cédula admite PDF o imágenes; las selfies deben ser JPG, PNG o WebP.']);
        }
    }

    public static function evidenceStatus(Order $order): ?array
    {
        $people = $order->people()->with('services')->get();
        if ($people->isEmpty()) {
            return null; // Legacy orders remain operable; never invent a person profile.
        }
        $statuses = [];
        foreach ($people as $person) {
            $required = self::requiredEvidence($person->services->pluck('code')->all(), $person->evidence_version);
            $uploaded = $order->attachments()->where(fn ($q) => $q->where('order_person_id', $person->id)->when($people->count() === 1, fn ($q) => $q->orWhereNull('order_person_id')))->pluck('purpose')->all();
            $missing = array_values(array_diff($required, $uploaded));
            $statuses[] = ['person_id' => $person->public_id, 'ready_for_review' => ! $missing, 'required' => $required, 'missing' => $missing];
        }

        return ['method' => 'manual', 'ready_for_review' => ! collect($statuses)->contains('ready_for_review', false), 'required' => array_values(array_unique(array_merge(...array_column($statuses, 'required')))), 'missing' => array_values(array_unique(array_merge(...array_column($statuses, 'missing')))), 'people' => $statuses];
    }

    public static function requiredEvidence(array $codes, int $version = 2): array
    {
        $codes = array_filter($codes, 'is_string');
        $required = array_intersect($codes, ['university-degree', 'exequatur']) ? ['identity_front'] : [];
        if (in_array('identity-verification', $codes, true)) {
            $required[] = 'selfie';
        }
        if ($version >= 2) {
            if (in_array('university-degree', $codes, true)) {
                $required[] = 'degree_document';
            }
            if (in_array('exequatur', $codes, true)) {
                $required[] = 'exequatur_document';
            }
        }

        return $required;
    }

    public static function distinctDocuments(array $files, array $existingHashes = []): void
    {
        $hashes = $existingHashes;
        foreach ($files as $file) {
            if (in_array($file['sha256'], $hashes, true)) {
                throw ValidationException::withMessages(['documents' => 'El mismo archivo está repetido. El frente, el reverso, las selfies y cada documento deben ser archivos diferentes, incluso si cambia su nombre.']);
            }
            $hashes[] = $file['sha256'];
        }
    }

    public static function requireEvidence(Order $order): void
    {
        $status = self::evidenceStatus($order);
        if ($status && ! $status['ready_for_review']) {
            throw ValidationException::withMessages(['evidence' => 'Falta evidencia obligatoria: '.implode(', ', array_map(fn ($key) => self::LABELS[$key], $status['missing'])).'.']);
        }
    }

    public static function findings(Order $order, array $findings): ?array
    {
        if (! $order->person()->exists()) {
            Validator::make(['findings' => $findings], ['findings' => ['prohibited']])->validate();

            return null;
        }
        self::requireEvidence($order);
        $people = $order->people()->with('services')->get();
        if ($people->count() > 1 || ($people->count() === 1 && array_key_exists($people->first()->public_id, $findings))) {
            $rules = ['findings' => ['required', 'array:'.implode(',', $people->pluck('public_id')->all())]];
            foreach ($people as $person) {
                $prefix = 'findings.'.$person->public_id;
                $codes = $person->services->pluck('code')->all();
                $rules[$prefix] = ['required', 'array:'.implode(',', $codes)];
                foreach ($codes as $code) {
                    $rules[$prefix.'.'.$code] = ['required', 'array:outcome,notes'];
                    $rules[$prefix.'.'.$code.'.outcome'] = ['required', Rule::in(array_keys(self::OUTCOMES))];
                    $rules[$prefix.'.'.$code.'.notes'] = ['required', 'string', 'min:5', 'max:5000'];
                }
            }
            $validated = Validator::make(['findings' => $findings], $rules)->validate()['findings'];
            $checks = [];
            foreach ($validated as $id => $services) {
                foreach ($services as $code => $check) {
                    $checks[] = ['person_id' => $id, 'service_code' => $code] + $check;
                }
            }

            return ['method' => 'manual', 'reviewed_at' => now()->toIso8601String(), 'checks' => $checks];
        }
        $codes = $people->first()->services->pluck('code')->all();
        $rules = ['findings' => ['required', 'array:'.implode(',', $codes)]];
        foreach ($codes as $code) {
            $rules['findings.'.$code] = ['required', 'array:outcome,notes'];
            $rules['findings.'.$code.'.outcome'] = ['required', Rule::in(array_keys(self::OUTCOMES))];
            $rules['findings.'.$code.'.notes'] = ['required', 'string', 'min:5', 'max:5000'];
        }
        $validated = Validator::make(['findings' => $findings], $rules)->validate()['findings'];

        return ['method' => 'manual', 'reviewed_at' => now()->toIso8601String(), 'checks' => collect($validated)->map(fn ($check, $code) => ['service_code' => $code] + $check)->values()->all()];
    }
}
