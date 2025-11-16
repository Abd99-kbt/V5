<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CustomerRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class CustomerCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class CustomerCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Customer::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/customer');
        CRUD::setEntityNameStrings('العميل', 'العملاء');
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::column('name')->label('الاسم')->type('text');
        CRUD::column('province')->label('المحافظة')->type('text');
        CRUD::column('mobile_number')->label('رقم الهاتف')->type('text');
        CRUD::column('follow_up_person')->label('شخص المتابعة')->type('text');
        CRUD::column('address')->label('العنوان')->type('text');
        CRUD::column('email')->label('البريد الإلكتروني')->type('email');
        CRUD::column('tax_number')->label('الرقم الضريبي')->type('text');
        CRUD::column('credit_limit')->label('حد الائتمان')->type('number');
        CRUD::column('customer_type')->label('نوع العميل')->type('select_from_array')->options([
            'individual' => 'فرد',
            'company' => 'شركة',
        ]);
        CRUD::column('is_active')->label('نشط')->type('boolean');
        CRUD::column('orders_count')->label('عدد الطلبات')->type('number');
        CRUD::column('invoices_count')->label('عدد الفواتير')->type('number');
        CRUD::column('total_orders_value')->label('إجمالي قيمة الطلبات')->type('number');
        CRUD::column('total_paid')->label('إجمالي المدفوع')->type('number');
        CRUD::column('outstanding_amount')->label('المبلغ المستحق')->type('number');
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(CustomerRequest::class);

        CRUD::field('name_en')->label('الاسم (English)')->type('text')->tab('المعلومات الأساسية');
        CRUD::field('name_ar')->label('الاسم (العربية)')->type('text')->tab('المعلومات الأساسية');
        CRUD::field('province_en')->label('المحافظة (English)')->type('text')->tab('المعلومات الأساسية');
        CRUD::field('province_ar')->label('المحافظة (العربية)')->type('text')->tab('المعلومات الأساسية');
        CRUD::field('mobile_number')->label('رقم الهاتف')->type('text')->tab('المعلومات الأساسية');
        CRUD::field('follow_up_person_en')->label('شخص المتابعة (English)')->type('text')->tab('المعلومات الأساسية');
        CRUD::field('follow_up_person_ar')->label('شخص المتابعة (العربية)')->type('text')->tab('المعلومات الأساسية');
        CRUD::field('address_en')->label('العنوان (English)')->type('textarea')->tab('المعلومات الأساسية');
        CRUD::field('address_ar')->label('العنوان (العربية)')->type('textarea')->tab('المعلومات الأساسية');
        CRUD::field('email')->label('البريد الإلكتروني')->type('email')->tab('المعلومات الأساسية');
        CRUD::field('tax_number')->label('الرقم الضريبي')->type('text')->tab('المعلومات الأساسية');
        CRUD::field('credit_limit')->label('حد الائتمان')->type('number')->tab('المعلومات المالية');
        CRUD::field('customer_type')->label('نوع العميل')->type('select_from_array')->options([
            'individual' => 'فرد',
            'company' => 'شركة',
        ])->tab('المعلومات المالية');
        CRUD::field('is_active')->label('نشط')->type('boolean')->tab('الإعدادات');
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}