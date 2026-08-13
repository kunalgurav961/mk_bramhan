/* ===================================================
   MK BRAHMAN — app.js  v2.0
   Smart search, profile navigation, shortlist,
   sidebar, toast, debounce, accessibility
   =================================================== */

'use strict';

// ---------- PROFILE NAVIGATION ----------
/**
 * Navigate to the profile detail page.
 * @param {number} id       Profile database ID
 * @param {string} [from]   The page we came from (for back button)
 */
function openProfile(id, from) {
    if (!id) return;
    const currentPage = from || window.location.pathname.split('/').pop() || 'index.php';
    window.location.href = `profile.php?id=${id}&from=${encodeURIComponent(currentPage)}`;
}

// ---------- SIDEBAR ----------
function openSidebar() {
    document.getElementById('sidebar')?.classList.add('open');
    document.getElementById('overlay')?.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    document.getElementById('sidebar')?.classList.remove('open');
    document.getElementById('overlay')?.classList.remove('active');
    document.body.style.overflow = '';
}

// ---------- MORE MENU ----------
function toggleMoreMenu() {
    const menu = document.getElementById('moreMenu');
    if (menu) menu.classList.toggle('open');
}

document.addEventListener('click', function (e) {
    const menu    = document.getElementById('moreMenu');
    const moreBtn = document.getElementById('moreBtn');
    if (menu && moreBtn && !moreBtn.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.remove('open');
    }
});

// ---------- TOAST ----------
function showToast(msg, duration = 2200) {
    let toast = document.getElementById('toast');
    if (!toast) {
        toast           = document.createElement('div');
        toast.id        = 'toast';
        toast.className = 'toast';
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.classList.add('show');
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => toast.classList.remove('show'), duration);
}

// ---------- DEBOUNCE ----------
function debounce(fn, delay) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

// ---------- FILTER STATE HELPERS ----------
/**
 * Read all currently active filter values and return a URLSearchParams string.
 */
function getFilterParams() {
    const params = new URLSearchParams();

    // Gender chip
    const activeChip = document.querySelector('.chip[data-filter="gender"].active');
    if (activeChip && activeChip.dataset.value !== '') {
        params.set('gender', activeChip.dataset.value);
    }

    // City select
    const cityEl = document.getElementById('cityFilter');
    if (cityEl && cityEl.value) params.set('city', cityEl.value);

    // Birth year select
    const yearEl = document.getElementById('yearFilter');
    if (yearEl && yearEl.value) params.set('birth_year', yearEl.value);

    // Sort select
    const sortEl = document.getElementById('sortFilter');
    if (sortEl && sortEl.value) params.set('sort', sortEl.value);

    return params;
}

// ---------- REAL-TIME SEARCH ----------
(function () {
    const searchInput      = document.getElementById('searchInput');
    const resultsContainer = document.getElementById('results');
    const spinner          = document.getElementById('spinner');
    const sectionTag       = document.getElementById('sectionTag');

    if (!searchInput || !resultsContainer) return;

    const searchUrl    = searchInput.dataset.url      || 'search.php';
    const isShortlisted= searchInput.dataset.shortlisted === '1';

    // Search hint — show smart search tip for numeric input
    searchInput.addEventListener('keyup', function () {
        const val = this.value.trim();
        if (/^\d{7,}$/.test(val)) {
            this.title = '📱 मोबाईल नंबर शोधत आहे...';
        } else if (/^\d{1,3}$/.test(val)) {
            this.title = '💡 Smart: 3 अंक = लिंग+वर्ष (उदा. 195 = मुलगा+1995)';
        } else {
            this.title = '';
        }
    });

    const doSearch = debounce(async function (q) {
        if (spinner) spinner.style.display = 'block';

        try {
            const filterParams = getFilterParams();
            let url = `${searchUrl}?search=${encodeURIComponent(q)}`;
            if (isShortlisted) url += '&shortlisted=1';
            // Append filter params
            filterParams.forEach((v, k) => { url += `&${k}=${encodeURIComponent(v)}`; });

            const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const html = await res.text();

            resultsContainer.innerHTML = html;

            // Re-attach shortlist handlers & row click handlers
            attachShortlistHandlers(resultsContainer);
            attachRowKeyboard(resultsContainer);

            // Update count
            const rows = resultsContainer.querySelectorAll('tr.data-row');
            if (sectionTag) {
                const countEl = sectionTag.querySelector('.count');
                if (countEl) countEl.textContent = rows.length;
            }
        } catch (err) {
            console.error('Search error:', err);
        } finally {
            if (spinner) spinner.style.display = 'none';
        }
    }, 300);

    // 'input' fires on typing/paste; 'search' fires on Enter or clearing type=search
    searchInput.addEventListener('input',  function () { doSearch(this.value.trim()); });
    searchInput.addEventListener('search', function () { doSearch(this.value.trim()); });
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            doSearch(this.value.trim());
        }
    });

    // Expose doSearch globally so filter bar can trigger it
    window._triggerSearch = () => doSearch(searchInput ? searchInput.value.trim() : '');
})();

// ---------- ROW KEYBOARD ACCESSIBILITY ----------
/**
 * Allow Enter key to open profile for keyboard users on profile rows.
 */
function attachRowKeyboard(container) {
    container = container || document;
    container.querySelectorAll('tr.profile-row').forEach(row => {
        if (row.dataset.kbAttached) return;
        row.dataset.kbAttached = '1';

        // Extract id from onclick attribute as fallback
        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const id = this.dataset.id;
                if (id) openProfile(id);
            }
        });
    });
}

// ---------- SHORTLIST TOGGLE ----------
function attachShortlistHandlers(container) {
    container = container || document;
    const btns = container.querySelectorAll('.shortlist-btn');

    btns.forEach(btn => {
        if (btn.dataset.attached) return;
        btn.dataset.attached = '1';

        btn.addEventListener('click', async function (e) {
            e.stopPropagation(); // Prevent row click from firing
            const id = this.dataset.id;
            if (!id) return;

            this.classList.add('loading');

            try {
                const fd = new FormData();
                fd.append('id', id);

                const res  = await fetch('toggle-shortlist.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.shortlisted === 1) {
                    this.textContent = '❤️';
                    this.title       = 'शॉर्टलिस्टमधून काढा';
                    showToast('❤️ शॉर्टलिस्टमध्ये जोडले');
                } else {
                    this.textContent = '🤍';
                    this.title       = 'शॉर्टलिस्टला जोडा';
                    showToast('🤍 शॉर्टलिस्टमधून काढले');

                    // On shortlisted page — fade out the row
                    if (document.body.dataset.page === 'shortlisted') {
                        const row = this.closest('tr');
                        if (row) {
                            row.style.transition = 'opacity .3s';
                            row.style.opacity    = '0';
                            setTimeout(() => row.remove(), 300);
                        }
                    }
                }

                updateShortlistBadge(data.total_shortlisted);

            } catch (err) {
                console.error('Shortlist error:', err);
                showToast('⚠️ Error. कृपया पुन्हा प्रयत्न करा.');
            } finally {
                this.classList.remove('loading');
            }
        });
    });
}

/**
 * Update sidebar shortlist badge count.
 */
function updateShortlistBadge(count) {
    const badge = document.querySelector('.side-link[href="shortlisted.php"] .badge');
    if (badge !== null && count !== undefined) {
        badge.textContent = count;
        badge.style.display = count > 0 ? '' : 'none';
    }
}

async function fetchShortlistCount() {
    try {
        const res  = await fetch('toggle-shortlist.php?count=1');
        const data = await res.json();
        updateShortlistBadge(data.total_shortlisted);
    } catch (_) { /* silent */ }
}

// ---------- INIT ----------
document.addEventListener('DOMContentLoaded', function () {
    // Menu button
    const menuBtn = document.getElementById('menuBtn');
    if (menuBtn) menuBtn.addEventListener('click', openSidebar);

    // More button
    const moreBtn = document.getElementById('moreBtn');
    if (moreBtn) moreBtn.addEventListener('click', toggleMoreMenu);

    // Attach shortlist handlers on initial load
    attachShortlistHandlers(document);

    // Attach keyboard nav to table rows
    attachRowKeyboard(document);

    // Keyboard: Escape closes sidebar / menu
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeSidebar();
            document.getElementById('moreMenu')?.classList.remove('open');
        }
    });

    // ── FILTER BAR ──────────────────────────────────────────────────────────
    // Gender chips
    document.querySelectorAll('.chip[data-filter="gender"]').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.chip[data-filter="gender"]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            if (window._triggerSearch) window._triggerSearch();
        });
    });

    // City, birth year, sort selects
    ['cityFilter', 'yearFilter', 'sortFilter'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', () => {
            if (window._triggerSearch) window._triggerSearch();
        });
    });
});

// ---------- MOBILE NUMBER SEARCH (dedicated field) ----------
(function () {
    const mobileInput = document.getElementById('mobileSearchInput');
    const mainInput   = document.getElementById('searchInput');
    const resultsEl   = document.getElementById('results');
    const spinner     = document.getElementById('spinner');
    const sectionTag  = document.getElementById('sectionTag');

    if (!mobileInput || !resultsEl) return;

    const searchUrl = mobileInput.dataset.url || 'search.php';

    async function runMobileSearch(q) {
        if (!q) {
            // Empty — restore full list by triggering main search with blank value
            if (mainInput) mainInput.dispatchEvent(new Event('input'));
            return;
        }
        if (spinner) spinner.style.display = 'block';
        try {
            const filterParams = getFilterParams();
            let url = `${searchUrl}?search=${encodeURIComponent(q)}`;
            filterParams.forEach((v, k) => { url += `&${k}=${encodeURIComponent(v)}`; });
            const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const html = await res.text();
            resultsEl.innerHTML = html;
            attachShortlistHandlers(resultsEl);
            attachRowKeyboard(resultsEl);
            const rows = resultsEl.querySelectorAll('tr.data-row');
            if (sectionTag) {
                const countEl = sectionTag.querySelector('.count');
                if (countEl) countEl.textContent = rows.length;
            }
        } catch (err) {
            console.error('Mobile search error:', err);
        } finally {
            if (spinner) spinner.style.display = 'none';
        }
    }

    const debouncedMobile = debounce(runMobileSearch, 300);

    mobileInput.addEventListener('input', function () {
        // Strip non-numeric characters automatically
        const digits = this.value.replace(/\D/g, '');
        if (this.value !== digits) this.value = digits;

        // Clear general search to avoid conflict
        if (mainInput && digits) mainInput.value = '';

        debouncedMobile(digits);
    });

    mobileInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            runMobileSearch(this.value.replace(/\D/g, ''));
        }
    });

    // When general search is typed, clear mobile field
    if (mainInput) {
        mainInput.addEventListener('input', function () {
            if (this.value.trim()) mobileInput.value = '';
        });
    }
})();
