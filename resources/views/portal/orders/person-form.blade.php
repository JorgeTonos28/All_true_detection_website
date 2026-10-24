@php($needsPerson = (bool) array_intersect($selectedCodes, \App\Support\PersonVerification::SERVICES))
<fieldset class="verification-fields stack" data-verification-fields="person" @disabled(!$needsPerson) @if(!$needsPerson) hidden @endif>
    <legend>Persona que será verificada</legend>
    <p class="muted">Una solicitud por persona. Puede combinar título, exequátur e identidad para la misma persona. Los campos marcados con * son obligatorios.</p>
    <div class="form-grid">
        <label>Nombre(s) *<input name="person[first_name]" value="{{ old('person.first_name') }}" maxlength="100" autocomplete="off" required></label>
        <label>Apellido(s) *<input name="person[last_name]" value="{{ old('person.last_name') }}" maxlength="100" autocomplete="off" required></label>
        <label>Cédula dominicana *<input name="person[identity_number]" value="{{ old('person.identity_number') }}" inputmode="numeric" maxlength="13" pattern="[0-9]{3}-?[0-9]{7}-?[0-9]" autocomplete="off" required><small>11 dígitos, con o sin guiones. No use el nombre de la persona en el título de la solicitud.</small></label>
        <label>Fecha de nacimiento (opcional)<input type="date" name="person[birth_date]" value="{{ old('person.birth_date') }}" max="{{ now()->format('Y-m-d') }}" autocomplete="off"></label>
    </div>
    <label>Dirección de la persona *<textarea name="person[address]" rows="2" maxlength="1000" autocomplete="off" required>{{ old('person.address') }}</textarea></label>
    <div class="form-grid">
        <label>Teléfono de la persona (opcional)<input name="person[phone]" value="{{ old('person.phone') }}" maxlength="40" autocomplete="off"></label>
        <label>Correo de la persona (opcional)<input type="email" name="person[email]" value="{{ old('person.email') }}" maxlength="254" autocomplete="off"></label>
    </div>
    <label>Finalidad autorizada de la verificación *<textarea name="person[purpose]" rows="2" minlength="5" maxlength="1000" required>{{ old('person.purpose') }}</textarea><small>Explique para qué necesita la verificación. Aporte únicamente información pertinente y autorizada.</small></label>
    <fieldset class="verification-subfields stack" data-verification-fields="university-degree" @disabled(!in_array('university-degree',$selectedCodes)) @if(!in_array('university-degree',$selectedCodes)) hidden @endif>
        <legend>Título universitario</legend>
        <div class="form-grid">
            <label>Universidad / institución *<input name="person[degree][institution]" value="{{ old('person.degree.institution') }}" maxlength="200" required></label>
            <label>Nombre del título *<input name="person[degree][degree_name]" value="{{ old('person.degree.degree_name') }}" maxlength="200" required></label>
            <label>Año de graduación (opcional)<input type="number" name="person[degree][graduation_year]" value="{{ old('person.degree.graduation_year') }}" min="1900" max="{{ date('Y') }}"></label>
            <label>Matrícula / registro (opcional)<input name="person[degree][registration_number]" value="{{ old('person.degree.registration_number') }}" maxlength="100"></label>
        </div>
    </fieldset>
    <fieldset class="verification-subfields stack" data-verification-fields="exequatur" @disabled(!in_array('exequatur',$selectedCodes)) @if(!in_array('exequatur',$selectedCodes)) hidden @endif>
        <legend>Exequátur</legend>
        <div class="form-grid">
            <label>Profesión *<input name="person[exequatur][profession]" value="{{ old('person.exequatur.profession') }}" maxlength="200" required></label>
            <label>Número de exequátur (opcional)<input name="person[exequatur][number]" value="{{ old('person.exequatur.number') }}" maxlength="100"></label>
            <label>Entidad emisora (opcional)<input name="person[exequatur][issuing_authority]" value="{{ old('person.exequatur.issuing_authority') }}" maxlength="200"></label>
            <label>Fecha de expedición (opcional)<input type="date" name="person[exequatur][issued_on]" value="{{ old('person.exequatur.issued_on') }}" max="{{ now()->format('Y-m-d') }}"></label>
        </div>
    </fieldset>
    <div class="evidence-intro"><h3>Evidencia privada</h3><p class="muted">Se admite escaneo o foto legible desde el celular. Cédula: PDF, JPG, PNG o WebP. Selfies: solo imágenes. No suba audio, vídeo, HEIC ni documentos de otras personas.</p></div>
    <div class="form-grid">
        <label>Frente de la cédula<input type="file" name="evidence[identity_front]" accept=".pdf,.jpg,.jpeg,.png,.webp" data-evidence-required="university-degree,exequatur" @required((bool)array_intersect($selectedCodes,['university-degree','exequatur']))><small>Obligatorio para título/exequátur; opcional si solo solicita identidad. Una copia sirve para ambos servicios.</small></label>
        <label>Reverso de la cédula (opcional)<input type="file" name="evidence[identity_back]" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
    </div>
    <fieldset class="verification-subfields stack" data-verification-fields="identity-verification" @disabled(!in_array('identity-verification',$selectedCodes)) @if(!in_array('identity-verification',$selectedCodes)) hidden @endif>
        <legend>Verificación de identidad con revisión humana</legend>
        <p class="info-strip">Un profesional de ATD revisará las imágenes y registrará su conclusión. Este servicio no incluye prueba de vida con movimiento ni cotejo biométrico automático.</p>
        <div class="form-grid">
            <label>Selfie frontal *<input type="file" name="evidence[selfie]" accept=".jpg,.jpeg,.png,.webp" required><small>Rostro completo y buena iluminación, sin filtros. Puede tomarla con el celular y seleccionar la foto.</small></label>
            <label>Segunda selfie (opcional)<input type="file" name="evidence[selfie_extra]" accept=".jpg,.jpeg,.png,.webp"></label>
        </div>
    </fieldset>
    <label class="check"><input type="checkbox" name="person[authorization_confirmed]" value="1" required @checked(old('person.authorization_confirmed'))>Confirmo que cuento con autorización de esta persona para aportar sus datos, cédula y fotografías con la finalidad indicada.</label>
</fieldset>
