/* ============================================================
   AURORA — main.js  (Stage 8: Auto-refresh + Multi-user)
   Global helpers, auto-refresh engine, session utils
   ============================================================ */

   const Aurora = {
    formatCurrency(val) {
        const n = parseFloat(val) || 0;
        if (n >= 100000) return '₹' + (n / 100000).toFixed(1) + 'L';
        if (n >= 1000)   return '₹' + (n / 1000).toFixed(1)   + 'K';
        return '₹' + n.toFixed(0);
    },
    formatCurrencyFull(val) { return '₹' + (parseFloat(val)||0).toLocaleString('en-IN'); },
    formatNumber(val) { return (parseInt(val)||0).toLocaleString('en-IN'); },
    fetch(url) {
        return fetch(url, { credentials: 'same-origin' })
            .then(r => r.json())
            .catch(() => ({ status: 'error', message: 'Network error' }));
    },
    animateCounter(el, target, prefix='', suffix='', duration=800) {
        if (!el) return;
        const startTs = performance.now();
        const step = (ts) => {
            const ease = 1 - Math.pow(1 - Math.min((ts - startTs)/duration, 1), 3);
            el.textContent = prefix + Math.floor(target * ease).toLocaleString('en-IN') + suffix;
            if (ease < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    },
    timeAgo(dateStr) {
        const diff = Math.floor((new Date() - new Date(dateStr)) / 1000);
        if (diff < 60)    return 'just now';
        if (diff < 3600)  return Math.floor(diff/60) + 'm ago';
        if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
        return Math.floor(diff/86400) + 'd ago';
    },
    showAlert(msg, type='info') {
        let t = document.getElementById('aurora-toast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'aurora-toast';
            t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-md);padding:14px 20px;font-size:13px;box-shadow:0 8px 32px rgba(0,0,0,0.4);z-index:9999;transform:translateY(80px);opacity:0;transition:all 0.3s ease;max-width:320px';
            document.body.appendChild(t);
        }
        t.textContent = msg;
        t.style.borderColor = type==='success'?'var(--green)':type==='error'?'var(--red)':'var(--blue)';
        t.style.transform='translateY(0)'; t.style.opacity='1';
        setTimeout(()=>{ t.style.transform='translateY(80px)'; t.style.opacity='0'; }, 3000);
    }
};

// ── AUTO-REFRESH ENGINE ───────────────────────────────────────
const AutoRefresh = {
    interval: 30000,
    timer: null,
    callbacks: [],
    register(fn) { this.callbacks.push(fn); },
    start() {
        if (this.timer) return;
        this.timer = setInterval(() => {
            this.callbacks.forEach(fn => { try { fn(); } catch(e){} });
            this.updateBadge();
        }, this.interval);
        this.showBadge();
    },
    showBadge() {
        const nav = document.querySelector('.nav-actions');
        if (!nav || document.getElementById('live-badge')) return;
        const badge = document.createElement('div');
        badge.id = 'live-badge';
        badge.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:20px;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);font-size:11px;color:#10b981;font-weight:500';
        badge.innerHTML = '<div style="width:7px;height:7px;border-radius:50%;background:#10b981;animation:aurora-pulse 2s infinite"></div><span id="live-label">Live</span>';
        nav.insertBefore(badge, nav.firstChild);
        if (!document.getElementById('aurora-pulse-style')) {
            const s = document.createElement('style');
            s.id = 'aurora-pulse-style';
            s.textContent = '@keyframes aurora-pulse{0%,100%{opacity:1}50%{opacity:0.3}}';
            document.head.appendChild(s);
        }
    },
    updateBadge() {
        const l = document.getElementById('live-label');
        if (l) { l.textContent='Updated'; setTimeout(()=>{ if(l)l.textContent='Live'; },2000); }
    }
};

function startClock() {
    const el = document.querySelector('.live-clock');
    if (!el) return;
    const u = () => { el.textContent = new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'}); };
    u(); setInterval(u, 1000);
}

window.addEventListener('scroll', () => {
    const nav = document.getElementById('navbar');
    if (nav) nav.classList.toggle('scrolled', window.scrollY > 20);
});

document.addEventListener('DOMContentLoaded', () => {
    startClock();
    AutoRefresh.start();
});