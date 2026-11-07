@extends('portal.layout')
@section('title','Gestionar usuario')
@section('subtitle','Revise la identidad, los permisos y el estado de acceso.')
@section('actions')<a class="button secondary" href="{{ route('portal.users.index') }}">Volver al directorio</a>@endsection
@section('content')<section class="panel member-page">@include('portal.users.manage')</section>@endsection
