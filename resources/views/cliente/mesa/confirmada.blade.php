
@extends('layouts.cliente')

@section('title', 'Mesa confirmada | Restaurante')
@section('heading', 'Mesa identificada')

@section('content')

<div class="qr-confirmation">

    <div class="qr-confirmation-icon">✓</div>

    <span class="cliente-eyebrow">CONEXIÓN EXITOSA</span>

    <h2>¡Ya estás en tu mesa!</h2>

    <p>
        Tu cuenta se ha asociado correctamente a la sesión del restaurante.
    </p>

    <div class="qr-table-info">
        <span>MESA ASIGNADA</span>

        <strong>
            {{ session('cliente_mesa_nombre', 'Mesa') }}
        </strong>

        <small>
            Sesión activa
        </small>
    </div>

    <a href="{{ route('cliente.dashboard') }}" class="qr-button">
        Ir al inicio
    </a>

</div>

@endsection
