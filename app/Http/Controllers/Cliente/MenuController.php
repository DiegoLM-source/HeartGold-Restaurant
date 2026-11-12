<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $filtrosSolicitud = $request->validate([
            'categoria' => ['nullable', 'uuid'],
            'buscar' => ['nullable', 'string', 'max:100', 'regex:/^[\pL\pN\s.\'-]*$/u'],
        ]);
        $productos = [];
        $categorias = [];
        $sesionActiva = false;
        $error = null;
        $usuarioId = session('usuario.id');
        $token = session('supabase_access_token');
        $url = rtrim((string) config('services.supabase.url'), '/');
        $key = config('services.supabase.key');
        $categoriaSeleccionada = $filtrosSolicitud['categoria'] ?? null;
        $busqueda = trim((string) ($filtrosSolicitud['buscar'] ?? ''));

        if (!$usuarioId || !$token || !$url || !$key) {
            $error = 'No se pudo validar la sesión con el restaurante. Cierra sesión e inicia nuevamente.';
        } else {
            try {
                $headers = ['apikey' => $key];
                $cliente = Http::acceptJson()->withToken($token)->withHeaders($headers)->connectTimeout(5)->timeout(15);
                $sesionRespuesta = $cliente->get($url . '/rest/v1/sesiones', [
                    'cliente_id' => 'eq.' . $usuarioId,
                    'activa' => 'eq.true',
                    'select' => 'id',
                    'limit' => '1',
                ]);
                if ($sesionRespuesta->successful()) {
                    $sesionActiva = !empty(($sesionRespuesta->json() ?? [])[0]['id']);
                } else {
                    logger()->warning('No se pudo validar sesión activa para menú del cliente', ['status' => $sesionRespuesta->status()]);
                    $error = 'No se pudo verificar si tienes una sesión de mesa activa.';
                }

                $categoriasRespuesta = $cliente->get($url . '/rest/v1/categorias', [
                    'select' => 'id,nombre,descripcion,orden,activa',
                    'activa' => 'eq.true',
                    'order' => 'orden.asc,nombre.asc',
                ]);
                if (!$categoriasRespuesta->successful()) {
                    logger()->error('Error consultando categorías del menú', ['status' => $categoriasRespuesta->status()]);
                    $error = 'No se pudieron consultar las categorías del menú.';
                } else {
                    $categorias = $categoriasRespuesta->json() ?? [];
                }

                $filtrosProductos = [
                    'select' => 'id,categoria_id,nombre,descripcion,precio,imagen_url,activo',
                    'order' => 'nombre.asc',
                    'limit' => '500',
                ];
                if ($categoriaSeleccionada) {
                    $filtrosProductos['categoria_id'] = 'eq.' . $categoriaSeleccionada;
                }
                if ($busqueda !== '') {
                    $filtrosProductos['nombre'] = 'ilike.*' . $busqueda . '*';
                }

                $productosRespuesta = $cliente->get($url . '/rest/v1/productos', $filtrosProductos);
                if (!$productosRespuesta->successful()) {
                    logger()->error('Error consultando productos del menú', ['status' => $productosRespuesta->status()]);
                    $error = 'No se pudieron consultar los productos del menú.';
                } else {
                    $productos = $productosRespuesta->json() ?? [];
                    $productoIds = array_values(array_filter(array_column($productos, 'id')));
                    $categoriasPorId = [];
                    foreach ($categorias as $categoria) $categoriasPorId[$categoria['id']] = $categoria;
                    $disponibilidad = [];

                    if ($productoIds) {
                        $disponibilidadRespuesta = $cliente->get($url . '/rest/v1/disponibilidad_productos', [
                            'select' => 'producto_id,porciones,disponible',
                            'producto_id' => 'in.(' . implode(',', $productoIds) . ')',
                        ]);
                        if ($disponibilidadRespuesta->successful()) {
                            foreach ($disponibilidadRespuesta->json() ?? [] as $item) $disponibilidad[$item['producto_id']] = $item;
                        } else {
                            logger()->warning('No se pudo consultar disponibilidad de productos', ['status' => $disponibilidadRespuesta->status()]);
                            $error = 'Se cargó el menú, pero la disponibilidad no está actualizada.';
                        }
                    }

                    foreach ($productos as &$producto) {
                        $producto['categoria'] = $categoriasPorId[$producto['categoria_id'] ?? ''] ?? null;
                        $producto['porciones'] = $disponibilidad[$producto['id']]['porciones'] ?? 0;
                        $producto['disponible'] = !empty($producto['activo'])
                            && !empty($disponibilidad[$producto['id']]['disponible'])
                            && (int) $producto['porciones'] > 0;
                    }
                    unset($producto);
                }
            } catch (ConnectionException $e) {
                report($e);
                $error = 'No fue posible conectar con el restaurante. Inténtalo nuevamente.';
            }
        }

        return view('cliente.menu.index', compact('productos', 'categorias', 'sesionActiva', 'error', 'categoriaSeleccionada', 'busqueda'));
    }
}
