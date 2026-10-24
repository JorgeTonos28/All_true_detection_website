<form method="post" action="{{ route('contact.store') }}" class="public-contact-form" data-public-contact>
    @csrf
    <div data-contact-feedback class="contact-feedback" role="status" aria-live="polite" hidden></div>
    <label>Nombre<input name="name" value="{{ old('name') }}" maxlength="120" autocomplete="name" required></label>
    <label>Correo<input type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required></label>
    <label>Asunto<input name="subject" value="{{ old('subject') }}" maxlength="200" required></label>
    <label>Mensaje<textarea name="body" minlength="10" maxlength="10000" rows="4" required>{{ old('body') }}</textarea></label>
    <input type="text" name="website" tabindex="-1" autocomplete="off" class="contact-honeypot" aria-hidden="true">
    <label class="contact-consent"><input type="checkbox" name="consent" value="1" required> Acepto el <a href="{{ route('privacy') }}">aviso de privacidad</a>.</label>
    <button class="btn btn-primary" type="submit">Enviar consulta</button>
</form>
