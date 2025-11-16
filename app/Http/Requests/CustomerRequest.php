<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return backpack_auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'province_en' => 'required|string|max:255',
            'province_ar' => 'required|string|max:255',
            'mobile_number' => 'required|string|max:20',
            'follow_up_person_en' => 'nullable|string|max:255',
            'follow_up_person_ar' => 'nullable|string|max:255',
            'address_en' => 'nullable|string',
            'address_ar' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'tax_number' => 'nullable|string|max:255',
            'credit_limit' => 'nullable|numeric|min:0|max:99999999.99',
            'customer_type' => 'required|in:individual,company',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'name_en' => 'الاسم (English)',
            'name_ar' => 'الاسم (العربية)',
            'province_en' => 'المحافظة (English)',
            'province_ar' => 'المحافظة (العربية)',
            'mobile_number' => 'رقم الهاتف',
            'follow_up_person_en' => 'شخص المتابعة (English)',
            'follow_up_person_ar' => 'شخص المتابعة (العربية)',
            'address_en' => 'العنوان (English)',
            'address_ar' => 'العنوان (العربية)',
            'email' => 'البريد الإلكتروني',
            'tax_number' => 'الرقم الضريبي',
            'credit_limit' => 'حد الائتمان',
            'customer_type' => 'نوع العميل',
            'is_active' => 'نشط',
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name_en.required' => 'الاسم بالإنجليزية مطلوب.',
            'name_ar.required' => 'الاسم بالعربية مطلوب.',
            'province_en.required' => 'المحافظة بالإنجليزية مطلوبة.',
            'province_ar.required' => 'المحافظة بالعربية مطلوبة.',
            'mobile_number.required' => 'رقم الهاتف مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صالح.',
            'credit_limit.numeric' => 'حد الائتمان يجب أن يكون رقماً.',
            'credit_limit.min' => 'حد الائتمان يجب أن يكون أكبر من أو يساوي 0.',
            'customer_type.required' => 'نوع العميل مطلوب.',
            'customer_type.in' => 'نوع العميل غير صالح.',
        ];
    }
}