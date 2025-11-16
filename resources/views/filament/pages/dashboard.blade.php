<x-filament-panels::page>
    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <div class="dashboard-header fade-in">
            <div class="header-content">
                <h1 class="dashboard-title">{{ __('لوحة التحكم') }}</h1>
                <p class="dashboard-subtitle">{{ __('مرحباً بك في نظام إدارة المستودعات') }}</p>
                <div class="header-stats">
                    <div class="stat-item">
                        <span class="stat-number">{{ $this->getTotalProducts() ?? '0' }}</span>
                        <span class="stat-label">{{ __('المنتجات') }}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">{{ $this->getTotalOrders() ?? '0' }}</span>
                        <span class="stat-label">{{ __('الطلبات') }}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">{{ $this->getTotalCustomers() ?? '0' }}</span>
                        <span class="stat-label">{{ __('العملاء') }}</span>
                    </div>
                </div>
            </div>
            <div class="header-actions">
                <button class="action-btn refresh-btn" onclick="refreshDashboard()">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"/>
                        <path d="M20.49 15A9 9 0 1 1 5.64 5.64L23 10"/>
                    </svg>
                    {{ __('تحديث') }}
                </button>
                <button class="action-btn settings-btn" onclick="openSettings()">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Quick Actions Bar -->
        <div class="quick-actions-bar slide-up">
            <div class="quick-actions">
                <button class="quick-action-btn" onclick="window.location.href='/admin/products/create'">
                    <div class="action-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v20M2 12h20"/>
                        </svg>
                    </div>
                    <span>{{ __('إضافة منتج') }}</span>
                </button>
                <button class="quick-action-btn" onclick="window.location.href='/admin/orders/create'">
                    <div class="action-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14,2 14,8 20,8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10,9 9,9 8,9"/>
                        </svg>
                    </div>
                    <span>{{ __('طلب جديد') }}</span>
                </button>
                <button class="quick-action-btn" onclick="window.location.href='/admin/customers/create'">
                    <div class="action-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <span>{{ __('عميل جديد') }}</span>
                </button>
                <button class="quick-action-btn" onclick="window.location.href='/admin/stock-alerts'">
                    <div class="action-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                    </div>
                    <span>{{ __('التنبيهات') }}</span>
                </button>
            </div>
        </div>

        <!-- Widget Grid -->
        <div id="widget-grid" class="widget-grid bounce-in">
            @foreach($this->getWidgets() as $index => $widget)
                <div class="widget-container" data-widget="{{ $widget }}" style="animation-delay: {{ $index * 100 }}ms">
                    <div class="widget-wrapper">
                        {{ $widget }}
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Loading Overlay -->
        <div id="loading-overlay" class="loading-overlay">
            <div class="loading-content">
                <div class="loading-spinner-large"></div>
                <p>{{ __('جاري التحميل...') }}</p>
            </div>
        </div>
    </div>

    <!-- Enhanced Styles -->
    <style>
        .dashboard-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 1.5rem;
        }

        .dark .dashboard-container {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }

        .dashboard-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .dark .dashboard-header {
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header-content {
            flex: 1;
        }

        .dashboard-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 0.5rem 0;
        }

        .dashboard-subtitle {
            font-size: 1.125rem;
            color: #64748b;
            margin: 0 0 1.5rem 0;
        }

        .dark .dashboard-subtitle {
            color: #94a3b8;
        }

        .header-stats {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
        }

        .dark .stat-number {
            color: #f1f5f9;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
        }

        .dark .stat-label {
            color: #94a3b8;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .action-btn {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 1);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px -5px rgba(0, 0, 0, 0.2);
        }

        .icon {
            width: 20px;
            height: 20px;
        }

        .quick-actions-bar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(15px);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 20px -5px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dark .quick-actions-bar {
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .quick-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .quick-action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            min-width: 120px;
        }

        .quick-action-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px -5px rgba(102, 126, 234, 0.4);
        }

        .action-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
        }

        .widget-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .widget-container {
            opacity: 0;
            transform: translateY(20px);
            animation: slideUp 0.6s ease-out forwards;
        }

        .widget-wrapper {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 20px -5px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .widget-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #667eea);
            background-size: 200% 100%;
            animation: gradientShift 3s ease-in-out infinite;
        }

        .widget-wrapper:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.15);
        }

        .dark .widget-wrapper {
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .loading-content {
            text-align: center;
            color: white;
        }

        .loading-spinner-large {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem auto;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                text-align: center;
            }

            .header-stats {
                justify-content: center;
            }

            .quick-actions {
                justify-content: center;
            }

            .widget-grid {
                grid-template-columns: 1fr;
            }
        }

        /* RTL Support */
        [dir="rtl"] .header-actions {
            flex-direction: row-reverse;
        }

        [dir="rtl"] .quick-actions {
            direction: ltr;
        }
    </style>

    <!-- Enhanced JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize sortable widgets
            new Sortable(document.getElementById('widget-grid'), {
                handle: '.widget-wrapper',
                animation: 300,
                easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
                onEnd: function(evt) {
                    console.log('Widget reordered:', evt.oldIndex, 'to', evt.newIndex);
                    // Save order to localStorage or send to server
                    saveWidgetOrder();
                }
            });

            // Add intersection observer for animations
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationPlayState = 'running';
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.widget-container').forEach(el => {
                observer.observe(el);
            });

            // Auto-refresh dashboard every 5 minutes
            setInterval(refreshDashboard, 300000);
        });

        function refreshDashboard() {
            const overlay = document.getElementById('loading-overlay');
            overlay.classList.add('show');

            // Simulate refresh (replace with actual AJAX call)
            setTimeout(() => {
                overlay.classList.remove('show');
                // Add refresh animation to widgets
                document.querySelectorAll('.widget-wrapper').forEach(el => {
                    el.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        el.style.transform = 'scale(1)';
                    }, 150);
                });
            }, 1500);
        }

        function saveWidgetOrder() {
            const order = Array.from(document.querySelectorAll('.widget-container')).map(el => el.dataset.widget);
            localStorage.setItem('dashboardWidgetOrder', JSON.stringify(order));
        }

        function openSettings() {
            // Open settings modal or navigate to settings page
            window.location.href = '/admin/user-settings';
        }

        // Add smooth scrolling for better UX
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + R for refresh
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                refreshDashboard();
            }
        });
    </script>
</x-filament-panels::page>