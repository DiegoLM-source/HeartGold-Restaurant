<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminReportesTest extends TestCase
{
    private const TABLE_ID = '00000000-0000-4000-8000-000000000008';
    private const SECOND_TABLE_ID = '00000000-0000-4000-8000-000000000010';

    public function test_admin_can_view_report_with_sales_payments_and_estimated_profit(): void
    {
        $this->fakeReportData();

        $response = $this->withSession([
            'usuario' => ['role' => 'admin'],
            'supabase_access_token' => 'test-token',
        ])->get(route('admin.reportes', ['desde' => '2025-01-01', 'hasta' => '2025-01-31']));

        $response->assertOk()
            ->assertViewHas('ventas', 100)
            ->assertViewHas('pagosRecibidos', 100)
            ->assertViewHas('saldoPendiente', 0)
            ->assertViewHas('gananciaEstimada', 20)
            ->assertViewHas('coberturaCostos', 100)
            ->assertViewHas('reporteMesas', function (array $mesas) {
                $mesasPorId = collect($mesas)->keyBy('id');

                return count($mesas) === 2
                    && $mesasPorId[self::TABLE_ID]['nombre'] === 'Mesa Jardín'
                    && $mesasPorId[self::TABLE_ID]['ventas'] === 100.0
                    && $mesasPorId[self::SECOND_TABLE_ID]['pedidosRegistrados'] === 1
                    && $mesasPorId[self::SECOND_TABLE_ID]['historial'][0]['estado'] === 'cancelado';
            });
    }

    public function test_admin_can_filter_report_and_order_history_by_table(): void
    {
        $this->fakeReportData();

        $response = $this->withSession([
            'usuario' => ['role' => 'admin'],
            'supabase_access_token' => 'test-token',
        ])->get(route('admin.reportes', [
            'desde' => '2025-01-01',
            'hasta' => '2025-01-31',
            'mesa_id' => self::TABLE_ID,
        ]));

        $response->assertOk()
            ->assertSee('Mesa Jardín')
            ->assertSee('Taco de prueba')
            ->assertViewHas('mesaSeleccionada', fn (?array $mesa) => $mesa !== null && $mesa['id'] === self::TABLE_ID)
            ->assertViewHas('reporteMesas', function (array $mesas) {
                return count($mesas) === 1
                    && $mesas[0]['pedidosRegistrados'] === 1
                    && $mesas[0]['ventas'] === 100.0
                    && $mesas[0]['pagosRecibidos'] === 100.0
                    && $mesas[0]['historial'][0]['estado'] === 'entregado'
                    && $mesas[0]['historial'][0]['productos'][0]['nombre_producto'] === 'Taco de prueba';
            });

        Http::assertSent(function (ClientRequest $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?? '', '/pedidos')
                && ($query['mesa_id'] ?? null) === 'eq.' . self::TABLE_ID;
        });
    }

    public function test_admin_can_download_a_pdf_report(): void
    {
        $this->fakeReportData();

        $response = $this->withSession([
            'usuario' => ['role' => 'admin'],
            'supabase_access_token' => 'test-token',
        ])->get(route('admin.reportes.pdf', [
            'desde' => '2025-01-01',
            'hasta' => '2025-01-31',
            'mesa_id' => self::TABLE_ID,
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="reporte-ventas-2025-01-01-2025-01-31.pdf"');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    private function fakeReportData(): void
    {
        Config::set('services.supabase.url', 'https://supabase.test');
        Config::set('services.supabase.key', 'test-key');

        $data = [
            'mesas' => [[
                'id' => self::TABLE_ID,
                'nombre' => 'Mesa Jardín',
                'zona' => 'Terraza',
                'activa' => true,
            ], [
                'id' => self::SECOND_TABLE_ID,
                'nombre' => 'Mesa Barra',
                'zona' => 'Interior',
                'activa' => true,
            ]],
            'pedidos' => [
                [
                    'id' => '00000000-0000-4000-8000-000000000001',
                    'mesa_id' => self::TABLE_ID,
                    'sesion_id' => '00000000-0000-4000-8000-000000000009',
                    'estado' => 'entregado',
                    'subtotal' => 100,
                    'total' => 100,
                    'observaciones' => null,
                    'creado_en' => '2025-01-03T12:00:00Z',
                ],
                [
                    'id' => '00000000-0000-4000-8000-000000000011',
                    'mesa_id' => self::SECOND_TABLE_ID,
                    'sesion_id' => null,
                    'estado' => 'cancelado',
                    'subtotal' => 40,
                    'total' => 40,
                    'observaciones' => null,
                    'creado_en' => '2025-01-05T12:00:00Z',
                ],
            ],
            'ventas' => [[
                'id' => '00000000-0000-4000-8000-000000000002',
                'pedido_id' => '00000000-0000-4000-8000-000000000001',
                'subtotal' => 100,
                'total' => 100,
                'estado' => 'pagado',
            ]],
            'pagos' => [[
                'id' => '00000000-0000-4000-8000-000000000003',
                'venta_id' => '00000000-0000-4000-8000-000000000002',
                'metodo_pago_id' => '00000000-0000-4000-8000-000000000004',
                'monto' => 100,
                'estado' => 'pagado',
            ]],
            'metodos_pago' => [[
                'id' => '00000000-0000-4000-8000-000000000004',
                'nombre' => 'Efectivo',
            ]],
            'detalle_pedido' => [
                [
                    'id' => '00000000-0000-4000-8000-000000000005',
                    'pedido_id' => '00000000-0000-4000-8000-000000000001',
                    'nombre_producto' => 'Taco de prueba',
                    'cantidad' => 2,
                    'precio_unitario' => 50,
                    'subtotal' => 100,
                ],
                [
                    'id' => '00000000-0000-4000-8000-000000000012',
                    'pedido_id' => '00000000-0000-4000-8000-000000000011',
                    'nombre_producto' => 'Ensalada de prueba',
                    'cantidad' => 1,
                    'precio_unitario' => 40,
                    'subtotal' => 40,
                ],
            ],
            'productos' => [[
                'id' => '00000000-0000-4000-8000-000000000006',
                'nombre' => 'Taco de prueba',
            ]],
            'recetas' => [[
                'producto_id' => '00000000-0000-4000-8000-000000000006',
                'ingrediente_id' => '00000000-0000-4000-8000-000000000007',
                'cantidad' => 2,
            ]],
            'lotes_ingrediente' => [[
                'ingrediente_id' => '00000000-0000-4000-8000-000000000007',
                'cantidad_inicial' => 1,
                'costo_total' => 20,
            ]],
        ];

        Http::fake(function (ClientRequest $request) use ($data) {
            $tabla = basename(parse_url($request->url(), PHP_URL_PATH));

            return Http::response($data[$tabla] ?? [], 200);
        });
    }
}
