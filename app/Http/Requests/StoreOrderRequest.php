<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Allow authenticated users to create orders
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:in,out'],
            'warehouse_id' => 'required|exists:warehouses,id',
            'customer_id' => 'required_if:type,out|exists:customers,id',
            'supplier_id' => 'required_if:type,in|exists:suppliers,id',
            'order_date' => 'required|date|before_or_equal:today',
            'required_date' => 'nullable|date|after:order_date',
            'material_type' => 'nullable|string|max:255',
            'required_weight' => 'nullable|numeric|min:0|max:999999.99',
            'required_length' => 'nullable|numeric|min:0|max:999999.99',
            'required_width' => 'nullable|numeric|min:0|max:999999.99',
            'delivery_method' => 'nullable|string|max:255',
            'delivery_address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
            'is_urgent' => 'boolean',
            'specifications' => 'nullable|array',
            'specifications.*' => 'string|max:255',
            'material_requirements' => 'nullable|array',
            'material_requirements.*.product_id' => 'required|exists:products,id',
            'material_requirements.*.required_weight' => 'required|numeric|min:0',
            'material_requirements.*.specifications' => 'nullable|array',
            'auto_material_selection' => 'boolean',
            'order_items' => 'nullable|array|min:1',
            'order_items.*.product_id' => 'required|exists:products,id',
            'order_items.*.quantity' => 'required|numeric|min:0.01',
            'order_items.*.unit_price' => 'nullable|numeric|min:0',
            'order_items.*.notes' => 'nullable|string|max:500',
            // Delivery specifications validation
            'delivery_width' => 'nullable|numeric|min:0|max:999999.99',
            'delivery_length' => 'nullable|numeric|min:0|max:999999.99',
            'delivery_thickness' => 'nullable|numeric|min:0|max:999999.99',
            'delivery_grammage' => 'nullable|numeric|min:0|max:999999.99',
            'delivery_quality' => 'nullable|string|max:255',
            'delivery_quantity' => 'nullable|integer|min:0|max:999999',
            'delivery_weight' => 'nullable|numeric|min:0|max:999999.99',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Order type is required',
            'type.in' => 'Order type must be either "in" (purchase) or "out" (sales)',
            'warehouse_id.required' => 'Warehouse is required',
            'warehouse_id.exists' => 'Selected warehouse does not exist',
            'customer_id.required_if' => 'Customer is required for sales orders',
            'supplier_id.required_if' => 'Supplier is required for purchase orders',
            'order_date.required' => 'Order date is required',
            'order_date.before_or_equal' => 'Order date cannot be in the future',
            'required_date.after' => 'Required date must be after order date',
            'required_weight.min' => 'Required weight must be greater than 0',
            'required_weight.max' => 'Required weight is too large',
            'required_length.min' => 'Required length must be greater than 0',
            'required_length.max' => 'Required length is too large',
            'required_width.min' => 'Required width must be greater than 0',
            'required_width.max' => 'Required width is too large',
            'order_items.required' => 'At least one order item is required',
            'order_items.*.product_id.required' => 'Product is required for each order item',
            'order_items.*.quantity.required' => 'Quantity is required for each order item',
            'order_items.*.quantity.min' => 'Quantity must be greater than 0',
            // Delivery specifications messages
            'delivery_width.min' => 'Delivery width must be greater than 0',
            'delivery_width.max' => 'Delivery width is too large',
            'delivery_length.min' => 'Delivery length must be greater than 0',
            'delivery_length.max' => 'Delivery length is too large',
            'delivery_thickness.min' => 'Delivery thickness must be greater than 0',
            'delivery_thickness.max' => 'Delivery thickness is too large',
            'delivery_grammage.min' => 'Delivery grammage must be greater than 0',
            'delivery_grammage.max' => 'Delivery grammage is too large',
            'delivery_quality.max' => 'Delivery quality description is too long',
            'delivery_quantity.min' => 'Delivery quantity must be greater than 0',
            'delivery_quantity.max' => 'Delivery quantity is too large',
            'delivery_weight.min' => 'Delivery weight must be greater than 0',
            'delivery_weight.max' => 'Delivery weight is too large',
        ];
    }

    public function prepareForValidation(): void
    {
        // Set default values
        if (!$this->has('auto_material_selection')) {
            $this->merge(['auto_material_selection' => true]);
        }

        if (!$this->has('is_urgent')) {
            $this->merge(['is_urgent' => false]);
        }

        // Set customer/supplier names if IDs are provided
        if ($this->input('type') === 'out' && $this->input('customer_id')) {
            $customer = \App\Models\Customer::find($this->input('customer_id'));
            if ($customer) {
                $this->merge([
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                    'customer_address' => $customer->address,
                ]);
            }
        }

        if ($this->input('type') === 'in' && $this->input('supplier_id')) {
            $supplier = \App\Models\Supplier::find($this->input('supplier_id'));
            if ($supplier) {
                $this->merge([
                    'customer_name' => $supplier->name,
                    'customer_phone' => $supplier->phone,
                    'customer_address' => $supplier->address,
                ]);
            }
        }
    }
}
