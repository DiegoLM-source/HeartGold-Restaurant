<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PerfilController extends Controller
{
    public function edit()
    {
        $perfil = [
            'full_name' => session('usuario.full_name', ''),
            'phone' => '',
        ];
        $error = null;

        try {
            $respuesta = $this->supabaseAuthRequest('get');
            if (!$respuesta->successful()) {
                logger()->warning('No se pudo consultar el perfil de autenticación del cliente', ['status' => $respuesta->status()]);
                $error = 'No se pudieron consultar todos los datos del perfil.';
            } else {
                $metadata = $respuesta->json('user_metadata') ?? [];
                $perfil['full_name'] = $metadata['full_name'] ?? $perfil['full_name'];
                $perfil['phone'] = $metadata['phone'] ?? '';
            }
        } catch (ConnectionException $e) {
            report($e);
            $error = 'No fue posible conectar con el servicio de perfiles.';
        }

        return view('cliente.perfil.edit', compact('perfil', 'error'));
    }

    public function update(Request $request)
    {
        $datos = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        try {
            $url = rtrim((string) config('services.supabase.url'), '/');
            $key = config('services.supabase.key');
            $token = session('supabase_access_token');
            $perfilRespuesta = Http::acceptJson()->withToken($token)
                ->withHeaders([
                    'apikey' => $key,
                    'Prefer' => 'return=representation',
                ])->connectTimeout(5)->timeout(15)
                ->patch($url . '/rest/v1/profiles?id=eq.' . urlencode((string) session('usuario.id')), [
                    'full_name' => trim($datos['full_name']),
                ]);

            if (!$perfilRespuesta->successful() || empty($perfilRespuesta->json()[0]['id'])) {
                logger()->error('No se pudo actualizar el nombre del perfil del cliente', ['status' => $perfilRespuesta->status()]);

                return back()->withInput()->withErrors([
                    'perfil' => 'No fue posible guardar el nombre del perfil. Verifica los permisos de tu cuenta.',
                ]);
            }

            $respuesta = $this->supabaseAuthRequest('put', [
                'data' => [
                    'full_name' => trim($datos['full_name']),
                    'phone' => trim($datos['phone']),
                ],
            ]);

            if (!$respuesta->successful()) {
                logger()->error('No se pudo actualizar el perfil del cliente', ['status' => $respuesta->status()]);

                return back()->withInput()->withErrors([
                    'perfil' => 'No fue posible guardar los cambios del perfil.',
                ]);
            }

            session()->put('usuario.full_name', trim($datos['full_name']));

            return redirect()->route('cliente.perfil.edit')->with('success', 'Tu perfil se actualizó correctamente.');
        } catch (ConnectionException $e) {
            report($e);

            return back()->withInput()->withErrors([
                'perfil' => 'No fue posible conectar con el servicio de perfiles.',
            ]);
        }
    }

    private function supabaseAuthRequest(string $method, array $payload = [])
    {
        $url = rtrim((string) config('services.supabase.url'), '/');
        $key = config('services.supabase.key');
        $token = session('supabase_access_token');

        abort_unless($url && $key && $token, 503, 'La sesión de perfil no está disponible.');

        $request = Http::acceptJson()->withToken($token)
            ->withHeaders(['apikey' => $key])->connectTimeout(5)->timeout(15);

        return $method === 'put'
            ? $request->put($url . '/auth/v1/user', $payload)
            : $request->get($url . '/auth/v1/user');
    }
}
