<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        $this->guardarCodigoQr($request);

        if (session()->has('usuario')) {
            return $this->redirigirDespuesDeAutenticar(
                session('usuario.role')
            );
        }

        return view('auth.login');
    }

    public function showRegister(Request $request)
    {
        $this->guardarCodigoQr($request);

        if (session()->has('usuario')) {
            return $this->redirigirDespuesDeAutenticar(
                session('usuario.role')
            );
        }

        return view('auth.register');
    }

    private function guardarCodigoQr(Request $request): void
    {
        $codigo = $request->query('mesa');

        if (is_string($codigo) && Str::isUuid($codigo)) {
            session([
                'codigo_qr_pendiente' => $codigo
            ]);
        }
    }

    public function login(Request $request)
    {
        $this->guardarCodigoQr($request);

        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $url = rtrim(config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        if (!$url || !$key) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'La configuración de Supabase está incompleta.',
                ]);
        }

        try {
            $respuesta = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->withHeaders([
                    'apikey' => $key,
                ])
                ->post($url . '/auth/v1/token?grant_type=password', [
                    'email' => $request->email,
                    'password' => $request->password,
                ]);

        } catch (ConnectionException $e) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'No se pudo conectar con el servicio de autenticación.',
                ]);
        }

        if (!$respuesta->successful()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Correo electrónico o contraseña incorrectos.',
                ]);
        }

        $datos = $respuesta->json();

        return $this->iniciarSesionConSupabase(
            $request,
            $datos,
            $url,
            $key
        );
    }

    public function register(Request $request)
    {
        $this->guardarCodigoQr($request);

        $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'full_name.required' => 'Debes ingresar tu nombre completo.',
            'email.required' => 'Debes ingresar tu correo electrónico.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'phone.required' => 'Debes ingresar tu número de teléfono.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $url = rtrim(config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        if (!$url || !$key) {
            return back()->withErrors([
                'email' => 'La configuración de Supabase está incompleta.',
            ]);
        }

        try {
            $respuesta = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->withHeaders([
                    'apikey' => $key,
                ])
                ->post($url . '/auth/v1/signup', [
                    'email' => $request->email,
                    'password' => $request->password,
                    'data' => [
                        'full_name' => trim($request->full_name),
                        'phone' => trim($request->phone),
                    ],
                ]);

        } catch (ConnectionException $e) {
            return back()->withErrors([
                'email' => 'No se pudo conectar con el servicio de registro.',
            ]);
        }

        if (!$respuesta->successful()) {
            $mensaje = $respuesta->json('msg')
                ?? $respuesta->json('message')
                ?? 'No se pudo crear la cuenta. Verifica los datos ingresados.';

            return back()
                ->withInput($request->only('full_name', 'email', 'phone'))
                ->withErrors([
                    'email' => $mensaje,
                ]);
        }

        $datos = $respuesta->json();

        // Supabase puede exigir confirmar el correo antes de iniciar sesión.
        if (
            empty($datos['access_token']) ||
            empty($datos['user']['id'])
        ) {
            return redirect('/login')->with(
                'status',
                'Tu cuenta fue registrada. Revisa tu correo electrónico para confirmar la cuenta antes de iniciar sesión.'
            );
        }

        return $this->iniciarSesionConSupabase(
            $request,
            $datos,
            $url,
            $key
        );
    }

    private function iniciarSesionConSupabase(
        Request $request,
        array $datos,
        string $url,
        string $key
    ) {
        if (
            empty($datos['access_token']) ||
            empty($datos['user']['id'])
        ) {
            return back()->withErrors([
                'email' => 'No se pudo completar la autenticación.',
            ]);
        }

        try {
            $perfilRespuesta = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->withToken($datos['access_token'])
                ->withHeaders([
                    'apikey' => $key,
                ])
                ->get($url . '/rest/v1/profiles', [
                    'id' => 'eq.' . $datos['user']['id'],
                    'select' => 'id,full_name,role',
                ]);

        } catch (ConnectionException $e) {
            return back()->withErrors([
                'email' => 'No se pudo consultar el perfil. Inténtalo nuevamente.',
            ]);
        }

        if (!$perfilRespuesta->successful()) {
            return back()->withErrors([
                'email' => 'No se pudo consultar el perfil del usuario.',
            ]);
        }

        $perfil = $perfilRespuesta->json();

        if (empty($perfil) || !isset($perfil[0]['role'])) {
            return back()->withErrors([
                'email' => 'El usuario no tiene un perfil configurado.',
            ]);
        }

        $usuario = $perfil[0];

        if (!in_array($usuario['role'], [
            'admin',
            'staff',
            'customer'
        ], true)) {
            return back()->withErrors([
                'email' => 'El usuario tiene un rol no reconocido.',
            ]);
        }

        $request->session()->regenerate();

        session([
            'usuario' => [
                'id' => $usuario['id'],
                'full_name' => $usuario['full_name'],
                'role' => $usuario['role'],
            ],
            'supabase_access_token' => $datos['access_token'],
            'supabase_refresh_token' => $datos['refresh_token'] ?? null,
        ]);

        return $this->redirigirDespuesDeAutenticar(
            $usuario['role']
        );
    }

    private function redirigirDespuesDeAutenticar(string $rol)
    {
        if ($rol === 'customer' && session()->has('codigo_qr_pendiente')) {

            $codigo = session('codigo_qr_pendiente');

            session()->forget('codigo_qr_pendiente');

            return redirect()->route('mesa.qr', [
                'codigo' => $codigo
            ]);
        }

        return $this->redirigirPorRol($rol);
    }

    private function redirigirPorRol(string $rol)
    {
        return match ($rol) {
            'admin' => redirect()->route('admin.dashboard'),
            'staff' => redirect()->route('trabajador.dashboard'),
            'customer' => redirect()->route('cliente.dashboard'),
            default => redirect()->route('login'),
        };
    }

    public function logout(Request $request)
    {
        $token = session('supabase_access_token');
        $url = rtrim(config('services.supabase.url'), '/');
        $key = config('services.supabase.key');

        if ($token && $url && $key) {
            try {
                Http::withToken($token)
                    ->withHeaders([
                        'apikey' => $key,
                    ])
                    ->timeout(5)
                    ->post($url . '/auth/v1/logout');

            } catch (ConnectionException $e) {
                // La sesión local también se cerrará.
            }
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
