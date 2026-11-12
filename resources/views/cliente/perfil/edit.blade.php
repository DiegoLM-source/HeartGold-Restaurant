@extends('layouts.cliente')

@section('title', 'Mi perfil | Restaurante')
@section('heading', 'Mi perfil')

@section('content')
<div class="cliente-page-heading">
    <div><span class="cliente-eyebrow">MI CUENTA</span><h2>Datos personales</h2><p>Actualiza el nombre y teléfono vinculados a tu cuenta.</p></div>
</div>

@if ($error)<div class="cliente-alert cliente-alert-error">{{ $error }}</div>@endif
@if (session('success'))<div class="cliente-alert cliente-alert-success">{{ session('success') }}</div>@endif
@if ($errors->has('perfil'))<div class="cliente-alert cliente-alert-error">{{ $errors->first('perfil') }}</div>@endif

<form class="cliente-profile-form" method="POST" action="{{ route('cliente.perfil.update') }}">
    @csrf
    @method('PUT')
    <label for="full_name">Nombre completo</label>
    <input id="full_name" name="full_name" value="{{ old('full_name', $perfil['full_name']) }}" required minlength="3" maxlength="150" autocomplete="name">
    @error('full_name')<span class="cliente-field-error">{{ $message }}</span>@enderror

    <label for="phone">Teléfono</label>
    <input id="phone" name="phone" value="{{ old('phone', $perfil['phone']) }}" required maxlength="30" autocomplete="tel">
    @error('phone')<span class="cliente-field-error">{{ $message }}</span>@enderror

    <button type="submit">Guardar cambios</button>
</form>
@endsection
