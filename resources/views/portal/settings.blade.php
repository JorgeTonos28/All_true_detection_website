@extends('portal.layout')
@section('title','Mi negocio')
@section('subtitle','Identidad, contacto y contenido de su sitio web.')
@section('content')
<form method="post" enctype="multipart/form-data" action="{{ route('portal.settings.update') }}" class="stack">@csrf @method('PUT')
<section class="panel"><h2>Identidad visual</h2><div class="two-columns"><label>Nuevo logo<input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp"><small>JPG, PNG o WebP, hasta 2 MB. Para el encabezado oscuro, use una versión clara.</small></label><label>Nuevo favicon<input type="file" name="favicon" accept=".png"><small>PNG, hasta 512 × 512 px y 512 KB.</small></label></div></section>
@foreach($settings as $group=>$items)<section class="panel"><h2>{{ $group === 'Home' ? 'Contenido de inicio' : $group }}</h2><div class="form-grid">@foreach($items as $setting)@continue($setting->type === 'file')<label>{{ $setting->label }}@if($setting->type === 'textarea')<textarea name="{{ $setting->key }}" rows="4" maxlength="5000">{{ old($setting->key,$setting->value) }}</textarea>@else<input name="{{ $setting->key }}" type="{{ str_ends_with($setting->key,'_url') ? 'url' : ($setting->key === 'email' ? 'email' : 'text') }}" value="{{ old($setting->key,$setting->value) }}" maxlength="{{ str_ends_with($setting->key,'_url') ? 500 : 5000 }}" @required($setting->key === 'company_name')>@endif</label>@endforeach</div></section>@endforeach
<div class="actions"><button>Guardar información</button><a class="button secondary" href="{{ route('home') }}">Ver sitio web</a></div>
</form>
@endsection
