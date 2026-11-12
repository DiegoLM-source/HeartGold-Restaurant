
@extends('layouts.cliente')

@section('title', 'Escanear QR | Restaurante')
@section('heading', 'Identificar mesa')

@section('content')

<div class="qr-page">

    <div class="qr-intro">
        <span class="cliente-eyebrow">ACCESO AL RESTAURANTE</span>
        <h2>Escanea el código QR de tu mesa</h2>
        <p>
            Apunta la cámara hacia el código QR que se encuentra en tu mesa
            para acceder a tu sesión.
        </p>
    </div>

    <div class="qr-card">

        <div class="qr-card-heading">
            <div class="qr-icon">▦</div>
            <div>
                <h3>Escáner de mesa</h3>
                <p>Ubica el código QR dentro del recuadro.</p>
            </div>
        </div>

        <div id="qr-reader"></div>

        <p id="qr-message" class="qr-message">
            Presiona iniciar para activar la cámara.
        </p>

        <button type="button" id="qr-start" class="qr-button">
            Activar cámara
        </button>

        <button type="button" id="qr-stop" class="qr-button qr-button-secondary" hidden>
            Detener cámara
        </button>

        <div class="qr-divider">
            <span>O ingresa el token manualmente</span>
        </div>

        <form action="{{ route('cliente.mesa.unirse') }}" method="POST">
            @csrf

            <label for="qr-token">Código de la sesión</label>

            <input
                type="text"
                name="token"
                id="qr-token"
                value="{{ old('token') }}"
                placeholder="Pega aquí el token QR"
                required
            >

            @error('token')
                <span class="qr-error">{{ $message }}</span>
            @enderror

            @error('qr')
                <span class="qr-error">{{ $message }}</span>
            @enderror

            <button type="submit" class="qr-button">
                Unirme a la mesa
            </button>
        </form>

    </div>
</div>

@endsection
