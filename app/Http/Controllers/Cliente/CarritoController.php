<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CarritoController extends Controller
{
    public function index()
    {
        $productos = [];
        $error = null;
        $subtotal = 0;
        $carrito = $this->carritoActual();
        $token = session('supabase_access_token');
        $url = rtrim((string) config('services.supabase.url'), '/');
        $key = config('services.supabase.key');
        $sesionActiva = false;

        if (!$token || !$url || !$key || !session('usuario.id')) {
            $error = 'No se pudo validar la sesión con el restaurante. Cierra sesión e inicia nuevamente.';
        } else {
            try {
                $request = Http::acceptJson()->withToken($token)->withHeaders(['apikey' => $key])->connectTimeout(5)->timeout(15);
                $respuestaSesion = $request->get($url . '/rest/v1/sesiones', [
                    'cliente_id' => 'eq.' . session('usuario.id'),
                    'activa' => 'eq.true',
                    'select' => 'id',
                    'limit' => '1',
                ]);
                $sesionActiva = $respuestaSesion->successful() && !empty(($respuestaSesion->json() ?? [])[0]['id']);

                if ($carrito) {
                    $ids = array_keys($carrito);
                    $respuestaProductos = $request->get($url . '/rest/v1/productos', [
                        'select' => 'id,nombre,descripcion,precio,imagen_url,activo',
                        'id' => 'in.(' . implode(',', $ids) . ')',
                    ]);
                    if (!$respuestaProductos->successful()) {
                        $error = 'No se pudieron consultar los productos del carrito.';
                    } else {
                        $lista = $respuestaProductos->json() ?? [];
                        $disponiblesRespuesta = $request->get($url . '/rest/v1/disponibilidad_productos', [
                            'select' => 'producto_id,porciones,disponible',
                            'producto_id' => 'in.(' . implode(',', $ids) . ')',
                        ]);
                        $disponibilidad = [];
                        if ($disponiblesRespuesta->successful()) {
                            foreach ($disponiblesRespuesta->json() ?? [] as $item) $disponibilidad[$item['producto_id']] = $item;
                        } else {
                            $error = 'No se pudo actualizar la disponibilidad de los productos.';
                        }

                        foreach ($lista as $producto) {
                            $producto['cantidad'] = $carrito[$producto['id']];
                            $producto['porciones'] = $disponibilidad[$producto['id']]['porciones'] ?? 0;
                            $producto['disponible'] = !empty($producto['activo'])
                                && !empty($disponibilidad[$producto['id']]['disponible'])
                                && (int) $producto['porciones'] >= $producto['cantidad'];
                            $producto['subtotal'] = (int) $producto['precio'] * (int) $producto['cantidad'];
                            $subtotal += $producto['subtotal'];
                            $productos[] = $producto;
                        }
                        $idsEncontrados = array_column($lista, 'id');
                        foreach (array_diff($ids, $idsEncontrados) as $productoId) {
                            $productos[] = [
                                'id' => $productoId,
                                'nombre' => 'Producto no disponible',
                                'precio' => 0,
                                'cantidad' => (int) $carrito[$productoId],
                                'subtotal' => 0,
                                'disponible' => false,
                            ];
                        }
                    }
                }
            } catch (ConnectionException $e) {
                report($e);
                $error = 'No fue posible conectar con el restaurante. Inténtalo nuevamente.';
            }
        }

        return view('cliente.carrito.index', compact('productos', 'carrito', 'subtotal', 'sesionActiva', 'error'));
    }

    public function agregar(Request $request)
    {
        $datos = $request->validate([
            'producto_id' => ['required', 'uuid'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ]);
        $carrito = $this->carritoActual();
        $cantidadNueva = ($carrito[$datos['producto_id']] ?? 0) + (int) $datos['cantidad'];
        if ($cantidadNueva > 2147483647) {
            return back()->withErrors(['cantidad' => 'La cantidad supera el máximo admitido.']);
        }

        $error = $this->validarDisponibilidad($datos['producto_id'], $cantidadNueva);
        if ($error) return back()->with('error', $error);

        $carrito[$datos['producto_id']] = $cantidadNueva;
        session()->put($this->carritoKey(), $carrito);

        return redirect()->route('cliente.carrito.index')->with('success', 'Producto agregado al carrito.');
    }

    public function actualizar(Request $request, string $producto)
    {
        abort_unless(Str::isUuid($producto), 404);
        $datos = $request->validate(['cantidad' => ['required', 'integer', 'min:1', 'max:2147483647']]);
        $carrito = $this->carritoActual();
        abort_unless(array_key_exists($producto, $carrito), 404);

        $error = $this->validarDisponibilidad($producto, (int) $datos['cantidad']);
        if ($error) return back()->with('error', $error);

        $carrito[$producto] = (int) $datos['cantidad'];
        session()->put($this->carritoKey(), $carrito);

        return back()->with('success', 'Cantidad actualizada.');
    }

    public function eliminar(string $producto)
    {
        abort_unless(Str::isUuid($producto), 404);
        $carrito = $this->carritoActual();
        unset($carrito[$producto]);
        session()->put($this->carritoKey(), $carrito);

        return back()->with('success', 'Producto eliminado del carrito.');
    }

    public function enviar(Request $request)
    {
        $datos = $request->validate(['observaciones' => ['nullable', 'string', 'max:1000']]);
        $carrito = $this->carritoActual();
        if (!$carrito) return back()->with('error', 'Agrega al menos un producto antes de enviar el pedido.');

        $token = session('supabase_access_token');
        $url = rtrim((string) config('services.supabase.url'), '/');
        $key = config('services.supabase.key');
        $usuarioId = session('usuario.id');

        if (!$token || !$url || !$key || !$usuarioId) {
            return back()->with('error', 'No se pudo validar la sesión con el restaurante. Inicia sesión nuevamente.');
        }

        try {
            $requestSupabase = Http::acceptJson()->withToken($token)->withHeaders(['apikey' => $key])->connectTimeout(5)->timeout(20);
            $sesionRespuesta = $requestSupabase->get($url . '/rest/v1/sesiones', [
                'cliente_id' => 'eq.' . $usuarioId,
                'activa' => 'eq.true',
                'select' => 'id,mesa_id',
                'order' => 'iniciada_en.desc',
                'limit' => '1',
            ]);
            if (!$sesionRespuesta->successful()) {
                logger()->error('Error validando sesión para crear pedido', ['status' => $sesionRespuesta->status()]);
                return back()->with('error', 'No se pudo validar la sesión de la mesa.');
            }
            $sesion = ($sesionRespuesta->json() ?? [])[0] ?? null;
            if (!$sesion) return back()->with('error', 'Necesitas una sesión de mesa activa y autorizada para pedir.');

            $productos = [];
            foreach ($carrito as $productoId => $cantidad) {
                $productos[] = [
                    'producto_id' => $productoId,
                    'cantidad' => (int) $cantidad,
                ];
            }

            $respuesta = $requestSupabase->post($url . '/rest/v1/rpc/crear_pedido', [
                'p_sesion_id' => $sesion['id'],
                'p_productos' => $productos,
                'p_observaciones' => trim((string) ($datos['observaciones'] ?? '')) ?: null,
            ]);

            if (!$respuesta->successful()) {
                logger()->warning('RPC crear_pedido rechazó el carrito', ['status' => $respuesta->status()]);
                return back()->with('error', $this->mensajeError($respuesta, 'No se pudo enviar el pedido. Verifica disponibilidad e inténtalo nuevamente.'));
            }

            session()->forget($this->carritoKey());

            return redirect()->route('cliente.pedidos.index')->with('success', 'Tu pedido fue enviado correctamente.');
        } catch (ConnectionException $e) {
            report($e);
            return back()->with('error', 'No fue posible conectar con el restaurante. El carrito se conservó.');
        }
    }

    private function validarDisponibilidad(string $productoId, int $cantidad): ?string
    {
        $url = rtrim((string) config('services.supabase.url'), '/');
        $key = config('services.supabase.key');
        $token = session('supabase_access_token');
        if (!$url || !$key || !$token) return 'No se pudo validar la sesión con el restaurante.';
        $request = Http::acceptJson()->withToken($token)
            ->withHeaders(['apikey' => $key])->connectTimeout(5)->timeout(15);

        try {
            $productoRespuesta = $request->get($url . '/rest/v1/productos', [
                'id' => 'eq.' . $productoId,
                'select' => 'id,activo',
                'limit' => '1',
            ]);
            if (!$productoRespuesta->successful()) return 'No se pudo validar el producto. Inténtalo nuevamente.';
            $producto = ($productoRespuesta->json() ?? [])[0] ?? null;
            if (!$producto || empty($producto['activo'])) return 'Este producto ya no está disponible.';

            $disponibilidadRespuesta = $request->get($url . '/rest/v1/disponibilidad_productos', [
                'producto_id' => 'eq.' . $productoId,
                'select' => 'porciones,disponible',
                'limit' => '1',
            ]);
            if (!$disponibilidadRespuesta->successful()) return 'No se pudo validar la disponibilidad del producto.';
            $disponibilidad = ($disponibilidadRespuesta->json() ?? [])[0] ?? null;
            if (!$disponibilidad || empty($disponibilidad['disponible']) || (int) $disponibilidad['porciones'] < $cantidad) {
                return 'La cantidad solicitada supera las porciones disponibles.';
            }

            return null;
        } catch (ConnectionException $e) {
            report($e);
            return 'No fue posible conectar con el restaurante.';
        }
    }

    private function carritoKey(): string
    {
        return 'cliente_carrito_' . session('usuario.id');
    }

    private function carritoActual(): array
    {
        $carrito = session($this->carritoKey(), []);
        return is_array($carrito) ? $carrito : [];
    }

    private function mensajeError($respuesta, string $alternativo): string
    {
        $mensaje = $respuesta->json('message') ?? $respuesta->json('msg');
        return is_string($mensaje) && trim($mensaje) !== '' && strlen($mensaje) <= 240
            ? $mensaje
            : $alternativo;
    }
}
