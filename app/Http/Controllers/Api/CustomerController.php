<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    use ApiResponseTrait;

    /**
     * Search customers by name, mobile number, or email
     */
    public function search(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validated = $request->validate([
                'q' => 'required|string|min:1|max:100',
                'limit' => 'nullable|integer|min:1|max:50',
            ]);

            $query = $validated['q'];
            $limit = $validated['limit'] ?? 20;

            // Search customers with active status only
            $customers = Customer::where('is_active', true)
                ->where(function ($q) use ($query) {
                    $q->where('name_en', 'like', "%{$query}%")
                      ->orWhere('name_ar', 'like', "%{$query}%")
                      ->orWhere('mobile_number', 'like', "%{$query}%")
                      ->orWhere('email', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get()
                ->map(function ($customer) {
                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'name_en' => $customer->name_en,
                        'name_ar' => $customer->name_ar,
                        'mobile_number' => $customer->mobile_number,
                        'email' => $customer->email,
                        'province' => $customer->province,
                        'province_en' => $customer->province_en,
                        'province_ar' => $customer->province_ar,
                        'address' => $customer->address,
                        'address_en' => $customer->address_en,
                        'address_ar' => $customer->address_ar,
                        'tax_number' => $customer->tax_number,
                        'credit_limit' => $customer->credit_limit,
                        'customer_type' => $customer->customer_type,
                        'customer_location' => $customer->customer_location,
                        'account_representative' => $customer->account_representative,
                        'total_orders_value' => $customer->total_orders_value,
                        'total_paid' => $customer->total_paid,
                        'outstanding_amount' => $customer->outstanding_amount,
                        'created_at' => $customer->created_at,
                        'updated_at' => $customer->updated_at,
                    ];
                });

            return $this->successResponse(
                [
                    'customers' => $customers,
                    'count' => $customers->count(),
                    'query' => $query,
                    'limit' => $limit,
                ],
                'Customer search completed successfully'
            );

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse(
                'An error occurred while searching customers',
                500,
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }
}