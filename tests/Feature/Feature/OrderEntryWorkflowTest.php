<?php

namespace Tests\Feature\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MaterialSelectionService;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OrderEntryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Warehouse $warehouse;
    protected Customer $customer;
    protected Product $product;
    protected MaterialSelectionService $materialSelectionService;
    protected PricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['type' => 'مستودع_رئيسي']);
        $this->customer = Customer::factory()->create();
        $this->product = Product::factory()->create([
            'cost_per_unit' => 10.00,
            'weight_per_unit' => 1.0,
            'specifications' => [
                'width' => 100,
                'length' => 1000,
                'grammage' => 80,
                'quality' => 'A'
            ]
        ]);

        // Create stock for the product
        Stock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_quantity' => 1000,
            'specifications' => [
                'width' => 100,
                'length' => 1000,
                'grammage' => 80,
                'quality' => 'A',
                'roll_number' => 'ROLL001'
            ]
        ]);

        $this->materialSelectionService = app(MaterialSelectionService::class);
        $this->pricingService = app(PricingService::class);
    }

    /** @test */
    public function it_can_create_an_order_with_basic_information()
    {
        $orderData = [
            'type' => 'out',
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'order_date' => now()->format('Y-m-d'),
            'required_date' => now()->addDays(7)->format('Y-m-d'),
            'required_weight' => 500,
            'required_length' => 500,
            'required_width' => 50,
            'delivery_method' => 'pickup',
            'notes' => 'Test order',
            'is_urgent' => false,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', $orderData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'order' => [
                    'type' => 'out',
                    'warehouse_id' => $this->warehouse->id,
                    'customer_id' => $this->customer->id,
                    'status' => 'مسودة',
                    'current_stage' => 'إنشاء',
                    'required_weight' => 500,
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'type' => 'out',
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'status' => 'مسودة',
            'current_stage' => 'إنشاء',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_for_order_creation()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/orders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'warehouse_id', 'customer_id', 'order_date']);
    }

    /** @test */
    public function it_can_automatically_select_materials_for_order()
    {
        $order = Order::factory()->create([
            'type' => 'out',
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'required_weight' => 100,
            'required_length' => 500,
            'required_width' => 50,
            'auto_material_selection' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/orders/{$order->id}/select-materials", [
                'auto_select' => true
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Materials selected successfully'
            ]);

        $order->refresh();
        $this->assertNotNull($order->selected_materials);
        $this->assertNotNull($order->materials_selected_at);
        $this->assertGreaterThan(0, $order->estimated_material_cost);
    }

    /** @test */
    public function it_can_calculate_pricing_for_order()
    {
        $order = Order::factory()->create([
            'type' => 'out',
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'required_weight' => 100,
            'estimated_material_cost' => 1000.00,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/orders/{$order->id}/calculate-pricing", [
                'profit_margin_percentage' => 25
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Pricing calculated successfully'
            ]);

        $order->refresh();
        $this->assertTrue($order->pricing_calculated);
        $this->assertNotNull($order->pricing_breakdown);
        $this->assertGreaterThan(0, $order->final_price);
    }

    /** @test */
    public function it_can_submit_order_for_approval()
    {
        $order = Order::factory()->create([
            'type' => 'out',
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'status' => 'مسودة',
            'selected_materials' => [['test' => 'data']],
            'pricing_calculated' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/orders/{$order->id}/submit-for-approval");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order submitted for approval successfully'
            ]);

        $order->refresh();
        $this->assertEquals('قيد_المراجعة', $order->status);
        $this->assertNotNull($order->submitted_at);
    }

    /** @test */
    public function it_prevents_submission_without_materials_and_pricing()
    {
        $order = Order::factory()->create([
            'type' => 'out',
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'status' => 'مسودة',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/orders/{$order->id}/submit-for-approval");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'Materials must be selected before submission'
            ]);
    }

    /** @test */
    public function it_can_approve_order()
    {
        $approver = User::factory()->create();
        $approver->assignRole('مدير_مبيعات');

        $order = Order::factory()->create([
            'type' => 'out',
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'status' => 'قيد_المراجعة',
        ]);

        $response = $this->actingAs($approver)
            ->postJson("/api/orders/{$order->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order approved successfully'
            ]);

        $order->refresh();
        $this->assertEquals('مؤكد', $order->status);
        $this->assertEquals('مراجعة', $order->current_stage);
        $this->assertNotNull($order->approved_at);
    }

    /** @test */
    public function it_can_get_order_with_full_details()
    {
        $order = Order::factory()->create([
            'type' => 'out',
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'selected_materials' => [['test' => 'data']],
            'pricing_calculated' => true,
            'pricing_breakdown' => ['test' => 'breakdown'],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'order' => [
                    'id',
                    'order_number',
                    'type',
                    'status',
                    'current_stage',
                    'customer',
                    'warehouse',
                    'selected_materials',
                    'pricing_breakdown',
                ],
                'material_availability',
                'pricing_summary'
            ]);
    }

    /** @test */
    public function it_can_list_orders_with_filters()
    {
        Order::factory()->count(3)->create([
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/orders?warehouse_id=' . $this->warehouse->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'orders' => [
                    'data' => [
                        '*' => [
                            'id',
                            'order_number',
                            'status',
                            'current_stage',
                        ]
                    ]
                ],
                'filters'
            ]);
    }

    /** @test */
    public function it_can_reserve_selected_materials()
    {
        $order = Order::factory()->create([
            'type' => 'out',
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'selected_materials' => [
                [
                    'stock_id' => 1,
                    'allocated_weight' => 50,
                    'estimated_cost' => 500.00
                ]
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/orders/{$order->id}/reserve-materials");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Materials reserved successfully'
            ]);
    }
}
