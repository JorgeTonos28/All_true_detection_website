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

    public const PURPOSES = ['supporting', 'identity_front', 'identity_back', 'selfie', 'selfie_extra'];

    public const LABELS = ['supporting' => 'Documento de apoyo', 'identity_front' => 'Cédula: frente', 'identity_back' => 'Cédula: reverso', 'selfie' => 'Selfie frontal', 'selfie_extra' => 'Selfie adicional'];

    public const OUTCOMES = ['verified' => 'Verificado', 'not_verified' => 'No verificado', 'inconclusive' => 'No concluyente'];

    public static function selected(array $items): array
    {
        return array_values(array_intersect(self::SERVICES, array_column($items, 'service_code')));
    }

    public static function rules(array $items): array
    {
        $codes = self::selected($items);
        if (! $codes) {
            return ['person' => ['prohibited']];
        }

        return [
            'person' => ['required', 'array:first_name,last_name,identity_number,address,birth_date,phone,email,purpose,authorization_confirmed,degree,exequatur'],
            'person.first_name' => ['required', 'string', 'max:100'],
            'person.last_name' => ['required', 'string', 'max:100'],
            // Format only. This does NOT assert authenticity or registry validity.
            'person.identity_number' => ['required', 'string', 'regex:/^[0-9]{3}-?[0-9]{7}-?[0-9]$/D'],
            'person.address' => ['required', 'string', 'max:1000'],
            'person.birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'person.phone' => ['nullable', 'string', 'max:40'],
            'person.email' => ['nullable', 'email:rfc', 'max:254'],
            'person.purpose' => ['required', 'string', 'min:5', 'max:1000'],
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
            $profile['identity_number'] = str_replace('-', '', $profile['identity_number']);
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
        if (! $codes || (str_starts_with($purpose, 'selfie') && ! in_array('identity-verification', $codes, true))) {
            throw ValidationException::withMessages(['purpose' => 'La categoría de evidencia no corresponde a los servicios de esta solicitud.']);
        }
        $extensions = str_starts_with($purpose, 'selfie') ? ['jpg', 'jpeg', 'png', 'webp'] : ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
        if (! in_array(strtolower($file->getClientOriginalExtension()), $extensions, true)) {
            throw ValidationException::withMessages(['file' => 'La cédula admite PDF o imágenes; las selfies deben ser JPG, PNG o WebP.']);
        }
    }

    public static function evidenceStatus(Order $order): ?array
    {
        if (! $order->person()->exists()) {
            return null; // Legacy orders remain operable; never invent a person profile.
        }
        $codes = self::selected($order->items()->with('service')->get()->map(fn ($item) => ['service_code' => $item->service->code])->all());
        $required = array_intersect($codes, ['university-degree', 'exequatur']) ? ['identity_front'] : [];
        if (in_array('identity-verification', $codes, true)) {
            $required[] = 'selfie';
        }
        $uploaded = $order->attachments()->pluck('purpose')->all();
        $missing = array_values(array_diff($required, $uploaded));

        return ['method' => 'manual', 'ready_for_review' => ! $missing, 'required' => $required, 'missing' => $missing];
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
        $codes = self::selected($order->items()->with('service')->get()->map(fn ($item) => ['service_code' => $item->service->code])->all());
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
