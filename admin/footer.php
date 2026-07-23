        </main>
        <!-- Footer -->
        <footer class="bg-white dark:bg-slate-800 border-t border-gray-200 dark:border-slate-700 px-6 py-3 flex-shrink-0 flex items-center justify-between text-xs text-gray-400 dark:text-slate-500">
            <span>&copy; <?= date('Y') ?> Autonomous Feedback Management System &mdash; <?= h(get_setting('institution_name', 'Autonomous College')) ?></span>
            <span class="hidden sm:block">Admin Portal v2.0</span>
        </footer>
    </div><!-- /.main wrapper -->

    <!-- ── Global JS ──────────────────────────────────────────────── -->
    <script>
    // ─── Dark Mode ─────────────────────────────────────────────────────────────
    (function () {
        const html    = document.documentElement;
        const sunIcon  = document.getElementById('icon-sun');
        const moonIcon = document.getElementById('icon-moon');
        const btn     = document.getElementById('dark-toggle');

        function applyTheme(dark) {
            if (dark) {
                html.classList.add('dark');
                sunIcon && sunIcon.classList.remove('hidden');
                moonIcon && moonIcon.classList.add('hidden');
            } else {
                html.classList.remove('dark');
                sunIcon && sunIcon.classList.add('hidden');
                moonIcon && moonIcon.classList.remove('hidden');
            }
        }

        const saved = localStorage.getItem('afms-theme');
        applyTheme(saved === 'dark');

        btn && btn.addEventListener('click', () => {
            const isDark = html.classList.contains('dark');
            applyTheme(!isDark);
            localStorage.setItem('afms-theme', isDark ? 'light' : 'dark');
        });
    })();

    // ─── Sidebar Toggle ────────────────────────────────────────────────────────
    (function () {
        const sidebar = document.getElementById('admin-sidebar');
        const btn     = document.getElementById('sidebar-toggle');
        if (!sidebar || !btn) return;

        function isMobile() { return window.innerWidth < 768; }

        btn.addEventListener('click', () => {
            if (isMobile()) {
                sidebar.classList.toggle('-translate-x-full');
            } else {
                const collapsed = sidebar.classList.toggle('w-16');
                sidebar.querySelectorAll('span:not(.sr-only), p').forEach(el => {
                    el.classList.toggle('hidden');
                });
            }
        });

        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', (e) => {
            if (isMobile() && !sidebar.contains(e.target) && !btn.contains(e.target)) {
                sidebar.classList.add('-translate-x-full');
            }
        });
    })();

    // ─── Toast Notification System ────────────────────────────────────────────
    window.showToast = function(message, type = 'success', duration = 3500) {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const colours = {
            success: 'bg-emerald-600 text-white',
            error:   'bg-red-600 text-white',
            warning: 'bg-yellow-500 text-white',
            info:    'bg-blue-600 text-white',
        };
        const icons = {
            success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
            error:   '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>',
            warning: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>',
            info:    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        };

        const toast = document.createElement('div');
        toast.className = `toast flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg max-w-sm text-sm font-medium ${colours[type] || colours.info}`;
        toast.innerHTML = `
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">${icons[type] || icons.info}</svg>
            <span>${message}</span>
            <button onclick="this.parentElement.remove()" class="ml-auto opacity-75 hover:opacity-100">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>`;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), duration);
    };

    // ─── Confirm Dialog ───────────────────────────────────────────────────────
    window.confirmAction = function(message, callback) {
        if (confirm(message)) callback();
    };

    // ─── AJAX Helper ──────────────────────────────────────────────────────────
    window.afmsPost = function(url, data, onSuccess, onError) {
        const fd = new FormData();
        for (const [k, v] of Object.entries(data)) fd.append(k, v);

        fetch(url, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    if (onSuccess) onSuccess(res);
                } else {
                    showToast(res.message || 'An error occurred.', 'error');
                    if (onError) onError(res);
                }
            })
            .catch(() => {
                showToast('Network error. Please try again.', 'error');
                if (onError) onError({});
            });
    };

    // ─── Auto-dismiss alerts ──────────────────────────────────────────────────
    document.querySelectorAll('[data-auto-dismiss]').forEach(el => {
        const ms = parseInt(el.dataset.autoDismiss || '4000', 10);
        setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity 0.4s'; setTimeout(() => el.remove(), 400); }, ms);
    });
    </script>
</body>
</html>
