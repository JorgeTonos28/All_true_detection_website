@extends('portal.layout')
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/identity-settings.css') }}">@endpush
@section('title','Mi negocio')
@section('subtitle','Identidad, contacto y contenido de su sitio web.')
@section('content')
<form method="post" enctype="multipart/form-data" action="{{ route('portal.settings.update') }}" class="stack">@csrf @method('PUT')
<section class="panel"><h2>Identidad visual</h2><div class="two-columns"><label>Nuevo logo<input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp"><small>JPG, PNG o WebP, hasta 2 MB. Para el encabezado oscuro, use una versión clara.</small></label><label>Nuevo favicon<input type="file" name="favicon" accept=".png"><small>PNG, hasta 512 × 512 px y 512 KB.</small></label></div></section>
<section class="panel stack identity-settings" aria-labelledby="identity-settings-title">
    <div class="panel-heading"><div><p class="eyebrow">Integración de identidad</p><h2 id="identity-settings-title">Revisión manual o captura automatizada</h2></div><span class="badge">{{ $identityMode === 'didit' ? 'Didit activo' : 'Manual activo' }}</span></div>
    <input type="hidden" name="identity_verification_mode" value="manual">
    <label class="identity-switch"><input type="checkbox" role="switch" name="identity_verification_mode" value="didit" @checked(old('identity_verification_mode',$identityMode)==='didit') @disabled(!$identityReady)><span><strong>Automatizar nuevas verificaciones con Didit</strong><small>Documento + prueba de vida + comparación facial. Apagar conserva el flujo de revisión humana de ATD.</small></span></label>
    <p class="muted">El cambio se aplica al guardar y solo a solicitudes nuevas. Las sesiones ya iniciadas continúan su conciliación. El texto del servicio en la web y los requisitos del portal/API se ajustan al modo activo. El pago y la publicación siguen bajo control de ATD; los casos no concluyentes pueden necesitar revisión.</p>
    @unless($identityReady)<p class="alert">Automatización no disponible todavía. Configure las variables DIDIT_* y las aprobaciones de privacidad/producción indicadas en el runbook. Las claves nunca se introducen ni se muestran en este formulario.</p>@endunless
    @if(config('identity.didit.environment')==='sandbox')<p class="info-strip">Entorno Didit: sandbox. Las comprobaciones son simuladas; no usar como verificaciones reales.</p>@endif
    <dl class="integration-facts"><dt>Proveedor</dt><dd>Didit · API v3 · enlace alojado (compatible con SDK/iframe del integrador)</dd><dt>Configuración</dt><dd>{{ $identityReady ? 'Completa en este entorno; requiere prueba integral antes de producción' : 'Pendiente' }}</dd><dt>Última conciliación</dt><dd>{{ $identityLastSync ?: 'Todavía no hay sesiones conciliadas' }}</dd><dt>Sesiones locales</dt><dd>{{ $identityCounts->sum() }} registradas · {{ $identityCounts['pending'] ?? 0 }} pendientes · {{ ($identityCounts['create_uncertain'] ?? 0) + ($identityCounts['needs_attention'] ?? 0) }} requieren atención operativa</dd></dl>
</section>
@foreach($settings as $group=>$items)<section class="panel"><h2>{{ $group === 'Home' ? 'Contenido de inicio' : $group }}</h2><div class="form-grid">@foreach($items as $setting)@continue($setting->type === 'file')<label>{{ $setting->label }}@if($setting->type === 'textarea')<textarea name="{{ $setting->key }}" rows="4" maxlength="5000">{{ old($setting->key,$setting->value) }}</textarea>@else<input name="{{ $setting->key }}" type="{{ str_ends_with($setting->key,'_url') ? 'url' : ($setting->key === 'email' ? 'email' : 'text') }}" value="{{ old($setting->key,$setting->value) }}" maxlength="{{ str_ends_with($setting->key,'_url') ? 500 : 5000 }}" @required($setting->key === 'company_name')>@endif</label>@endforeach</div></section>@endforeach
<div class="actions"><button>Guardar información</button><a class="button secondary" href="{{ route('home') }}">Ver sitio web</a></div>
</form>
@endsection
