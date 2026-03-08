/* ============================================================
   AURORA — main.js
   Global helpers, session, navbar dynamic data
   ============================================================ */

// ---- FORMAT HELPERS ----
const Aurora = {

    // Format currency in Indian style
    formatCurrency(amount) {
        const num = parseFloat(amount);
        if (num >= 100000) {
            return '₹' + (num / 100000).toFixed(1) + 'L';
        } else if (num >= 1000) {
            return '₹' + (num / 1000).toFixed(1) + 'k';
        }
        return '₹' + num.toLocaleString('en-IN');
    },

    // Format full currency
    formatCurrencyFull(amount) {
        return '₹' + parseFloat(amount).toLocaleString('en-IN', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    },

    // Format number
    formatNumber(num) {
        return parseInt(num).toLocaleString('en-IN');
    },

    // Show loading spinner in element
    showLoading(selector) {
        const el = document.querySelector(selector);
        if (el) el.innerHTML = '<div class="spinner" style="margin:0 auto"></div>';
    },

    // Show error in element
    showError(selector, msg) {
        const el = document.querySelector(selector);
        if (el) el.innerHTML = `<div class="alert alert-error show" style="margin:0">
            <span class="alert-icon">⚠️</span>${msg}
        </div>`;
    },

    // AJAX GET helper — returns Promise
    fetch(url) {
        return fetch(url, { credentials: 'same-origin' })
            .then(res => {
                if (!res.ok) throw new Error('Network error');
                return res.json();
            })
            .catch(err => {
                console.error('Aurora fetch error:', err);
                return { status: 'error', message: err.message };
            });
    },

    // Show alert message
    showAlert(type, message, containerId = 'alertContainer') {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = `
            <div class="alert alert-${type} show">
                <span class="alert-icon">${type === 'error' ? '⚠️' : '✅'}</span>
                ${message}
            </div>`;
        setTimeout(() => { container.innerHTML = ''; }, 5000);
    },

    // Animate counter from 0 to value
    animateCounter(el, target, prefix = '', suffix = '', duration = 1200) {
        if (!el) return;
        const start     = 0;
        const increment = target / (duration / 16);
        let   current   = start;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            el.textContent = prefix + Aurora.formatNumber(Math.floor(current)) + suffix;
        }, 16);
    },

    // Time ago helper
    timeAgo(dateStr) {
        const now  = new Date();
        const date = new Date(dateStr);
        const diff = Math.floor((now - date) / 1000);
        if (diff < 60)   return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
        return Math.floor(diff / 86400) + ' days ago';
    }
};

// ---- NAVBAR DYNAMIC DATA ----
document.addEventListener('DOMContentLoaded', () => {

    // Live clock
    const timeEl = document.getElementById('currentTime');
    const dateEl = document.getElementById('currentDate');

    function updateClock() {
        const now = new Date();
        if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-IN', {
            hour: '2-digit', minute: '2-digit'
        });
        if (dateEl) dateEl.textContent = now.toLocaleDateString('en-IN', {
            weekday: 'long', day: 'numeric', month: 'long'
        });
    }
    updateClock();
    setInterval(updateClock, 1000);

    // Navbar scroll effect
    const navbar = document.getElementById('navbar') || document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            navbar.classList.toggle('scrolled', window.scrollY > 10);
        });
    }

    // Sidebar active state
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.sidebar-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href && href === currentPage) {
            document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
        }
    });

    // Mobile sidebar toggle
    const menuBtn = document.getElementById('menuBtn');
    const sidebar = document.querySelector('.sidebar');
    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    // Load user data into navbar
    Aurora.fetch('php/get_dashboard_stats.php').then(data => {
        if (data.status === 'success') {
            // Update user name in sidebar
            const nameEls = document.querySelectorAll('.user-name');
            nameEls.forEach(el => el.textContent = data.user_name);

            // Update welcome message
            const welcomeEl = document.querySelector('.welcome-text h2');
            if (welcomeEl) {
                const hour = new Date().getHours();
                const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
                const firstName = data.user_name.split(' ')[0];
                welcomeEl.textContent = `${greeting}, ${firstName} 👋`;
            }
        }
    });
});