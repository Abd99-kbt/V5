/**
 * Enhanced UI JavaScript for Warehouse Management System
 * Modern, interactive features with Arabic RTL support
 */

class WarehouseUI {
    constructor() {
        this.init();
        this.setupEventListeners();
        this.setupRealTimeUpdates();
        this.setupCharts();
    }

    init() {
        this.isRTL = document.documentElement.dir === 'rtl';
        this.currentTheme = localStorage.getItem('theme') || 'light';
        this.setupTheme();
        this.setupAnimations();
        this.setupTooltips();
    }

    setupEventListeners() {
        // Theme toggle
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => this.toggleTheme());
        }

        // Language toggle
        const langToggle = document.getElementById('lang-toggle');
        if (langToggle) {
            langToggle.addEventListener('click', () => this.toggleLanguage());
        }

        // Search functionality
        this.setupSearch();

        // Drag and drop
        this.setupDragAndDrop();

        // Modal functionality
        this.setupModals();

        // Form enhancements
        this.setupFormEnhancements();

        // Notification system
        this.setupNotifications();
    }

    setupTheme() {
        document.documentElement.classList.toggle('dark', this.currentTheme === 'dark');

        // Update theme toggle button
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.innerHTML = this.currentTheme === 'dark' ? '☀️' : '🌙';
        }
    }

    toggleTheme() {
        this.currentTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        localStorage.setItem('theme', this.currentTheme);
        this.setupTheme();
        this.updateChartsTheme();
    }

    setupAnimations() {
        // Intersection Observer for fade-in animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, observerOptions);

        // Observe all enhanced cards and widgets
        document.querySelectorAll('.enhanced-card, .dashboard-widget, .chart-container').forEach(el => {
            observer.observe(el);
        });

        // Add staggered animation delays
        document.querySelectorAll('.metric-card').forEach((card, index) => {
            card.style.animationDelay = `${index * 100}ms`;
        });
    }

    setupSearch() {
        const searchInputs = document.querySelectorAll('.search-enhanced input');

        searchInputs.forEach(input => {
            let searchTimeout;

            input.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.performSearch(e.target.value);
                }, 300);
            });

            // Add search icon animation
            const searchIcon = input.parentElement.querySelector('.search-icon');
            if (searchIcon) {
                input.addEventListener('focus', () => {
                    searchIcon.style.transform = 'scale(1.2)';
                });

                input.addEventListener('blur', () => {
                    searchIcon.style.transform = 'scale(1)';
                });
            }
        });
    }

    performSearch(query) {
        if (!query.trim()) {
            this.showAllItems();
            return;
        }

        // Show loading state
        this.showSearchLoading();

        // Simulate API call (replace with actual API call)
        setTimeout(() => {
            const items = document.querySelectorAll('.data-item, .product-item, .order-item');
            let visibleCount = 0;

            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                const isMatch = text.includes(query.toLowerCase());

                if (isMatch) {
                    item.style.display = 'flex';
                    item.classList.add('search-highlight');
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                    item.classList.remove('search-highlight');
                }
            });

            this.showSearchResults(visibleCount, query);
        }, 300);
    }

    showSearchLoading() {
        // Show loading spinner in search results
        const resultsContainer = document.getElementById('search-results');
        if (resultsContainer) {
            resultsContainer.innerHTML = '<div class="loading-state"><div class="loading-spinner"></div><p>جاري البحث...</p></div>';
        }
    }

    showSearchResults(count, query) {
        const resultsContainer = document.getElementById('search-results');
        if (resultsContainer) {
            const message = count === 0
                ? `لا توجد نتائج للبحث: "${query}"`
                : `تم العثور على ${count} نتيجة للبحث: "${query}"`;

            resultsContainer.innerHTML = `<p class="search-results-info">${message}</p>`;
        }
    }

    showAllItems() {
        const items = document.querySelectorAll('.data-item, .product-item, .order-item');
        items.forEach(item => {
            item.style.display = 'flex';
            item.classList.remove('search-highlight');
        });

        const resultsContainer = document.getElementById('search-results');
        if (resultsContainer) {
            resultsContainer.innerHTML = '';
        }
    }

    setupDragAndDrop() {
        const dragZones = document.querySelectorAll('.drag-zone');
        const draggableItems = document.querySelectorAll('.draggable-item');

        dragZones.forEach(zone => {
            zone.addEventListener('dragover', (e) => {
                e.preventDefault();
                zone.classList.add('drag-over');
            });

            zone.addEventListener('dragleave', () => {
                zone.classList.remove('drag-over');
            });

            zone.addEventListener('drop', (e) => {
                e.preventDefault();
                zone.classList.remove('drag-over');

                const files = Array.from(e.dataTransfer.files);
                this.handleDroppedFiles(files, zone);
            });

            // Click to upload
            zone.addEventListener('click', () => {
                const input = zone.querySelector('input[type="file"]');
                if (input) input.click();
            });
        });

        draggableItems.forEach(item => {
            item.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', item.dataset.id);
                item.classList.add('dragging');
            });

            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
            });
        });
    }

    handleDroppedFiles(files, zone) {
        files.forEach(file => {
            if (file.type.startsWith('image/') || file.type === 'application/pdf') {
                this.uploadFile(file, zone);
            } else {
                this.showNotification('نوع الملف غير مدعوم', 'error');
            }
        });
    }

    uploadFile(file, zone) {
        const formData = new FormData();
        formData.append('file', file);

        // Show upload progress
        const progressContainer = document.createElement('div');
        progressContainer.className = 'upload-progress';
        progressContainer.innerHTML = `
            <div class="progress-bar">
                <div class="progress-fill" style="width: 0%"></div>
            </div>
            <p>جاري رفع ${file.name}...</p>
        `;

        zone.appendChild(progressContainer);

        // Simulate upload progress
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += 10;
            progressContainer.querySelector('.progress-fill').style.width = `${progress}%`;

            if (progress >= 100) {
                clearInterval(progressInterval);
                setTimeout(() => {
                    progressContainer.remove();
                    this.showNotification(`تم رفع ${file.name} بنجاح`, 'success');
                }, 500);
            }
        }, 200);
    }

    setupRealTimeUpdates() {
        // Setup WebSocket connection for real-time updates
        if (typeof io !== 'undefined') {
            this.socket = io();
            this.setupSocketListeners();
        } else {
            // Fallback to polling if WebSocket not available
            this.setupPollingUpdates();
        }
    }

    setupSocketListeners() {
        this.socket.on('stock-update', (data) => {
            this.updateStockDisplay(data);
        });

        this.socket.on('new-order', (data) => {
            this.showNotification('طلب جديد تم إنشاؤه', 'info');
            this.updateOrderCount();
        });

        this.socket.on('alert', (data) => {
            this.showNotification(data.message, data.type);
        });
    }

    setupPollingUpdates() {
        // Fallback polling every 30 seconds
        setInterval(() => {
            this.checkForUpdates();
        }, 30000);
    }

    async checkForUpdates() {
        try {
            const response = await fetch('/api/updates');
            const data = await response.json();

            if (data.success) {
                this.updateDashboardData(data);
            }
        } catch (error) {
            console.error('Error checking for updates:', error);
        }
    }

    updateStockDisplay(data) {
        const stockElements = document.querySelectorAll(`[data-product-id="${data.product_id}"]`);

        stockElements.forEach(element => {
            const quantityElement = element.querySelector('.stock-quantity');
            const statusElement = element.querySelector('.stock-status');

            if (quantityElement) {
                quantityElement.textContent = data.new_quantity;
                quantityElement.classList.add('updated');
                setTimeout(() => quantityElement.classList.remove('updated'), 1000);
            }

            if (statusElement) {
                statusElement.className = `status-badge status-${data.status}`;
                statusElement.textContent = this.getStatusLabel(data.status);
            }
        });
    }

    setupCharts() {
        // Initialize charts when they're visible
        const chartContainers = document.querySelectorAll('.chart-container');

        if (chartContainers.length > 0 && typeof Chart !== 'undefined') {
            chartContainers.forEach(container => {
                this.createChart(container);
            });
        }
    }

    createChart(container) {
        const canvas = container.querySelector('canvas');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const chartType = container.dataset.chartType || 'line';
        const chartData = JSON.parse(container.dataset.chartData || '{}');

        const chart = new Chart(ctx, {
            type: chartType,
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: this.isRTL ? 'left' : 'right',
                        labels: {
                            font: {
                                family: 'Cairo, Inter, sans-serif'
                            }
                        }
                    },
                    tooltip: {
                        titleFont: {
                            family: 'Cairo, Inter, sans-serif'
                        },
                        bodyFont: {
                            family: 'Cairo, Inter, sans-serif'
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            font: {
                                family: 'Cairo, Inter, sans-serif'
                            }
                        }
                    },
                    y: {
                        ticks: {
                            font: {
                                family: 'Cairo, Inter, sans-serif'
                            }
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });

        // Store chart instance for theme updates
        container._chart = chart;
    }

    updateChartsTheme() {
        document.querySelectorAll('.chart-container').forEach(container => {
            if (container._chart) {
                container._chart.update();
            }
        });
    }

    setupTooltips() {
        const tooltipElements = document.querySelectorAll('.tooltip-enhanced');

        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target);
            });

            element.addEventListener('mouseleave', () => {
                this.hideTooltip();
            });
        });
    }

    showTooltip(element) {
        const tooltip = element.querySelector('.tooltip-content');
        if (tooltip) {
            const rect = element.getBoundingClientRect();
            tooltip.style.opacity = '1';
            tooltip.style.visibility = 'visible';
        }
    }

    hideTooltip() {
        document.querySelectorAll('.tooltip-content').forEach(tooltip => {
            tooltip.style.opacity = '0';
            tooltip.style.visibility = 'hidden';
        });
    }

    setupModals() {
        const modalTriggers = document.querySelectorAll('[data-modal-target]');
        const modalClosers = document.querySelectorAll('[data-modal-close]');

        modalTriggers.forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                const modalId = trigger.dataset.modalTarget;
                this.openModal(modalId);
            });
        });

        modalClosers.forEach(closer => {
            closer.addEventListener('click', () => {
                this.closeModal();
            });
        });

        // Close modal on backdrop click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-enhanced')) {
                this.closeModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeModal();
            }
        });
    }

    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';

            // Focus management for accessibility
            const focusableElement = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (focusableElement) {
                focusableElement.focus();
            }
        }
    }

    closeModal() {
        const modals = document.querySelectorAll('.modal-enhanced.show');
        modals.forEach(modal => {
            modal.classList.remove('show');
        });
        document.body.style.overflow = '';
    }

    setupFormEnhancements() {
        const forms = document.querySelectorAll('.enhanced-form');

        forms.forEach(form => {
            const inputs = form.querySelectorAll('input, select, textarea');

            inputs.forEach(input => {
                // Add floating label effect
                input.addEventListener('focus', () => {
                    input.parentElement.classList.add('focused');
                });

                input.addEventListener('blur', () => {
                    if (!input.value) {
                        input.parentElement.classList.remove('focused');
                    }
                });

                // Real-time validation
                input.addEventListener('input', () => {
                    this.validateField(input);
                });
            });
        });
    }

    validateField(input) {
        const validationRules = input.dataset.validation ? JSON.parse(input.dataset.validation) : {};

        let isValid = true;
        let errorMessage = '';

        // Required validation
        if (validationRules.required && !input.value.trim()) {
            isValid = false;
            errorMessage = 'هذا الحقل مطلوب';
        }

        // Email validation
        if (validationRules.email && input.value && !this.isValidEmail(input.value)) {
            isValid = false;
            errorMessage = 'البريد الإلكتروني غير صالح';
        }

        // Min length validation
        if (validationRules.minLength && input.value.length < validationRules.minLength) {
            isValid = false;
            errorMessage = `الحد الأدنى للطول ${validationRules.minLength} أحرف`;
        }

        // Show/hide validation message
        const errorElement = input.parentElement.querySelector('.field-error');
        if (errorElement) {
            errorElement.textContent = errorMessage;
            errorElement.style.display = isValid ? 'none' : 'block';
        }

        // Update field styling
        input.classList.toggle('invalid', !isValid);
        input.classList.toggle('valid', isValid);

        return isValid;
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    setupNotifications() {
        this.notificationsContainer = document.getElementById('notifications');
        if (!this.notificationsContainer) {
            this.notificationsContainer = document.createElement('div');
            this.notificationsContainer.id = 'notifications';
            this.notificationsContainer.className = 'notifications-container';
            document.body.appendChild(this.notificationsContainer);
        }
    }

    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `toast-notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-message">${message}</span>
                <button class="notification-close">&times;</button>
            </div>
        `;

        this.notificationsContainer.appendChild(notification);

        // Auto remove after duration
        setTimeout(() => {
            this.removeNotification(notification);
        }, duration);

        // Manual close
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            this.removeNotification(notification);
        });

        // Animate in
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
    }

    removeNotification(notification) {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }

    updateDashboardData(data) {
        // Update metric cards
        Object.keys(data.metrics || {}).forEach(key => {
            const element = document.getElementById(`metric-${key}`);
            if (element) {
                element.textContent = data.metrics[key];
                element.classList.add('updated');
                setTimeout(() => element.classList.remove('updated'), 1000);
            }
        });

        // Update charts
        this.updateChartsData(data.charts || {});

        // Update alerts
        if (data.alerts) {
            this.updateAlerts(data.alerts);
        }
    }

    updateChartsData(chartsData) {
        Object.keys(chartsData).forEach(chartId => {
            const container = document.getElementById(`chart-${chartId}`);
            if (container && container._chart) {
                container._chart.data = chartsData[chartId];
                container._chart.update();
            }
        });
    }

    updateAlerts(alerts) {
        const alertsContainer = document.getElementById('alerts-container');
        if (!alertsContainer) return;

        alertsContainer.innerHTML = '';

        alerts.forEach(alert => {
            const alertElement = document.createElement('div');
            alertElement.className = `alert-enhanced alert-${alert.type}`;
            alertElement.innerHTML = `
                <div class="alert-content">
                    <span class="alert-message">${alert.message}</span>
                    <span class="alert-time">${this.formatTime(alert.created_at)}</span>
                </div>
            `;
            alertsContainer.appendChild(alertElement);
        });
    }

    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) return 'منذ لحظات';
        if (diff < 3600000) return `منذ ${Math.floor(diff / 60000)} دقيقة`;
        if (diff < 86400000) return `منذ ${Math.floor(diff / 3600000)} ساعة`;

        return date.toLocaleDateString('ar-SA');
    }

    getStatusLabel(status) {
        const statusLabels = {
            'active': 'نشط',
            'inactive': 'غير نشط',
            'pending': 'في الانتظار',
            'confirmed': 'مؤكد',
            'processing': 'قيد المعالجة',
            'shipped': 'تم الشحن',
            'delivered': 'تم التسليم',
            'cancelled': 'ملغي',
            'low_stock': 'انخفاض المخزون',
            'out_of_stock': 'نفاد المخزون'
        };

        return statusLabels[status] || status;
    }

    // Utility method to add CSS classes with animation
    addClassWithAnimation(element, className) {
        element.classList.add(className);

        // Remove class after animation completes
        const duration = getComputedStyle(element).transitionDuration;
        const durationMs = parseFloat(duration) * 1000;

        setTimeout(() => {
            element.classList.remove(className);
        }, durationMs);
    }

    // Debounce utility
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Throttle utility
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        }
    }
}

// Page transition system
setupPageTransitions() {
    // Intercept navigation links
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a[href]');
        if (link && link.href.includes(window.location.origin) && !link.href.includes('#')) {
            e.preventDefault();
            this.navigateToPage(link.href);
        }
    });

    // Handle browser back/forward
    window.addEventListener('popstate', (e) => {
        if (e.state && e.state.page) {
            this.loadPageContent(e.state.page, false);
        }
    });

    // Handle form submissions
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.method.toLowerCase() === 'get' || form.action.includes(window.location.origin)) {
            e.preventDefault();
            this.submitForm(form);
        }
    });
}

navigateToPage(url, addToHistory = true) {
    this.showPageTransition('جاري التحميل...');

    // Add to history if needed
    if (addToHistory) {
        history.pushState({ page: url }, '', url);
    }

    // Simulate page load (replace with actual navigation)
    setTimeout(() => {
        this.loadPageContent(url);
    }, 800);
}

loadPageContent(url, showTransition = true) {
    if (showTransition) {
        this.showPageTransition('جاري التحميل...');
    }

    // Simulate content loading (replace with actual AJAX)
    setTimeout(() => {
        this.hidePageTransition();

        // Add page loaded animation
        const mainContent = document.querySelector('main, .content, #content');
        if (mainContent) {
            mainContent.classList.add('fade-in');
        }

        // Update active navigation
        this.updateActiveNavigation(url);
    }, 500);
}

showPageTransition(message = 'جاري التحميل...') {
    let transition = document.getElementById('page-transition');
    if (!transition) {
        transition = document.createElement('div');
        transition.id = 'page-transition';
        transition.className = 'page-transition';
        transition.innerHTML = `
            <div class="spinner"></div>
            <div class="message">${message}</div>
        `;
        document.body.appendChild(transition);
    }

    transition.classList.add('show');
}

hidePageTransition() {
    const transition = document.getElementById('page-transition');
    if (transition) {
        transition.classList.remove('show');
        setTimeout(() => {
            transition.remove();
        }, 500);
    }
}

updateActiveNavigation(currentUrl) {
    // Update navigation active states
    document.querySelectorAll('.nav-link, .sidebar-link').forEach(link => {
        link.classList.toggle('active', link.href === currentUrl);
    });
}

submitForm(form) {
    this.showPageTransition('جاري الحفظ...');

    // Simulate form submission (replace with actual AJAX)
    setTimeout(() => {
        this.hidePageTransition();
        this.showNotification('تم الحفظ بنجاح', 'success');
    }, 1000);
}

// Enhanced animation methods
animateElement(element, animationClass, duration = 300) {
    element.classList.add(animationClass);

    setTimeout(() => {
        element.classList.remove(animationClass);
    }, duration);
}

// Staggered animations for lists
animateListItems(selector) {
    document.querySelectorAll(selector).forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';

        setTimeout(() => {
            item.style.transition = 'all 0.3s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

// Smooth scroll to element
scrollToElement(selector, offset = 0) {
    const element = document.querySelector(selector);
    if (element) {
        const elementPosition = element.getBoundingClientRect().top + window.pageYOffset;
        window.scrollTo({
            top: elementPosition - offset,
            behavior: 'smooth'
        });
    }
}

// Enhanced search with debouncing
setupEnhancedSearch() {
    const searchInputs = document.querySelectorAll('.search-enhanced input');

    searchInputs.forEach(input => {
        let searchTimeout;

        input.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.performEnhancedSearch(e.target.value);
            }, 300);
        });

        input.addEventListener('focus', () => {
            input.parentElement.classList.add('focused');
        });

        input.addEventListener('blur', () => {
            input.parentElement.classList.remove('focused');
        });
    });
}

performEnhancedSearch(query) {
    if (!query.trim()) {
        this.clearSearchResults();
        return;
    }

    this.showSearchLoading();

    // Simulate search (replace with actual API call)
    setTimeout(() => {
        const results = this.mockSearchResults(query);
        this.displaySearchResults(results, query);
    }, 300);
}

mockSearchResults(query) {
    // Mock search results (replace with actual search logic)
    const allItems = [
        { title: 'منتج تجريبي 1', type: 'product', url: '/admin/products/1' },
        { title: 'طلب تجريبي 1', type: 'order', url: '/admin/orders/1' },
        { title: 'عميل تجريبي 1', type: 'customer', url: '/admin/customers/1' },
    ];

    return allItems.filter(item =>
        item.title.toLowerCase().includes(query.toLowerCase())
    );
}

displaySearchResults(results, query) {
    const resultsContainer = document.getElementById('search-results');
    if (!resultsContainer) return;

    if (results.length === 0) {
        resultsContainer.innerHTML = `
            <div class="no-results">
                <p>لا توجد نتائج للبحث: "${query}"</p>
            </div>
        `;
    } else {
        resultsContainer.innerHTML = results.map(result => `
            <div class="search-result-item" onclick="window.location.href='${result.url}'">
                <div class="result-icon">
                    ${this.getResultIcon(result.type)}
                </div>
                <div class="result-content">
                    <div class="result-title">${result.title}</div>
                    <div class="result-type">${this.getResultTypeLabel(result.type)}</div>
                </div>
            </div>
        `).join('');
    }

    resultsContainer.classList.add('show');
}

getResultIcon(type) {
    const icons = {
        product: '📦',
        order: '📋',
        customer: '👤',
        invoice: '🧾',
        supplier: '🏭'
    };
    return icons[type] || '📄';
}

getResultTypeLabel(type) {
    const labels = {
        product: 'منتج',
        order: 'طلب',
        customer: 'عميل',
        invoice: 'فاتورة',
        supplier: 'مورد'
    };
    return labels[type] || type;
}

clearSearchResults() {
    const resultsContainer = document.getElementById('search-results');
    if (resultsContainer) {
        resultsContainer.classList.remove('show');
        resultsContainer.innerHTML = '';
    }
}

showSearchLoading() {
    const resultsContainer = document.getElementById('search-results');
    if (resultsContainer) {
        resultsContainer.innerHTML = `
            <div class="search-loading">
                <div class="loading-dots">
                    <div class="loading-dot"></div>
                    <div class="loading-dot"></div>
                    <div class="loading-dot"></div>
                </div>
                <p>جاري البحث...</p>
            </div>
        `;
        resultsContainer.classList.add('show');
    }
}

// Enhanced modal system
setupEnhancedModals() {
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-modal]');
        if (trigger) {
            e.preventDefault();
            this.openEnhancedModal(trigger.dataset.modal);
        }

        const closer = e.target.closest('[data-modal-close]');
        if (closer) {
            this.closeEnhancedModal();
        }
    });

    // Close modal on escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            this.closeEnhancedModal();
        }
    });
}

openEnhancedModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        // Focus management
        const focusableElement = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusableElement) {
            focusableElement.focus();
        }
    }
}

closeEnhancedModal() {
    const modals = document.querySelectorAll('.modal-modern.show, .modal-enhanced.show');
    modals.forEach(modal => {
        modal.classList.remove('show');
    });
    document.body.style.overflow = '';
}

// Enhanced form validation
setupEnhancedFormValidation() {
    const forms = document.querySelectorAll('.enhanced-form');

    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, select, textarea');

        inputs.forEach(input => {
            input.addEventListener('blur', () => {
                this.validateField(input);
            });

            input.addEventListener('input', () => {
                if (input.classList.contains('invalid')) {
                    this.validateField(input);
                }
            });
        });
    });
}

validateField(input) {
    const rules = this.getValidationRules(input);
    const value = input.value.trim();
    let isValid = true;
    let errorMessage = '';

    // Required validation
    if (rules.required && !value) {
        isValid = false;
        errorMessage = 'هذا الحقل مطلوب';
    }

    // Email validation
    if (rules.email && value && !this.isValidEmail(value)) {
        isValid = false;
        errorMessage = 'البريد الإلكتروني غير صالح';
    }

    // Min length validation
    if (rules.minLength && value.length < rules.minLength) {
        isValid = false;
        errorMessage = `الحد الأدنى للطول ${rules.minLength} أحرف`;
    }

    // Max length validation
    if (rules.maxLength && value.length > rules.maxLength) {
        isValid = false;
        errorMessage = `الحد الأقصى للطول ${rules.maxLength} حرف`;
    }

    // Update field state
    this.updateFieldState(input, isValid, errorMessage);

    return isValid;
}

getValidationRules(input) {
    const rules = {};
    const dataRules = input.dataset.validation;

    if (dataRules) {
        Object.assign(rules, JSON.parse(dataRules));
    }

    // Check for HTML5 validation attributes
    if (input.hasAttribute('required')) {
        rules.required = true;
    }

    if (input.hasAttribute('minlength')) {
        rules.minLength = parseInt(input.getAttribute('minlength'));
    }

    if (input.hasAttribute('maxlength')) {
        rules.maxLength = parseInt(input.getAttribute('maxlength'));
    }

    if (input.type === 'email') {
        rules.email = true;
    }

    return rules;
}

updateFieldState(input, isValid, errorMessage) {
    const formField = input.closest('.form-field');
    if (!formField) return;

    // Update classes
    input.classList.toggle('valid', isValid);
    input.classList.toggle('invalid', !isValid);
    formField.classList.toggle('error', !isValid);
    formField.classList.toggle('success', isValid);

    // Update error message
    let errorElement = formField.querySelector('.field-feedback');
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = 'field-feedback error-message';
        formField.appendChild(errorElement);
    }

    errorElement.textContent = errorMessage;
    errorElement.style.display = isValid ? 'none' : 'block';
}

// Enhanced notification system
showEnhancedNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `notification-modern ${type}`;
    notification.innerHTML = `
        <div class="notification-content-modern">
            <div class="notification-icon">
                ${this.getNotificationIcon(type)}
            </div>
            <div class="notification-message">${message}</div>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
        </div>
    `;

    // Add to notifications container
    let container = document.getElementById('notifications-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notifications-container';
        container.className = 'notifications-container';
        document.body.appendChild(container);
    }

    container.appendChild(notification);

    // Animate in
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);

    // Auto remove
    setTimeout(() => {
        this.removeNotification(notification);
    }, duration);
}

getNotificationIcon(type) {
    const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };
    return icons[type] || icons.info;
}

removeNotification(notification) {
    notification.classList.remove('show');
    setTimeout(() => {
        notification.remove();
    }, 300);
}

// Performance monitoring
setupPerformanceMonitoring() {
    // Monitor page load time
    window.addEventListener('load', () => {
        const loadTime = performance.now();
        console.log(`Page loaded in ${Math.round(loadTime)}ms`);

        if (loadTime > 3000) {
            this.showEnhancedNotification('الصفحة استغرقت وقتاً طويلاً في التحميل', 'warning');
        }
    });

    // Monitor resource loading
    const observer = new PerformanceObserver((list) => {
        list.getEntries().forEach((entry) => {
            if (entry.duration > 1000) {
                console.warn(`Slow resource: ${entry.name} took ${Math.round(entry.duration)}ms`);
            }
        });
    });

    observer.observe({ entryTypes: ['resource'] });
}

// Accessibility enhancements
setupAccessibilityEnhancements() {
    // Add skip links
    const skipLink = document.createElement('a');
    skipLink.href = '#main-content';
    skipLink.className = 'skip-link';
    skipLink.textContent = 'تخطي إلى المحتوى الرئيسي';
    document.body.insertBefore(skipLink, document.body.firstChild);

    // Add main content ID if not exists
    const mainContent = document.querySelector('main, .content, #content');
    if (mainContent && !mainContent.id) {
        mainContent.id = 'main-content';
    }

    // Enhanced focus management
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Tab') {
            document.body.classList.add('keyboard-navigation');
        }
    });

    document.addEventListener('mousedown', () => {
        document.body.classList.remove('keyboard-navigation');
    });

    // Add ARIA labels for better screen reader support
    document.querySelectorAll('.btn-enhanced').forEach(btn => {
        if (!btn.getAttribute('aria-label')) {
            btn.setAttribute('aria-label', btn.textContent.trim());
        }
    });
}

// Theme management
setupThemeManagement() {
    // Listen for system theme changes
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

    mediaQuery.addEventListener('change', (e) => {
        if (!localStorage.getItem('theme')) {
            this.currentTheme = e.matches ? 'dark' : 'light';
            this.setupTheme();
        }
    });

    // Add theme transition
    document.documentElement.style.setProperty('--theme-transition', 'background-color 0.3s ease, color 0.3s ease');
}

// Error handling
setupErrorHandling() {
    window.addEventListener('error', (e) => {
        console.error('JavaScript Error:', e.error);
        this.showEnhancedNotification('حدث خطأ غير متوقع', 'error');
    });

    window.addEventListener('unhandledrejection', (e) => {
        console.error('Unhandled Promise Rejection:', e.reason);
        this.showEnhancedNotification('حدث خطأ في الاتصال', 'error');
    });
}

// Utility methods
debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

// Initialize all enhanced features
init() {
    this.isRTL = document.documentElement.dir === 'rtl';
    this.currentTheme = localStorage.getItem('theme') || 'light';
    this.setupTheme();
    this.setupAnimations();
    this.setupTooltips();
    this.setupPageTransitions();
    this.setupEnhancedSearch();
    this.setupEnhancedModals();
    this.setupEnhancedFormValidation();
    this.setupAccessibilityEnhancements();
    this.setupThemeManagement();
    this.setupErrorHandling();
    this.setupPerformanceMonitoring();
}
}

// Initialize the enhanced UI when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
window.warehouseUI = new WarehouseUI();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
module.exports = WarehouseUI;
}