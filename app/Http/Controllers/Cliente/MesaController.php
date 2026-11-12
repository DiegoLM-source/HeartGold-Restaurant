<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MesaController extends Controller
{
    public function escanear()
    {
        return view('cliente.mesa.escanear');
    }

    public function unirse(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        $token = trim($request->token);

        $supabaseUrl = config('services.supabase.url');
        $supabaseKey = config('services.supabase.key');
        $accessToken = session('supabase_access_token');

        if (!$accessToken || !$supabaseUrl || !$supabaseKey) {
            return back()->withErrors([
                'qr' => 'No se pudo validar tu sesión. Inicia sesión nuevamente.'
            ]);
        }

        $response = Http::acceptJson()
            ->withToken($accessToken)
            ->withHeaders([
                'apikey' => $supabaseKey,
            ])
            ->timeout(20)
            ->post(
                rtrim($supabaseUrl, '/') . '/rest/v1/rpc/unirse_sesion',
                [
                    'p_token_qr' => $token,
                ]
            );

        if (!$response->successful()) {
            $mensaje = $response->json('message')
                ?? 'No fue posible unirse a esta mesa.';

            return back()->withErrors([
                'qr' => $mensaje
            ])->withInput();
        }

        $resultado = $response->json();

        $sesion = is_array($resultado) && array_is_list($resultado)
            ? ($resultado[0] ?? null)
            : $resultado;

        if (
            !is_array($sesion) ||
            empty($sesion['sesion_id']) ||
            empty($sesion['mesa_id'])
        ) {
            return back()->withErrors([
                'qr' => 'Supabase no devolvió los datos esperados de la sesión.'
            ]);
        }

        session([
            'cliente_sesion_id' => $sesion['sesion_id'],
            'cliente_mesa_id' => $sesion['mesa_id'],
            'cliente_mesa_nombre' => $sesion['nombre_mesa'],
        ]);

        return redirect()
            ->route('cliente.mesa.confirmada')
            ->with('success', 'Te has unido correctamente a la mesa.');
    }

    public function confirmada()
    {
        if (!session()->has('cliente_sesion_id')) {
            return redirect()->route('cliente.mesa.escanear');
        }

        return view('cliente.mesa.confirmada');
    }
}
