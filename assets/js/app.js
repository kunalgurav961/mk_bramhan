/* ===================================================
   MK BRAHMAN — app.js  v3.0
   Smart search, gender filter, sort (asc/desc),
   profile navigation, shortlist, sidebar, toast,
   debounce, accessibility
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

// ==========================================================
//  UNIFIED SEARCH + FILTER ENGINE
//  Reads from: searchInput, mobileSearchInput, gender chips,
//              sortBy select, sortDirBtn
// ==========================================================
(function () {
    const searchInput  = document.getElementById('searchInput');
    const mobileInput  = document.getElementById('mobileSearchInput');
    const resultsEl    = document.getElementById('results');
    const spinner      = document.getElementById('spinner');
    const sectionTag   = document.getElementById('sectionTag');

    // Filter/sort controls (may not exist on all pages)
    const genderChips  = document.querySelectorAll('.filter-chip[data-gender]');
    const sortByEl     = document.getElementById('sortBy');
    const sortDirBtn   = document.getElementById('sortDirBtn');

    if (!resultsEl) return;

    const searchUrl     = searchInput?.dataset.url || 'search.php';
    const isShortlisted = searchInput?.dataset.shortlisted === '1';

    // ── State ──────────────────────────────────────────────────────
    let activeGender = '';   // '' | '0' | '1'
    let sortBy       = 'id';
    let sortDir      = 'DESC';

    // ── Helpers ────────────────────────────────────────────────────
    function buildUrl(q) {
        const params = new URLSearchParams();
        if (q)          params.set('search',    q);
        if (activeGender !== '') params.set('gender',  activeGender);
        params.set('sort_by',  sortBy);
        params.set('sort_dir', sortDir);
        if (isShortlisted) params.set('shortlisted', '1');
        return `${searchUrl}?${params.toString()}`;
    }

    async function runSearch(q) {
        if (spinner) spinner.style.display = 'block';
        try {
            const url  = buildUrl(q);
            const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const html = await res.text();

            resultsEl.innerHTML = html;

            attachShortlistHandlers(resultsEl);
            attachRowKeyboard(resultsEl);

            // Update displayed count
            const rows = resultsEl.querySelectorAll('tr.data-row');
            if (sectionTag) {
                const countEl = sectionTag.querySelector('.count');
                if (countEl) countEl.textContent = rows.length;
            }
        } catch (err) {
            console.error('Search error:', err);
        } finally {
            if (spinner) spinner.style.display = 'none';
        }
    }

    function getCurrentQuery() {
        // Mobile field takes priority if filled
        if (mobileInput && mobileInput.value.trim()) return mobileInput.value.trim();
        return searchInput ? searchInput.value.trim() : '';
    }

    const debouncedSearch = debounce(function () {
        runSearch(getCurrentQuery());
    }, 280);

    // ── Search Input ───────────────────────────────────────────────
    if (searchInput) {
        // Smart hint
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

        searchInput.addEventListener('input',  function () {
            if (mobileInput && this.value.trim()) mobileInput.value = '';
            debouncedSearch();
        });
        searchInput.addEventListener('search', debouncedSearch);
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); runSearch(this.value.trim()); }
        });
    }

    // ── Mobile Number Search ───────────────────────────────────────
    if (mobileInput) {
        mobileInput.addEventListener('input', function () {
            const digits = this.value.replace(/\D/g, '');
            if (this.value !== digits) this.value = digits;
            if (searchInput && digits) searchInput.value = '';
            debouncedSearch();
        });
        mobileInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); runSearch(this.value.replace(/\D/g, '')); }
        });
    }

    // ── Gender Chip Filter ─────────────────────────────────────────
    genderChips.forEach(chip => {
        chip.addEventListener('click', function () {
            activeGender = this.dataset.gender;

            // Update chip styles & aria-pressed
            genderChips.forEach(c => {
                const isActive = c === this;
                c.classList.toggle('active', isActive);
                c.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            debouncedSearch();
        });
    });

    // ── Sort Column ────────────────────────────────────────────────
    if (sortByEl) {
        sortByEl.addEventListener('change', function () {
            sortBy = this.value;
            debouncedSearch();
        });
    }

    // ── Sort Direction Toggle ──────────────────────────────────────
    if (sortDirBtn) {
        sortDirBtn.addEventListener('click', function () {
            sortDir = sortDir === 'DESC' ? 'ASC' : 'DESC';
            this.dataset.dir = sortDir;
            this.querySelector('.sort-icon').textContent = sortDir === 'ASC' ? '↑' : '↓';
            this.setAttribute('aria-label', sortDir === 'ASC' ? 'Ascending order' : 'Descending order');
            debouncedSearch();
        });
    }

})();

// ---------- ROW KEYBOARD ACCESSIBILITY ----------
function attachRowKeyboard(container) {
    container = container || document;
    container.querySelectorAll('tr.profile-row').forEach(row => {
        if (row.dataset.kbAttached) return;
        row.dataset.kbAttached = '1';
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
            e.stopPropagation();
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
    const menuBtn = document.getElementById('menuBtn');
    if (menuBtn) menuBtn.addEventListener('click', openSidebar);

    const moreBtn = document.getElementById('moreBtn');
    if (moreBtn) moreBtn.addEventListener('click', toggleMoreMenu);

    attachShortlistHandlers(document);
    attachRowKeyboard(document);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeSidebar();
            document.getElementById('moreMenu')?.classList.remove('open');
        }
    });
});
