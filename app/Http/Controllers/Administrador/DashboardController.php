<?php

namespace App\Http\Controllers\Administrador;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DashboardController extends Controller
{
    public function index()
    {
        $ingredientes = [];
        $categorias = [];
        $productos = [];
        $recetasProductos = [];
        $error = null;

        try {
            foreach ([
                'ingredientes' => ['select' => 'id,nombre,descripcion,tipo,unidad,stock_minimo,activo', 'order' => 'nombre.asc', 'limit' => '100'],
                'categorias' => ['select' => 'id,nombre,descripcion,orden,activa', 'order' => 'orden.asc,nombre.asc', 'limit' => '100'],
                'productos' => ['select' => 'id,categoria_id,nombre,descripcion,precio,imagen_url,tiempo_preparacion_min,activo', 'order' => 'nombre.asc', 'limit' => '100'],
            ] as $tabla => $filtros) {
                $respuesta = $this->supabase()->get($this->restUrl($tabla), $filtros);

                if (!$respuesta->successful()) {
                    logger()->error('No se pudo consultar el módulo de administración', [
                        'tabla' => $tabla,
                        'status' => $respuesta->status(),
                        'respuesta' => $respuesta->json() ?? $respuesta->body(),
                    ]);
                    $error = 'No fue posible cargar toda la información. Comprueba que la cuenta admin tenga permisos en Supabase.';
                    break;
                }

                $$tabla = $respuesta->json() ?? [];
            }

            $productoIds = array_values(array_filter(array_column($productos, 'id')));
            if ($productoIds) {
                $respuestaRecetas = $this->supabase()->get($this->restUrl('recetas'), [
                    'select' => 'producto_id,ingrediente_id,cantidad',
                    'producto_id' => 'in.(' . implode(',', $productoIds) . ')',
                ]);

                if ($respuestaRecetas->successful()) {
                    $ingredientesPorId = [];
                    foreach ($ingredientes as $ingrediente) {
                        $ingredientesPorId[$ingrediente['id']] = $ingrediente;
                    }

                    foreach ($respuestaRecetas->json() ?? [] as $linea) {
                        $ingrediente = $ingredientesPorId[$linea['ingrediente_id']] ?? null;
                        $recetasProductos[$linea['producto_id']][] = [
                            'nombre' => $ingrediente['nombre'] ?? 'Ingrediente no disponible',
                            'cantidad' => $linea['cantidad'],
                            'unidad' => $ingrediente['unidad'] ?? '',
                        ];
                    }
                } else {
                    logger()->warning('No se pudieron consultar las recetas en administración', [
                        'status' => $respuestaRecetas->status(),
                    ]);
                }
            }
        } catch (ConnectionException $exception) {
            report($exception);
            $error = 'No se pudo conectar con Supabase. Inténtalo de nuevo.';
        } catch (\RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        return view('admin.dashboard', compact('ingredientes', 'categorias', 'productos', 'recetasProductos', 'error'));
    }

    public function storeIngrediente(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'tipo' => ['nullable', 'string', 'max:80'],
            'unidad' => ['required', 'in:g,ml,unidad'],
            'stock_minimo' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ]);

        return $this->insertar($request, 'ingredientes', [
            ...$datos,
            'activo' => true,
        ], 'Ingrediente creado. Si debe aparecer en el inventario, registra también su primer lote.');
    }

    public function storeCategoria(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'orden' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        return $this->insertar($request, 'categorias', [
            ...$datos,
            'activa' => true,
        ], 'Categoría creada.');
    }

    public function storeProducto(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'categoria_id' => ['nullable', 'uuid'],
            'descripcion' => ['nullable', 'string', 'max:1500'],
            'precio' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'imagen_url' => ['nullable', 'url', 'max:2000'],
            'tiempo_preparacion_min' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'receta' => ['required', 'array', 'min:1', 'max:50'],
            'receta.*.ingrediente_id' => ['required', 'uuid', 'distinct'],
            'receta.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:100000000'],
        ]);

        try {
            $productoRespuesta = $this->supabase()
                ->withHeaders(['Prefer' => 'return=representation'])
                ->post($this->restUrl('productos'), [
                    'nombre' => $datos['nombre'],
                    'categoria_id' => $datos['categoria_id'] ?? null,
                    'descripcion' => $datos['descripcion'] ?? null,
                    'precio' => $datos['precio'],
                    'imagen_url' => $datos['imagen_url'] ?? null,
                    'tiempo_preparacion_min' => $datos['tiempo_preparacion_min'] ?? null,
                    'activo' => true,
                ]);

            if (!$productoRespuesta->successful()) {
                return $this->errorPostgrest($request, $productoRespuesta, 'productos');
            }

            $productoId = data_get($productoRespuesta->json(), '0.id');
            if (!$productoId) {
                logger()->error('Supabase creó un producto sin devolver su UUID');
                return back()->withInput()->with('error', 'El platillo se creó, pero no se pudo obtener su identificador para guardar la receta. Contacta al administrador de la base de datos.');
            }

            $filasReceta = array_map(
                fn (array $linea) => [
                    'producto_id' => $productoId,
                    'ingrediente_id' => $linea['ingrediente_id'],
                    'cantidad' => $linea['cantidad'],
                ],
                $datos['receta']
            );

            $recetaRespuesta = $this->supabase()
                ->withHeaders(['Prefer' => 'return=minimal'])
                ->post($this->restUrl('recetas'), $filasReceta);

            if (!$recetaRespuesta->successful()) {
                logger()->error('Falló el registro de receta para un producto nuevo', [
                    'producto_id' => $productoId,
                    'status' => $recetaRespuesta->status(),
                    'respuesta' => $recetaRespuesta->json() ?? $recetaRespuesta->body(),
                ]);

                $limpieza = $this->supabase()->send('DELETE', $this->restUrl('productos'), [
                    'query' => ['id' => 'eq.' . $productoId],
                ]);

                $mensaje = $limpieza->successful()
                    ? 'No se guardó la receta y se deshizo la creación del platillo. Verifica sus ingredientes e inténtalo de nuevo.'
                    : 'El platillo se creó, pero la receta no se guardó y no se pudo deshacer el alta. Revisa el catálogo antes de volver a intentarlo.';

                return back()->withInput()->with('error', $mensaje);
            }

            return redirect()->route('admin.dashboard')->with(
                'success',
                'Platillo y receta creados. La disponibilidad se calcula con las existencias de sus ingredientes.'
            );
        } catch (ConnectionException $exception) {
            report($exception);
            return back()->withInput()->with('error', 'No se pudo conectar con Supabase. Comprueba si el platillo alcanzó a guardarse antes de volver a enviarlo.');
        } catch (\RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    private function errorPostgrest(Request $request, $respuesta, string $tabla)
    {
        logger()->error('No se pudo crear un registro desde administración', [
            'tabla' => $tabla,
            'status' => $respuesta->status(),
            'respuesta' => $respuesta->json() ?? $respuesta->body(),
        ]);

        $detalle = $respuesta->json('message') ?? $respuesta->json('hint');
        $codigo = $respuesta->json('code');
        if ($codigo === '23505') {
            $entidad = match ($tabla) {
                'categorias' => 'una categoría con ese nombre',
                'ingredientes' => 'un ingrediente con ese nombre',
                'productos' => 'un platillo con ese nombre',
                default => 'un registro con esos datos',
            };
            return back()->withInput()->with('error', 'Ya existe ' . $entidad . '.');
        }

        if ($codigo === '23503') {
            return back()->withInput()->with('error', 'La categoría o el ingrediente seleccionado ya no está disponible. Actualiza el panel e inténtalo de nuevo.');
        }

        $mensajeError = match ($respuesta->status()) {
            401, 403 => 'Supabase rechazó la operación. Verifica las políticas RLS para administradores.',
            404 => 'La tabla solicitada no está disponible en Supabase.',
            default => is_string($detalle) && $detalle !== ''
                ? 'No se pudo guardar: ' . $detalle
                : 'No se pudo guardar. Revisa los campos y los permisos de Supabase.',
        };

        return back()->withInput()->with('error', $mensajeError);
    }

    private function insertar(Request $request, string $tabla, array $datos, string $mensaje)
    {
        try {
            $respuesta = $this->supabase()
                ->withHeaders(['Prefer' => 'return=representation'])
                ->post($this->restUrl($tabla), $datos);

            if (!$respuesta->successful()) {
                return $this->errorPostgrest($request, $respuesta, $tabla);
            }

            return redirect()->route('admin.dashboard')->with('success', $mensaje);
        } catch (ConnectionException $exception) {
            report($exception);
            return back()->withInput()->with('error', 'No se pudo conectar con Supabase. Inténtalo de nuevo.');
        } catch (\RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    private function supabase()
    {
        $token = session('supabase_access_token');
        $key = config('services.supabase.key');
        $baseUrl = rtrim((string) config('services.supabase.url'), '/');

        if (!$token || !$key || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('La sesión de Supabase no está disponible. Cierra sesión e inicia nuevamente.');
        }

        return Http::acceptJson()
            ->withToken($token)
            ->withHeaders(['apikey' => $key])
            ->connectTimeout(5)
            ->timeout(15);
    }

    private function restBase(): string
    {
        return rtrim((string) config('services.supabase.url'), '/') . '/rest/v1/';
    }

    private function restUrl(string $path): string
    {
        return $this->restBase() . $path;
    }
}
