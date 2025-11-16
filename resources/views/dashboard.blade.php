@extends('layouts.app')

@section('title', 'لوحة التحكم')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">
        <i class="fas fa-tachometer-alt me-2 text-primary"></i>
        لوحة التحكم
    </h2>
    <div class="text-muted">
        <i class="fas fa-clock me-1"></i>
        {{ now()->locale('ar')->format('l، d F Y - H:i') }}
    </div>
</div>

<!-- Welcome Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-gradient-primary text-white">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="mb-1">
                            مرحباً، {{ Auth::guard('username')->user()->name }}
                        </h4>
                        <p class="mb-0 opacity-75">
                            تسجيل الدخول بنجاح باستخدام اسم المستخدم: 
                            <strong>{{ Auth::guard('username')->user()->username }}</strong>
                        </p>
                        @if(Auth::guard('username')->user()->email)
                        <small class="opacity-75">
                            البريد الإلكتروني: {{ Auth::guard('username')->user()->email }}
                        </small>
                        @else
                        <small class="opacity-75">
                            لم يتم تحديد بريد إلكتروني
                        </small>
                        @endif
                    </div>
                    <div class="col-auto">
                        <div class="display-4">
                            <i class="fas fa-user-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="display-6 text-primary mb-2">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h5 class="card-title">الطلبات</h5>
                <p class="card-text text-muted">
                    <span class="badge bg-primary fs-6">0</span>
                    <br>إجمالي الطلبات
                </p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="display-6 text-success mb-2">
                    <i class="fas fa-warehouse"></i>
                </div>
                <h5 class="card-title">المستودع</h5>
                <p class="card-text text-muted">
                    <span class="badge bg-success fs-6">0</span>
                    <br>إدارة المخزون
                </p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="display-6 text-warning mb-2">
                    <i class="fas fa-boxes"></i>
                </div>
                <h5 class="card-title">المنتجات</h5>
                <p class="card-text text-muted">
                    <span class="badge bg-warning fs-6">0</span>
                    <br>إدارة المنتجات
                </p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="display-6 text-info mb-2">
                    <i class="fas fa-users"></i>
                </div>
                <h5 class="card-title">العملاء</h5>
                <p class="card-text text-muted">
                    <span class="badge bg-info fs-6">0</span>
                    <br>إدارة العملاء
                </p>
            </div>
        </div>
    </div>
</div>

<!-- System Information -->
<div class="row">
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    معلومات النظام
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <small class="text-muted">إصدار النظام</small>
                        <div class="fw-bold">5.0</div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">بيئة التشغيل</small>
                        <div class="fw-bold">Production</div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-6">
                        <small class="text-muted">طريقة المصادقة</small>
                        <div class="fw-bold">اسم المستخدم</div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">آخر تحديث</small>
                        <div class="fw-bold">{{ now()->locale('ar')->format('Y-m-d H:i') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-shield-alt me-2"></i>
                    الأمان والمصادقة
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <span class="badge bg-success">
                        <i class="fas fa-check me-1"></i>
                        تم تسجيل الدخول بنجاح
                    </span>
                </div>
                <small class="text-muted d-block">
                    آخر تسجيل دخول: {{ now()->locale('ar')->format('Y-m-d H:i:s') }}
                </small>
                <small class="text-muted d-block">
                    طريقة المصادقة: Arabic Username Authentication
                </small>
                <small class="text-muted d-block">
                    حالة الجلسة: نشطة
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    إجراءات سريعة
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-primary w-100">
                            <i class="fas fa-plus me-1"></i>
                            إضافة طلب جديد
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-success w-100">
                            <i class="fas fa-box me-1"></i>
                            إدارة المنتجات
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="{{ route('admin.stocks.index') }}" class="btn btn-outline-warning w-100">
                            <i class="fas fa-warehouse me-1"></i>
                            إدارة المخزون
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-info w-100">
                            <i class="fas fa-users me-1"></i>
                            إدارة العملاء
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Authentication Test Results -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle me-2"></i>
                    تم تفعيل نظام المصادقة بالأسماء العربية بنجاح
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <h6>ميزات النظام الجديد:</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>دعم الأسماء العربية</li>
                            <li><i class="fas fa-check text-success me-2"></i>تسجيل دخول بدون @</li>
                            <li><i class="fas fa-check text-success me-2"></i>حقل البريد الإلكتروني اختياري</li>
                            <li><i class="fas fa-check text-success me-2"></i>واجهة عربية كاملة</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6>أسماء المستخدمين المتاحة:</h6>
                        <ul class="list-unstyled">
                            <li><code>مدير_شامل</code></li>
                            <li><code>موظف_مبيعات</code></li>
                            <li><code>محاسب</code></li>
                            <li><code>مسؤول_مستودع</code></li>
                            <li><code>موظف_مستودع</code></li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6>الكلمة السرية الافتراضية:</h6>
                        <p class="text-muted">كل كلمة مرور للمستخدم هي: <code>password123</code></p>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i>
                            <small>يمكنك تسجيل الدخول الآن باستخدام أي من الأسماء أعلاه</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(45deg, #007bff, #0056b3);
}

.card {
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-2px);
}

.display-6 {
    font-size: 3rem;
}

.badge {
    font-size: 0.8rem;
}

code {
    background-color: #f8f9fa;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 0.9em;
}
</style>

@endsection