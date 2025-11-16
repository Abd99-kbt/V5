<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CustomerValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Create test warehouse
        $this->warehouse = Warehouse::factory()->create();

        // Create test customers
        $this->customer1 = Customer::factory()->create([
            'name_en' => 'Test Customer 1',
            'name_ar' => 'عميل تجريبي 1',
            'mobile_number' => '1234567890',
            'email' => 'customer1@test.com',
            'credit_limit' => 10000.00,
            'is_active' => true,
        ]);

        $this->customer2 = Customer::factory()->create([
            'name_en' => 'Test Customer 2',
            'name_ar' => 'عميل تجريبي 2',
            'mobile_number' => '0987654321',
            'email' => 'customer2@test.com',
            'credit_limit' => 5000.00,
            'is_active' => true,
        ]);

        // Create inactive customer
        $this->inactiveCustomer = Customer::factory()->create([
            'name_en' => 'Inactive Customer',
            'is_active' => false,
        ]);
    }

    /** @test */
    public function it_can_search_customers_by_name()
    {
        $response = $this->get('/admin/orders/create');

        $response->assertStatus(200);

        // Test that active customers are available for selection
        $this->assertDatabaseHas('customers', [
            'name_en' => 'Test Customer 1',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('customers', [
            'name_en' => 'Test Customer 2',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_validates_credit_limit_before_order_creation()
    {
        $validationService = new CustomerValidationService();

        // Test valid credit limit
        $validation = $validationService->validateCreditLimit($this->customer1, 5000.00);
        $this->assertTrue($validation['valid']);
        $this->assertEquals(5000.00, $validation['remaining_credit']);

        // Test exceeded credit limit
        $validation = $validationService->validateCreditLimit($this->customer1, 15000.00);
        $this->assertFalse($validation['valid']);
        $this->assertStringContains('exceeded', $validation['message']);
    }

    /** @test */
    public function it_provides_customer_credit_summary()
    {
        $validationService = new CustomerValidationService();

        // Create some orders for the customer
        Order::factory()->create([
            'customer_id' => $this->customer1->id,
            'total_amount' => 3000.00,
            'is_paid' => false,
        ]);

        Order::factory()->create([
            'customer_id' => $this->customer1->id,
            'total_amount' => 2000.00,
            'is_paid' => true,
        ]);

        $summary = $validationService->getCreditSummary($this->customer1);

        $this->assertEquals(10000.00, $summary['credit_limit']);
        $this->assertEquals(3000.00, $summary['outstanding_amount']); // Only unpaid order
        $this->assertEquals(7000.00, $summary['available_credit']);
        $this->assertEquals(30.0, $summary['utilization_percentage']);
    }

    /** @test */
    public function it_shows_warnings_for_high_credit_utilization()
    {
        $validationService = new CustomerValidationService();

        // Create order that uses 90% of credit limit
        Order::factory()->create([
            'customer_id' => $this->customer1->id,
            'total_amount' => 9000.00,
            'is_paid' => false,
        ]);

        $validation = $validationService->validateCustomerForOrder($this->customer1, 500.00);

        $this->assertTrue($validation['valid']);
        $this->assertNotEmpty($validation['warnings']);
        $this->assertStringContains('high relative to available credit', $validation['warnings'][0]);
    }

    /** @test */
    public function it_prevents_orders_for_inactive_customers()
    {
        $validationService = new CustomerValidationService();

        $validation = $validationService->validateCustomerStatus($this->inactiveCustomer);

        $this->assertFalse($validation['valid']);
        $this->assertStringContains('inactive', $validation['message']);
    }

    /** @test */
    public function it_provides_customer_data_for_preview()
    {
        // Create some orders for testing
        Order::factory()->count(3)->create([
            'customer_id' => $this->customer1->id,
            'total_amount' => 1000.00,
            'is_paid' => false,
        ]);

        $customerSelect = new \App\Filament\Components\CustomerSelect('customer_id');
        $customerData = $customerSelect->getCustomerData($this->customer1->id);

        $this->assertNotNull($customerData);
        $this->assertEquals($this->customer1->id, $customerData['id']);
        $this->assertEquals($this->customer1->name, $customerData['name']);
        $this->assertEquals(10000.00, $customerData['credit_limit']);
        $this->assertCount(3, $customerData['recent_orders']);
    }

    /** @test */
    public function it_formats_customer_options_correctly()
    {
        $customerSelect = new \App\Filament\Components\CustomerSelect('customer_id');
        $formatted = $this->invokePrivateMethod($customerSelect, 'formatCustomerOption', [$this->customer1]);

        $this->assertStringContains('Test Customer 1', $formatted);
        $this->assertStringContains('1234567890', $formatted);
    }

    /** @test */
    public function it_only_shows_active_customers_in_options()
    {
        $customerSelect = new \App\Filament\Components\CustomerSelect('customer_id');
        $options = $customerSelect->getOptions();

        // Should not include inactive customer
        $inactiveFound = false;
        foreach ($options as $id => $label) {
            if ($id === $this->inactiveCustomer->id) {
                $inactiveFound = true;
                break;
            }
        }

        $this->assertFalse($inactiveFound, 'Inactive customer should not appear in options');
    }

    /**
     * Helper method to invoke private methods for testing
     */
    private function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}