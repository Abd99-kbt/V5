<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ProductRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class ProductCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ProductCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Product::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/product');
        CRUD::setEntityNameStrings('منتج', 'المنتجات');
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::column('sku')->label('رمز المنتج')->type('text');
        CRUD::column('name_en')->label('اسم المنتج')->type('text');
        CRUD::column('category.name')->label('الفئة')->type('relationship');
        CRUD::column('supplier.name')->label('المورد')->type('relationship');
        CRUD::column('selling_price')->label('سعر البيع')->type('number');
        CRUD::column('total_stock')->label('إجمالي المخزون')->type('number');
        CRUD::column('is_active')->label('نشط')->type('boolean');

        // Add filters
        CRUD::filter('category_id')
            ->type('select2')
            ->label('الفئة')
            ->values(function () {
                return \App\Models\Category::where('is_active', true)->pluck('name', 'id')->toArray();
            });

        CRUD::filter('supplier_id')
            ->type('select2')
            ->label('المورد')
            ->values(function () {
                return \App\Models\Supplier::where('is_active', true)->pluck('name', 'id')->toArray();
            });
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(ProductRequest::class);

        CRUD::field('name_en')->label('اسم المنتج (English)')->type('text')->tab('المعلومات الأساسية');
        CRUD::field('name_ar')->label('اسم المنتج (العربية)')->type('text')->tab('المعلومات الأساسية');
        CRUD::field('sku')->label('رمز المنتج')->type('text')->tab('المعلومات الأساسية');
        CRUD::field('barcode')->label('الباركود')->type('text')->tab('المعلومات الأساسية');
        CRUD::field('description_en')->label('الوصف (English)')->type('textarea')->tab('المعلومات الأساسية');
        CRUD::field('description_ar')->label('الوصف (العربية)')->type('textarea')->tab('المعلومات الأساسية');
        CRUD::field('image')->label('صورة المنتج')->type('image')->tab('المعلومات الأساسية');

        CRUD::field('category_id')->label('الفئة')->type('select2')
            ->entity('category')->model(\App\Models\Category::class)
            ->attribute('name')->tab('المعلومات الأساسية');

        CRUD::field('supplier_id')->label('المورد')->type('select2')
            ->entity('supplier')->model(\App\Models\Supplier::class)
            ->attribute('name')->tab('المعلومات الأساسية');

        CRUD::field('purchase_price')->label('سعر الشراء')->type('number')->tab('الأسعار');
        CRUD::field('selling_price')->label('سعر البيع')->type('number')->tab('الأسعار');
        CRUD::field('wholesale_price')->label('سعر الجملة')->type('number')->tab('الأسعار');

        CRUD::field('min_stock_level')->label('الحد الأدنى للمخزون')->type('number')->tab('إدارة المخزون');
        CRUD::field('max_stock_level')->label('الحد الأقصى للمخزون')->type('number')->tab('إدارة المخزون');
        CRUD::field('unit')->label('الوحدة')->type('text')->tab('إدارة المخزون');
        CRUD::field('weight')->label('الوزن')->type('number')->tab('إدارة المخزون');
        CRUD::field('volume')->label('الحجم')->type('number')->tab('إدارة المخزون');

        CRUD::field('is_active')->label('نشط')->type('boolean')->tab('الإعدادات');
        CRUD::field('track_inventory')->label('تتبع المخزون')->type('boolean')->tab('الإعدادات');
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
