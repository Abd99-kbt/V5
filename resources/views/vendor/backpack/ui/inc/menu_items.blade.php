{{-- This file is used for menu items by any Backpack v6 theme --}}
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>

<x-backpack::menu-item title="Categories" icon="la la-question" :link="backpack_url('category')" />
<x-backpack::menu-item title="Suppliers" icon="la la-question" :link="backpack_url('supplier')" />
<x-backpack::menu-item title="Warehouses" icon="la la-question" :link="backpack_url('warehouse')" />
<x-backpack::menu-item title="Products" icon="la la-question" :link="backpack_url('product')" />
<x-backpack::menu-item title="Stocks" icon="la la-question" :link="backpack_url('stock')" />
<x-backpack::menu-item title="Orders" icon="la la-question" :link="backpack_url('order')" />