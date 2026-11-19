<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminOperationsAccessTest extends TestCase
{
    private const PRODUCT_ID = '00000000-0000-4000-8000-000000000101';
    private const INGREDIENT_ID = '00000000-0000-4000-8000-000000000102';

    public function test_admin_can_open_worker_operations_and_return_to_admin(): void
    {
        $this->configureSupabase();
        Http::fake(fn () => Http::response([], 200, ['Content-Range' => '0-0/0']));

        $response = $this->withSession($this->adminSession())
            ->get(route('trabajador.dashboard'));

        $response->assertOk()
            ->assertSee('Mesas')
            ->assertSee('Inventario')
            ->assertSee('Ventas y pagos')
            ->assertSee(route('admin.dashboard'));
    }

    public function test_admin_can_see_product_actions_and_cost_from_purchase_lots(): void
    {
        $this->fakeCatalog();

        $response = $this->withSession($this->adminSession())->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Editar')
            ->assertSee('Archivar')
            ->assertSee('Coste promedio ponderado de compras')
            ->assertSee('Métodos de pago')
            ->assertSee('Efectivo')
            ->assertSee('Transferencia bancaria')
            ->assertSee(route('admin.metodos-pago.sugeridos'))
            ->assertSee(route('admin.productos.edit', self::PRODUCT_ID));
        $response->assertViewHas('costoPlatillos', fn (array $costos) => $costos[self::PRODUCT_ID] === 40.0);
    }

    public function test_admin_can_add_missing_payment_methods_and_reactivate_inactive_defaults(): void
    {
        $this->configureSupabase();
        $existentes = [
            ['id' => '00000000-0000-4000-8000-000000000201', 'nombre' => 'Efectivo', 'activo' => true],
            ['id' => '00000000-0000-4000-8000-000000000202', 'nombre' => 'Tarjeta débito', 'activo' => false],
        ];
        $insertados = [];
        $actualizados = [];

        Http::fake(function (ClientRequest $request) use (&$existentes, &$insertados, &$actualizados) {
            if ($request->method() === 'GET') {
                return Http::response($existentes, 200);
            }

            if ($request->method() === 'POST') {
                $metodo = array_merge($request->data(), [
                    'id' => '00000000-0000-4000-8000-' . str_pad((string) (301 + count($insertados)), 12, '0', STR_PAD_LEFT),
                ]);
                $existentes[] = $metodo;
                $insertados[] = $metodo;
                return Http::response([$metodo], 201);
            }

            $actualizados[] = $request->data();
            $existentes[1]['activo'] = true;
            return Http::response([$existentes[1]], 200);
        });

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.metodos-pago.sugeridos'));

        $response->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Métodos de pago listos: 3 agregados y 1 reactivados.');
        $this->assertCount(3, $insertados);
        $this->assertCount(1, $actualizados);

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.metodos-pago.sugeridos'));
        $response->assertSessionHas('success', 'Métodos de pago listos: 0 agregados y 0 reactivados.');
        $this->assertCount(3, $insertados);
    }

    public function test_admin_can_open_the_product_editor(): void
    {
        $this->fakeCatalog();

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.productos.edit', self::PRODUCT_ID));

        $response->assertOk()
            ->assertSee('Editar platillo')
            ->assertSee('Guardar cambios')
            ->assertSee('Salsa de prueba');
    }

    public function test_admin_can_archive_a_product(): void
    {
        $this->configureSupabase();
        $product = $this->product();
        Http::fake(function (ClientRequest $request) use ($product) {
            return $request->method() === 'PATCH'
                ? Http::response([array_merge($product, ['activo' => false])], 200)
                : Http::response([], 200);
        });

        $response = $this->withSession($this->adminSession())
            ->patch(route('admin.productos.estado', self::PRODUCT_ID), ['activo' => 0]);

        $response->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Registro archivado.');
    }

    private function fakeCatalog(): void
    {
        $this->configureSupabase();
        $data = [
            'ingredientes' => [[
                'id' => self::INGREDIENT_ID,
                'nombre' => 'Tomate',
                'tipo' => 'Verdura',
                'unidad' => 'g',
                'stock_minimo' => 1,
                'activo' => true,
            ]],
            'categorias' => [],
            'productos' => [$this->product()],
            'metodos_pago' => [[
                'id' => '00000000-0000-4000-8000-000000000103',
                'nombre' => 'Efectivo',
                'descripcion' => 'Pago recibido en efectivo.',
                'activo' => true,
            ]],
            'lotes_ingrediente' => [[
                'ingrediente_id' => self::INGREDIENT_ID,
                'cantidad_inicial' => 3,
                'costo_total' => 60,
            ]],
            'recetas' => [[
                'producto_id' => self::PRODUCT_ID,
                'ingrediente_id' => self::INGREDIENT_ID,
                'cantidad' => 2,
            ]],
        ];

        Http::fake(function (ClientRequest $request) use ($data) {
            $tabla = basename(parse_url($request->url(), PHP_URL_PATH));

            return Http::response($data[$tabla] ?? [], 200);
        });
    }

    private function product(): array
    {
        return [
            'id' => self::PRODUCT_ID,
            'categoria_id' => null,
            'nombre' => 'Salsa de prueba',
            'descripcion' => 'Una salsa',
            'precio' => 100,
            'imagen_url' => null,
            'tiempo_preparacion_min' => 5,
            'activo' => true,
        ];
    }

    private function configureSupabase(): void
    {
        Config::set('services.supabase.url', 'https://supabase.test');
        Config::set('services.supabase.key', 'test-key');
    }

    private function adminSession(): array
    {
        return [
            'usuario' => ['role' => 'admin', 'full_name' => 'Admin de prueba'],
            'supabase_access_token' => 'test-token',
        ];
    }
}
