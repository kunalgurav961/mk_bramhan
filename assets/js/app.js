/* ===================================================
   MK BRAHMAN — app.js  v3.1
   Smart search, gender filter, sort (asc/desc),
   profile navigation, shortlist, sidebar, toast,
   debounce, accessibility
   =================================================== */

'use strict';

// ---------- PROFILE NAVIGATION ----------
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
    container.querySelectorAll('.shortlist-btn').forEach(btn => {
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

// ==========================================================
//  UNIFIED SEARCH + FILTER ENGINE
//  Initialised inside DOMContentLoaded so all elements exist
// ==========================================================
document.addEventListener('DOMContentLoaded', function () {

    // ── Global inits ────────────────────────────────────────
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

    // ── Search / Filter setup ────────────────────────────────
    const searchInput = document.getElementById('searchInput');
    const mobileInput = document.getElementById('mobileSearchInput');
    const resultsEl   = document.getElementById('results');
    const spinner     = document.getElementById('spinner');
    const sectionTag  = document.getElementById('sectionTag');
    const genderChips = document.querySelectorAll('.filter-chip[data-gender]');
    const sortByEl    = document.getElementById('sortBy');
    const sortDirBtn  = document.getElementById('sortDirBtn');
    const sortIcon    = sortDirBtn ? sortDirBtn.querySelector('.sort-icon') : null;

    if (!resultsEl) return;   // Not a listing page — stop here

    const searchUrl     = searchInput?.dataset.url || 'search.php';
    const isShortlisted = searchInput?.dataset.shortlisted === '1';

    // State
    let activeGender = '';
    let sortBy       = sortByEl ? sortByEl.value : 'id';
    let sortDir      = 'DESC';

    // Build the search URL from all current filter state
    function buildUrl(query) {
        const p = new URLSearchParams();
        if (query)              p.set('search',    query);
        if (activeGender !== '') p.set('gender',   activeGender);
        p.set('sort_by',  sortBy);
        p.set('sort_dir', sortDir);
        if (isShortlisted)      p.set('shortlisted', '1');
        return searchUrl + '?' + p.toString();
    }

    // Execute the search and replace tbody
    async function runSearch(query) {
        if (spinner) { spinner.style.display = 'block'; }
        try {
            const url  = buildUrl(query || '');
            const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const html = await resp.text();
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
            showToast('⚠️ शोध अयशस्वी. पुन्हा प्रयत्न करा.');
        } finally {
            if (spinner) { spinner.style.display = 'none'; }
        }
    }

    function currentQuery() {
        if (mobileInput && mobileInput.value.trim()) return mobileInput.value.trim();
        return searchInput ? searchInput.value.trim() : '';
    }

    const debouncedSearch = debounce(() => runSearch(currentQuery()), 280);

    // ── General search input ────────────────────────────────
    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            const v = this.value.trim();
            this.title = /^\d{7,}$/.test(v) ? '📱 मोबाईल नंबर शोधत आहे...'
                       : /^\d{1,3}$/.test(v) ? '💡 Smart: 195 = मुलगा+1995'
                       : '';
        });
        searchInput.addEventListener('input', function () {
            if (mobileInput && this.value.trim()) mobileInput.value = '';
            debouncedSearch();
        });
        searchInput.addEventListener('search', debouncedSearch);
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); runSearch(this.value.trim()); }
        });
    }

    // ── Mobile number search ────────────────────────────────
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

    // ── Gender chip filter ──────────────────────────────────
    genderChips.forEach(chip => {
        chip.addEventListener('click', function () {
            activeGender = this.dataset.gender;          // '' | '0' | '1'
            genderChips.forEach(c => {
                const active = (c === this);
                c.classList.toggle('active', active);
                c.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            runSearch(currentQuery());                    // immediate — no debounce
        });
    });

    // ── Sort column selector ────────────────────────────────
    if (sortByEl) {
        sortByEl.addEventListener('change', function () {
            sortBy = this.value;
            runSearch(currentQuery());
        });
    }

    // ── Sort direction toggle ───────────────────────────────
    if (sortDirBtn) {
        sortDirBtn.addEventListener('click', function () {
            sortDir = (sortDir === 'DESC') ? 'ASC' : 'DESC';
            this.dataset.dir = sortDir;
            if (sortIcon) sortIcon.textContent = (sortDir === 'ASC') ? '↑' : '↓';
            this.title = (sortDir === 'ASC') ? 'Ascending (A→Z)' : 'Descending (Z→A)';
            runSearch(currentQuery());
        });
    }

});
