/**
 * Digital Forecast Hub - Main JavaScript
 * Handles common functionality across the platform
 */

// Global variables
let isLoggedIn = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
let currentUserId = <?php echo isLoggedIn() ? '"' . getCurrentUserId() . '"' : 'null'; ?>;
let csrfToken = '<?php echo generateCSRFToken(); ?>';

// DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

/**
 * Initialize application
 */
function initializeApp() {
    // Navigation
    initNavigation();
    initUserDropdown();
    initMobileMenu();

    // Forms
    initForms();
    initPasswordToggles();

    // UI Components
    initTooltips();
    initModals();
    initAlerts();

    // Real-time features
    if (isLoggedIn) {
        initRealTimeUpdates();
    }

    // Performance monitoring
    if (typeof performance !== 'undefined') {
        trackPageLoad();
    }
}

/**
 * Navigation functionality
 */
function initNavigation() {
    // Highlight current page
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');

    navLinks.forEach(link => {
        if (link.getAttribute('href') === currentPath ||
            link.getAttribute('href') === currentPath + 'index.php') {
            link.classList.add('active');
        }
    });
}

/**
 * User dropdown functionality
 */
function initUserDropdown() {
    const dropdownToggle = document.getElementById('user-dropdown-toggle');
    const dropdownMenu = document.getElementById('user-dropdown-menu');

    if (!dropdownToggle || !dropdownMenu) return;

    // Toggle dropdown
    dropdownToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function() {
        dropdownMenu.classList.remove('show');
    });

    // Prevent closing when clicking inside dropdown
    dropdownMenu.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Close dropdown on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            dropdownMenu.classList.remove('show');
        }
    });
}

/**
 * Mobile menu functionality
 */
function initMobileMenu() {
    const navbarToggle = document.getElementById('navbar-toggle');
    const navbarMenu = document.getElementById('navbar-menu');

    if (!navbarToggle || !navbarMenu) return;

    // Toggle mobile menu
    navbarToggle.addEventListener('click', function() {
        navbarMenu.classList.toggle('show');
        navbarToggle.classList.toggle('active');
    });

    // Close menu on window resize if desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            navbarMenu.classList.remove('show');
            navbarToggle.classList.remove('active');
        }
    });
}

/**
 * Form initialization
 */
function initForms() {
    // Add CSRF token to all forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        if (!form.querySelector('input[name="csrf_token"]')) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
        }
    });

    // Handle AJAX forms
    const ajaxForms = document.querySelectorAll('.ajax-form');
    ajaxForms.forEach(form => {
        form.addEventListener('submit', handleAjaxForm);
    });
}

/**
 * Handle AJAX form submission
 */
async function handleAjaxForm(e) {
    e.preventDefault();

    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;

    try {
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="loading"></span> Loading...';

        const formData = new FormData(form);
        const response = await fetch(form.action, {
            method: form.method,
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await response.json();

        if (result.success) {
            showAlert(result.message, 'success');

            // Handle success actions
            if (result.redirect) {
                setTimeout(() => {
                    window.location.href = result.redirect;
                }, 1500);
            } else if (result.reload) {
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                // Reset form if no redirect/reload needed
                form.reset();
            }
        } else {
            showAlert(result.message || 'An error occurred', 'error');
        }

    } catch (error) {
        console.error('Form submission error:', error);
        showAlert('Network error. Please try again.', 'error');
    } finally {
        // Restore button state
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

/**
 * Password toggle functionality
 */
function initPasswordToggles() {
    const toggles = document.querySelectorAll('.password-toggle');

    toggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = '🙈'; // Hide icon
            } else {
                input.type = 'password';
                icon.textContent = '👁️'; // Show icon
            }
        });
    });
}

/**
 * Initialize tooltips
 */
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');

    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.dataset.tooltip;
            document.body.appendChild(tooltip);

            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
        });

        element.addEventListener('mouseleave', function() {
            const tooltip = document.querySelector('.tooltip');
            if (tooltip) {
                tooltip.remove();
            }
        });
    });
}

/**
 * Initialize modal functionality
 */
function initModals() {
    // Close modals when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeModal(e.target);
        }
    });

    // Close modals with escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                closeModal(openModal);
            }
        }
    });
}

/**
 * Open modal
 */
function openModal(modalId, content = null) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    if (content) {
        modal.querySelector('.modal-body').innerHTML = content;
    }

    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

/**
 * Close modal
 */
function closeModal(modal) {
    modal.classList.remove('show');
    document.body.style.overflow = '';
}

/**
 * Show alert message
 */
function showAlert(message, type = 'info', duration = 5000) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;

    // Position alert
    alert.style.position = 'fixed';
    alert.style.top = '100px';
    alert.style.right = '20px';
    alert.style.zIndex = '9999';
    alert.style.maxWidth = '400px';
    alert.style.boxShadow = 'var(--shadow-lg)';
    alert.style.animation = 'slideInRight 0.3s ease';

    document.body.appendChild(alert);

    // Auto-remove
    setTimeout(() => {
        if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
        }
    }, duration);
}

/**
 * Initialize existing alerts
 */
function initAlerts() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach((alert, index) => {
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.animation = 'slideInRight 0.3s ease reverse';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 300);
            }
        }, 5000 + (index * 1000)); // Stagger removal times
    });
}

/**
 * Real-time updates
 */
function initRealTimeUpdates() {
    // Poll for updates every 30 seconds
    setInterval(fetchUpdates, 30000);

    // Listen for server-sent events if supported
    if ('EventSource' in window) {
        initEventSource();
    }
}

/**
 * Fetch real-time updates
 */
async function fetchUpdates() {
    try {
        const response = await fetch('/api/updates.php', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (response.ok) {
            const updates = await response.json();
            handleRealTimeUpdates(updates);
        }
    } catch (error) {
        console.error('Failed to fetch updates:', error);
    }
}

/**
 * Initialize Server-Sent Events
 */
function initEventSource() {
    try {
        const eventSource = new EventSource('/api/events.php');

        eventSource.onmessage = function(event) {
            const updates = JSON.parse(event.data);
            handleRealTimeUpdates(updates);
        };

        eventSource.onerror = function(event) {
            console.error('EventSource failed:', event);
            eventSource.close();
        };

    } catch (error) {
        console.error('EventSource not supported:', error);
    }
}

/**
 * Handle real-time updates
 */
function handleRealTimeUpdates(updates) {
    if (updates.newMessages) {
        updateChatMessages(updates.newMessages);
    }

    if (updates.matchUpdates) {
        updateMatches(updates.matchUpdates);
    }

    if (updates.balanceUpdate) {
        updateBalance(updates.balanceUpdate);
    }

    if (updates.notifications) {
        showNotifications(updates.notifications);
    }
}

/**
 * Update chat messages
 */
function updateChatMessages(messages) {
    const messageContainer = document.querySelector('.message-list');
    if (!messageContainer) return;

    messages.forEach(message => {
        const messageElement = createMessageElement(message);
        messageContainer.appendChild(messageElement);
    });

    // Scroll to bottom
    messageContainer.scrollTop = messageContainer.scrollHeight;
}

/**
 * Create message element
 */
function createMessageElement(message) {
    const div = document.createElement('div');
    div.className = 'message-item';
    div.innerHTML = `
        <img src="${message.avatar}" alt="Avatar" class="message-avatar">
        <div class="message-content">
            <div class="message-header">
                <span class="message-username">${message.username}</span>
                <span class="message-time">${formatTime(message.created_at)}</span>
            </div>
            <p class="message-text">${message.message}</p>
        </div>
    `;
    return div;
}

/**
 * Update match information
 */
function updateMatches(matchUpdates) {
    matchUpdates.forEach(update => {
        const matchElement = document.querySelector(`[data-match-id="${update.match_id}"]`);
        if (matchElement) {
            updateMatchElement(matchElement, update);
        }
    });
}

/**
 * Update user balance display
 */
function updateBalance(newBalance) {
    const balanceElements = document.querySelectorAll('.user-coins');
    balanceElements.forEach(element => {
        element.textContent = formatCoins(newBalance);

        // Add animation
        element.style.animation = 'pulse 0.5s ease';
        setTimeout(() => {
            element.style.animation = '';
        }, 500);
    });
}

/**
 * Show notifications
 */
function showNotifications(notifications) {
    notifications.forEach(notification => {
        showAlert(notification.message, notification.type, 8000);
    });
}

/**
 * Format time
 */
function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return date.toLocaleDateString();
}

/**
 * Format coins
 */
function formatCoins(amount) {
    return parseFloat(amount).toFixed(2) + ' AC';
}

/**
 * Track page load performance
 */
function trackPageLoad() {
    if (performance.timing) {
        const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;

        // Log to console in development
        if (window.location.hostname === 'localhost') {
            console.log(`Page load time: ${loadTime}ms`);
        }

        // Send to analytics if available
        if (typeof gtag !== 'undefined') {
            gtag('event', 'page_load_time', {
                custom_parameter: loadTime
            });
        }
    }
}

/**
 * Debounce function
 */
function debounce(func, wait) {
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

/**
 * Throttle function
 */
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Copy to clipboard
 */
async function copyToClipboard(text) {
    try {
        if (navigator.clipboard) {
            await navigator.clipboard.writeText(text);
            showAlert('Copied to clipboard', 'success');
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showAlert('Copied to clipboard', 'success');
        }
    } catch (err) {
        console.error('Failed to copy text: ', err);
        showAlert('Failed to copy to clipboard', 'error');
    }
}

/**
 * API request helper
 */
async function apiRequest(url, options = {}) {
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        }
    };

    const mergedOptions = { ...defaultOptions, ...options };

    try {
        const response = await fetch(url, mergedOptions);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    } catch (error) {
        console.error('API request failed:', error);
        throw error;
    }
}

/**
 * Local storage helpers
 */
const storage = {
    set: function(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
            console.error('LocalStorage set error:', e);
        }
    },

    get: function(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (e) {
            console.error('LocalStorage get error:', e);
            return defaultValue;
        }
    },

    remove: function(key) {
        try {
            localStorage.removeItem(key);
        } catch (e) {
            console.error('LocalStorage remove error:', e);
        }
    }
};

/**
 * Initialize theme based on user preference
 */
function initTheme() {
    const savedTheme = storage.get('theme', 'dark');
    if (savedTheme === 'light' || (savedTheme === 'system' && window.matchMedia('(prefers-color-scheme: light)').matches)) {
        document.documentElement.setAttribute('data-theme', 'light');
    }
}

/**
 * Toggle theme
 */
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

    document.documentElement.setAttribute('data-theme', newTheme);
    storage.set('theme', newTheme);
}

// Initialize theme on load
initTheme();

// Export global functions
window.DigitalHub = {
    openModal,
    closeModal,
    showAlert,
    copyToClipboard,
    apiRequest,
    storage,
    toggleTheme,
    debounce,
    throttle
};