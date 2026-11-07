@extends('portal.layout')
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/identity-settings.css') }}">@endpush
@section('title','Mi negocio')
@section('subtitle','Administre la información comercial y las opciones de su portal.')
@section('content')
<nav class="integration-tabs" aria-label="Configuración del negocio">
@foreach(['brand'=>'Marca','contact'=>'Contacto y redes','content'=>'Contenido web','billing'=>'Precios y pagos','identity'=>'Verificación de identidad'] as $key=>$label)<a href="{{ route('portal.settings',['tab'=>$key]) }}" @if($tab===$key) aria-current="page" @endif>{{ $label }}</a>@endforeach
</nav>
<form method="post" enctype="multipart/form-data" action="{{ route('portal.settings.update',['tab'=>$tab]) }}" class="stack">@csrf @method('PUT')
<input type="hidden" name="section" value="{{ $tab }}">
@if($tab==='brand')
<section class="panel"><h2>Identidad visual</h2><div class="two-columns"><label>Nuevo logo<input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp"><small>JPG, PNG o WebP, hasta 2 MB.</small></label><label>Nuevo favicon<input type="file" name="favicon" accept=".png"><small>PNG, hasta 512 × 512 px y 512 KB.</small></label></div></section>
@endif
@if($tab==='billing')@include('portal.settings-billing')
@elseif($tab==='identity')@include('portal.settings-identity')
@else
@php($visibleGroups = ['brand'=>['Marca'],'contact'=>['Contacto','Redes'],'content'=>['Home','SEO']][$tab])
@foreach($settings as $group=>$items)@continue(!in_array($group,$visibleGroups,true))
<section class="panel"><h2>{{ $group === 'Home' ? 'Contenido de inicio' : $group }}</h2><div class="form-grid">@foreach($items as $setting)@continue($setting->type === 'file')<label>{{ $setting->label }}@if($setting->type === 'textarea')<textarea name="{{ $setting->key }}" rows="4" maxlength="5000">{{ old($setting->key,$setting->value) }}</textarea>@else<input name="{{ $setting->key }}" type="{{ str_ends_with($setting->key,'_url') ? 'url' : ($setting->key === 'email' ? 'email' : 'text') }}" value="{{ old($setting->key,$setting->value) }}" maxlength="{{ str_ends_with($setting->key,'_url') ? 500 : 5000 }}" @required($setting->key === 'company_name')>@endif</label>@endforeach</div></section>
@endforeach
@endif
<div class="actions"><button>Guardar cambios</button><a class="button secondary" href="{{ route('home') }}">Ver sitio web</a></div>
</form>
@endsection
@push('scripts')<script src="{{ asset('assets/js/business-settings.js') }}" defer></script>@endpush
