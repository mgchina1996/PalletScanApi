const app = document.querySelector('#report-app');

const icons = {
    logo: '<svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2"><path d="m4 7 8-4 8 4-8 4-8-4Z"/><path d="m4 7 8 4 8-4v10l-8 4-8-4V7Z"/><path d="M12 11v10"/></svg>',
    overview: '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
    products: '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="m4 7 8-4 8 4-8 4-8-4Z"/><path d="m4 7 8 4 8-4v10l-8 4-8-4V7Z"/><path d="M12 11v10"/></svg>',
    entries: '<svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h7M9 17h7M9 9h2"/></svg>',
    stocked: '<svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v5c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 10v5c0 1.7 3.6 3 8 3s8-1.3 8-3v-5M4 15v4c0 1.7 3.6 3 8 3s8-1.3 8-3v-4"/></svg>',
    location: '<svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>',
    warning: '<svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.3 3.7 2.5 18a2 2 0 0 0 1.8 3h15.4a2 2 0 0 0 1.8-3L13.7 3.7a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>',
    calendar: '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>',
    down: '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>',
    search: '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>',
    left: '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>',
    right: '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>',
};

const state = {
    activePage: 'overview',
    dateFrom: losAngelesToday(),
    dateTo: losAngelesToday(),
    allDates: false,
    pickerOpen: false,
    draftFrom: losAngelesToday(),
    draftTo: losAngelesToday(),
    draftAllDates: false,
    calendarMonth: '',
    selectingRange: false,
    summary: { entered: 0, locations: 0, stocked: 0, notStocked: 0 },
    locations: [],
    products: [],
    productLocations: [],
    productMeta: { currentPage: 1, lastPage: 1, total: 0, from: null, to: null },
    productFilters: { location: '', search: '', page: 1, perPage: 25 },
    loading: true,
    error: '',
};

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function losAngelesToday() {
    if (app.dataset.today) return app.dataset.today;

    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'America/Los_Angeles',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const values = Object.fromEntries(parts.map((part) => [part.type, part.value]));

    return `${values.year}-${values.month}-${values.day}`;
}

function shiftDate(value, days) {
    const date = new Date(`${value}T12:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);

    return date.toISOString().slice(0, 10);
}

function presetRange(preset) {
    const today = losAngelesToday();

    if (preset === 'today') return { from: today, to: today };
    if (preset === 'yesterday') {
        const yesterday = shiftDate(today, -1);
        return { from: yesterday, to: yesterday };
    }
    if (preset === 'last3') return { from: shiftDate(today, -2), to: today };
    if (preset === 'last7') return { from: shiftDate(today, -6), to: today };
    if (preset === 'last30') return { from: shiftDate(today, -29), to: today };

    return { from: today, to: today };
}

function shiftMonth(value, months) {
    const date = new Date(`${value.slice(0, 7)}-15T12:00:00Z`);
    date.setUTCMonth(date.getUTCMonth() + months, 1);

    return date.toISOString().slice(0, 7) + '-01';
}

function renderCalendar(month, showPrevious, showNext) {
    const monthDate = new Date(`${month}T12:00:00Z`);
    const year = monthDate.getUTCFullYear();
    const monthIndex = monthDate.getUTCMonth();
    const firstWeekday = new Date(Date.UTC(year, monthIndex, 1)).getUTCDay();
    const daysInMonth = new Date(Date.UTC(year, monthIndex + 1, 0)).getUTCDate();
    const label = new Intl.DateTimeFormat('en-US', { month: 'short', year: 'numeric', timeZone: 'UTC' }).format(monthDate);
    const blanks = Array.from({ length: firstWeekday }, () => '<span></span>').join('');
    const days = Array.from({ length: daysInMonth }, (_, index) => {
        const day = index + 1;
        const date = `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const inRange = !state.draftAllDates && date >= state.draftFrom && date <= state.draftTo;
        const endpoint = !state.draftAllDates && (date === state.draftFrom || date === state.draftTo);
        const classes = endpoint
            ? 'bg-blue-600 font-bold text-white'
            : inRange
                ? 'bg-blue-50 font-semibold text-blue-700'
                : 'text-slate-600 hover:bg-slate-100';

        return `<button type="button" data-calendar-date="${date}" class="grid h-9 place-items-center rounded-md text-xs ${classes}">${day}</button>`;
    }).join('');

    return `<div class="min-w-0 p-4"><div class="mb-4 grid grid-cols-[36px_1fr_36px] items-center"><span>${showPrevious ? `<button type="button" data-month-action="previous" class="grid h-9 w-9 place-items-center rounded-lg text-slate-500 hover:bg-slate-100">${icons.left}</button>` : ''}</span><strong class="text-center text-sm">${label}</strong><span>${showNext ? `<button type="button" data-month-action="next" class="grid h-9 w-9 place-items-center rounded-lg text-slate-500 hover:bg-slate-100">${icons.right}</button>` : ''}</span></div><div class="grid grid-cols-7 text-center text-[10px] font-semibold text-slate-400"><span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span></div><div class="mt-2 grid grid-cols-7 gap-y-1">${blanks}${days}</div></div>`;
}

function periodLabel() {
    if (state.allDates) return 'All dates';
    if (state.dateFrom === state.dateTo) return state.dateFrom;

    return `${state.dateFrom} – ${state.dateTo}`;
}

function isPresetActive(preset) {
    if (preset === 'all') return state.draftAllDates;
    const range = presetRange(preset);

    return !state.draftAllDates && state.draftFrom === range.from && state.draftTo === range.to;
}

async function api(path) {
    const response = await fetch(path, { headers: { Accept: 'application/json' } });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'Unable to load the overview.');

    return payload;
}

function metricCard(label, value, tone, icon) {
    const tones = {
        blue: 'bg-blue-50 text-blue-600 ring-blue-100',
        violet: 'bg-violet-50 text-violet-600 ring-violet-100',
        green: 'bg-emerald-50 text-emerald-600 ring-emerald-100',
        amber: 'bg-amber-50 text-amber-600 ring-amber-100',
    };

    return `<div class="flex min-h-32 items-center gap-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl ring-1 ${tones[tone]}">${icons[icon]}</span><span><strong class="block text-3xl font-black tracking-tight text-slate-950">${Number(value).toLocaleString()}</strong><span class="mt-1 block text-base font-medium text-slate-500">${escapeHtml(label)}</span></span></div>`;
}

function loadingBlock(label) {
    return `<div class="grid min-h-64 place-items-center text-slate-500"><div class="text-center"><span class="mx-auto block h-8 w-8 animate-spin rounded-full border-3 border-blue-100 border-t-blue-600"></span><span class="mt-3 block text-sm font-semibold">${escapeHtml(label)}</span></div></div>`;
}

function renderBars() {
    if (state.loading) return loadingBlock('Loading locations…');
    if (!state.locations.length) return '<div class="grid min-h-64 place-items-center text-sm text-slate-500">No entries for the selected date.</div>';

    const max = Math.max(...state.locations.map((location) => location.entered), 1);

    return `<div class="overflow-x-auto pb-2"><div class="flex h-80 min-w-max items-end gap-5 border-b border-slate-200 px-4 pt-8">${state.locations.slice(0, 12).map((location) => `
        <div class="flex h-full w-20 shrink-0 flex-col items-center justify-end">
            <strong class="mb-2 text-sm text-slate-700">${location.entered.toLocaleString()}</strong>
            <div class="w-12 rounded-t-lg bg-gradient-to-t from-blue-600 to-blue-400 shadow-sm" style="height:${Math.max((location.entered / max) * 220, 6)}px"></div>
            <strong class="mt-3 h-10 w-full break-words text-center text-xs leading-4 text-slate-600">${escapeHtml(location.locationCode)}</strong>
        </div>`).join('')}</div></div>`;
}

function renderLocationRows() {
    if (state.loading) return `<tr><td colspan="5">${loadingBlock('Loading summary…')}</td></tr>`;
    if (!state.locations.length) return '<tr><td colspan="5" class="px-6 py-16 text-center text-slate-500">No location records found.</td></tr>';

    return state.locations.map((location) => `
        <tr class="border-t border-slate-100 hover:bg-slate-50">
            <td class="px-5 py-4 font-bold">${escapeHtml(location.locationCode)}</td>
            <td class="px-5 py-4 text-center font-semibold">${location.entered.toLocaleString()}</td>
            <td class="px-5 py-4 text-center font-semibold text-emerald-700">${location.stocked.toLocaleString()}</td>
            <td class="px-5 py-4 text-center font-semibold ${location.notStocked > 0 ? 'text-amber-700' : 'text-slate-500'}">${location.notStocked.toLocaleString()}</td>
            <td class="whitespace-nowrap px-5 py-4 text-right text-sm text-slate-600">${escapeHtml(location.lastEntryAt || '—')}</td>
        </tr>`).join('');
}

function renderProductRows() {
    if (state.loading) return `<tr><td colspan="5">${loadingBlock('Loading products…')}</td></tr>`;
    if (!state.products.length) return '<tr><td colspan="5" class="px-6 py-20 text-center text-slate-500">No products found.</td></tr>';

    return state.products.map((product) => `
        <tr class="border-t border-slate-200 align-middle hover:bg-slate-50">
            <td class="px-5 py-5"><div class="flex min-w-0 gap-4"><div class="grid h-24 w-24 shrink-0 place-items-center overflow-hidden rounded-xl bg-white text-blue-600 ring-1 ring-slate-200">${product.imageUrl ? `<img src="${escapeHtml(product.imageUrl)}" alt="${escapeHtml(product.name || product.sku || product.tpin || 'Product')}" loading="lazy" class="h-full w-full object-contain p-1">` : icons.products}</div><div class="min-w-0 text-sm leading-6">${product.name ? `<strong class="block max-w-xl break-words text-base [overflow-wrap:anywhere]">${escapeHtml(product.name)}</strong>` : ''}<div><span class="font-semibold text-slate-500">Location:</span> ${escapeHtml(product.locationCode)}</div><div><span class="font-semibold text-slate-500">Item ID:</span> ${escapeHtml(product.itemId || '—')}</div><div><span class="font-semibold text-slate-500">Carton ID:</span> ${escapeHtml(product.cartonId || '—')}</div><div><span class="font-semibold text-slate-500">Carton Number:</span> ${escapeHtml(product.cartonNumber || '—')}</div><div><span class="font-semibold text-slate-500">TPIN:</span> ${escapeHtml(product.tpin || '—')}</div><div><span class="font-semibold text-slate-500">SKU:</span> <span class="break-words [overflow-wrap:anywhere]">${escapeHtml(product.sku || '—')}</span></div></div></div></td>
            <td class="px-5 py-5 text-center text-xl font-bold">${product.quantityOnHand === null ? '—' : Number(product.quantityOnHand).toLocaleString()}</td>
            <td class="px-5 py-5 text-center text-xl font-bold">${product.quantityAvailable === null ? '—' : Number(product.quantityAvailable).toLocaleString()}</td>
            <td class="px-5 py-5 text-center text-xl font-black text-blue-700">${Number(product.quantityScanned).toLocaleString()}</td>
            <td class="whitespace-nowrap px-5 py-5 text-center text-sm font-semibold text-slate-700">${escapeHtml(product.stockedOn || '—')}</td>
        </tr>`).join('');
}

function renderPickerPopup() {
    if (!state.pickerOpen) return '';

    const presets = [
        ['today', 'Today'],
        ['yesterday', 'Yesterday'],
        ['last3', 'Last 3 Days'],
        ['last7', 'Last 7 Days'],
        ['last30', 'Last 30 Days'],
        ['all', 'All Dates'],
    ];

    return `<div class="absolute left-0 top-[52px] z-40 w-[min(820px,calc(100vw-2.5rem))] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"><div class="grid md:grid-cols-[170px_minmax(0,1fr)]"><div class="border-b border-slate-200 bg-slate-50 p-2 md:border-r md:border-b-0"><div class="grid">${presets.map(([value, label]) => `<button type="button" data-range-preset="${value}" class="border-l-3 px-3 py-2.5 text-left text-sm ${isPresetActive(value) ? 'border-blue-500 bg-blue-50 font-bold text-blue-700' : 'border-transparent text-slate-600 hover:bg-white'}">${label}</button>`).join('')}</div></div><div class="grid sm:grid-cols-2 sm:divide-x sm:divide-slate-200">${renderCalendar(state.calendarMonth, true, false)}${renderCalendar(shiftMonth(state.calendarMonth, 1), false, true)}</div></div><div class="flex items-center justify-between border-t border-slate-200 px-4 py-3"><span class="text-xs font-semibold text-slate-500">${state.draftAllDates ? 'All dates' : `${escapeHtml(state.draftFrom)} – ${escapeHtml(state.draftTo)}`}</span><div class="flex gap-2"><button type="button" data-picker-cancel class="h-9 px-4 text-sm font-bold text-slate-500 hover:text-slate-900">Cancel</button><button type="button" data-picker-apply class="h-9 rounded-lg bg-blue-600 px-4 text-sm font-bold text-white hover:bg-blue-700">Confirm</button></div></div></div>`;
}

function renderProductsPage() {
    return `
        <div class="min-h-dvh bg-slate-100 lg:grid lg:grid-cols-[240px_minmax(0,1fr)]">
            <aside class="border-b border-slate-200 bg-[#0b2550] text-white lg:sticky lg:top-0 lg:h-dvh lg:border-r lg:border-b-0"><div class="flex h-16 items-center px-5 lg:h-20"><a href="/" class="flex items-center gap-3 font-black tracking-tight"><span class="text-blue-400">${icons.logo}</span><span class="text-xl">Pallet<span class="text-blue-400">Scan</span></span></a></div><nav class="flex gap-2 px-3 pb-3 lg:block lg:space-y-2 lg:px-4 lg:pt-5"><button type="button" data-report-nav="overview" class="flex min-h-12 flex-1 items-center gap-3 rounded-xl px-4 text-left text-sm font-semibold text-blue-100 hover:bg-white/10 lg:w-full">${icons.overview}<span>Overview</span></button><button type="button" data-report-nav="products" class="flex min-h-12 flex-1 items-center gap-3 rounded-xl bg-blue-600 px-4 text-left text-sm font-bold text-white shadow-sm lg:w-full">${icons.products}<span>Products</span></button></nav><div class="hidden px-5 text-xs leading-5 text-blue-100/60 lg:absolute lg:bottom-6 lg:block"><div>Report timezone</div><strong class="text-blue-100">America/Los_Angeles</strong></div></aside>
            <div class="min-w-0"><header class="border-b border-slate-200 bg-white"><div class="mx-auto flex h-20 max-w-[1500px] items-center px-5 lg:px-8"><div><h1 class="text-2xl font-black tracking-tight">Products</h1><p class="text-sm text-slate-500">Stock generation products and current inventory.</p></div></div></header>
                <main class="mx-auto max-w-[1500px] px-5 py-6 lg:px-8">
                    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><form id="product-filters" class="grid gap-3 lg:grid-cols-[minmax(180px,.6fr)_minmax(280px,1fr)_auto]"><label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Location</span><span class="relative block"><span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-slate-400">${icons.search}</span><input id="product-location" type="search" value="${escapeHtml(state.productFilters.location)}" placeholder="Search location" autocomplete="off" class="h-11 w-full rounded-lg border border-slate-300 bg-white pr-3 pl-10 outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100"></span></label><label><span class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Search</span><span class="relative block"><span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-slate-400">${icons.search}</span><input id="product-search" value="${escapeHtml(state.productFilters.search)}" placeholder="Search Carton, TPIN or SKU" class="h-11 w-full rounded-lg border border-slate-300 pr-3 pl-10 outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100"></span></label><div class="flex items-end gap-2"><button type="submit" class="h-11 rounded-lg bg-blue-600 px-6 text-sm font-bold text-white hover:bg-blue-700">Apply</button><button type="button" data-product-clear class="h-11 rounded-lg border border-slate-300 px-4 text-sm font-bold text-slate-600 hover:bg-slate-50">Clear</button></div></form></section>
                    ${state.error ? `<div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">${escapeHtml(state.error)}</div>` : ''}
                    <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Products</h2><select id="product-per-page" class="h-10 rounded-lg border border-slate-300 px-3 text-sm"><option value="25" ${state.productFilters.perPage === 25 ? 'selected' : ''}>25 per page</option><option value="50" ${state.productFilters.perPage === 50 ? 'selected' : ''}>50 per page</option><option value="100" ${state.productFilters.perPage === 100 ? 'selected' : ''}>100 per page</option></select></div><div class="overflow-x-auto"><table class="w-full min-w-[1100px] border-collapse"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="w-2/5 px-5 py-4 text-left">Product</th><th class="px-5 py-4 text-center">Quantity On Hand</th><th class="px-5 py-4 text-center">Quantity Available</th><th class="px-5 py-4 text-center">Quantity Scanned</th><th class="px-5 py-4 text-center">Stocked On</th></tr></thead><tbody>${renderProductRows()}</tbody></table></div><div class="flex items-center justify-between border-t border-slate-200 px-5 py-4 text-sm text-slate-500"><span>${state.productMeta.from ?? 0}–${state.productMeta.to ?? 0} of ${state.productMeta.total.toLocaleString()}</span><div class="flex items-center gap-2"><button type="button" data-product-page="${state.productMeta.currentPage - 1}" ${state.productMeta.currentPage <= 1 ? 'disabled' : ''} class="grid h-10 w-10 place-items-center rounded-lg border border-slate-300 disabled:opacity-40">${icons.left}</button><span>Page ${state.productMeta.currentPage} of ${state.productMeta.lastPage}</span><button type="button" data-product-page="${state.productMeta.currentPage + 1}" ${state.productMeta.currentPage >= state.productMeta.lastPage ? 'disabled' : ''} class="grid h-10 w-10 place-items-center rounded-lg border border-slate-300 disabled:opacity-40">${icons.right}</button></div></div></section>
                </main>
            </div>
        </div>`;
}

function render() {
    if (state.activePage === 'products') {
        app.innerHTML = renderProductsPage();
        return;
    }

    const selectedPeriod = periodLabel();

    app.innerHTML = `
        <div class="min-h-dvh bg-slate-100 lg:grid lg:grid-cols-[240px_minmax(0,1fr)]">
            <aside class="border-b border-slate-200 bg-[#0b2550] text-white lg:sticky lg:top-0 lg:h-dvh lg:border-r lg:border-b-0">
                <div class="flex h-16 items-center px-5 lg:h-20"><a href="/" class="flex items-center gap-3 font-black tracking-tight"><span class="text-blue-400">${icons.logo}</span><span class="text-xl">Pallet<span class="text-blue-400">Scan</span></span></a></div>
                <nav class="flex gap-2 px-3 pb-3 lg:block lg:space-y-2 lg:px-4 lg:pt-5">
                    <button type="button" data-report-nav="overview" class="flex min-h-12 flex-1 items-center gap-3 rounded-xl bg-blue-600 px-4 text-left text-sm font-bold text-white shadow-sm lg:w-full">${icons.overview}<span>Overview</span></button>
                    <button type="button" data-report-nav="products" class="flex min-h-12 flex-1 items-center gap-3 rounded-xl px-4 text-left text-sm font-semibold text-blue-100 hover:bg-white/10 lg:w-full">${icons.products}<span>Products</span></button>
                </nav>
            </aside>

            <div class="min-w-0">
                <header class="border-b border-slate-200 bg-white">
                    <div class="mx-auto flex h-20 max-w-[1500px] items-center justify-between px-5 lg:px-8"><div><h1 class="text-2xl font-black tracking-tight">Inventory Overview</h1><p class="text-sm text-slate-500">Entry and inventory processing status by warehouse location.</p></div><div class="hidden rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-600 sm:block">${escapeHtml(selectedPeriod)}</div></div>
                </header>

                <main class="mx-auto max-w-[1500px] px-5 py-6 lg:px-8">
                    <section class="relative flex items-center">
                        <button type="button" data-picker-toggle class="flex h-11 w-72 max-w-full items-center gap-3 rounded-xl border border-slate-300 bg-white px-4 text-left shadow-sm transition hover:border-blue-400 hover:shadow"><span class="text-blue-600">${icons.calendar}</span><span class="min-w-0 flex-1"><span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400">Entry Date</span><strong class="block truncate text-sm text-slate-800">${escapeHtml(selectedPeriod)}</strong></span><span class="text-slate-400">${icons.down}</span></button>
                        ${renderPickerPopup()}
                    </section>

                    ${state.error ? `<div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">${escapeHtml(state.error)}</div>` : ''}

                    <section class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        ${metricCard('Entered Records', state.summary.entered, 'blue', 'entries')}
                        ${metricCard('Stocked', state.summary.stocked, 'green', 'stocked')}
                        ${metricCard('Locations', state.summary.locations, 'violet', 'location')}
                        ${metricCard('Not Stocked', state.summary.notStocked, 'amber', 'warning')}
                    </section>

                    <section class="mt-5">
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="mb-5"><h2 class="text-lg font-bold">Entries by Location</h2></div>${renderBars()}</article>
                    </section>

                    <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 px-5 py-4"><h2 class="text-lg font-bold">Location Summary</h2></div><div class="overflow-x-auto"><table class="w-full min-w-[760px] border-collapse"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3 text-left">Location</th><th class="px-5 py-3 text-center">Entered</th><th class="px-5 py-3 text-center">Stocked</th><th class="px-5 py-3 text-center">Not Stocked</th><th class="px-5 py-3 text-right">Last Entry Time</th></tr></thead><tbody>${renderLocationRows()}</tbody></table></div></section>
                </main>
            </div>
        </div>`;
}

async function loadOverview() {
    state.loading = true;
    state.error = '';
    render();

    const parameters = new URLSearchParams();
    if (state.allDates) parameters.set('allDates', '1');
    else {
        parameters.set('dateFrom', state.dateFrom);
        parameters.set('dateTo', state.dateTo);
    }

    try {
        const response = await api(`/api/v1/reports/inventory/overview?${parameters}`);
        state.summary = response.summary;
        state.locations = response.locations;
    } catch (error) {
        state.error = error.message;
        state.locations = [];
    } finally {
        state.loading = false;
        render();
    }
}

async function loadProducts() {
    state.loading = true;
    state.error = '';
    render();

    const parameters = new URLSearchParams({
        allDates: '1',
        page: String(state.productFilters.page),
        perPage: String(state.productFilters.perPage),
    });
    if (state.productFilters.location) parameters.set('location', state.productFilters.location);
    if (state.productFilters.search) parameters.set('search', state.productFilters.search);

    try {
        const response = await api(`/api/v1/reports/inventory?${parameters}`);
        state.products = response.data;
        state.productLocations = response.locations;
        state.productMeta = response.meta;
    } catch (error) {
        state.error = error.message;
        state.products = [];
    } finally {
        state.loading = false;
        render();
    }
}

function reloadActivePage() {
    if (state.activePage === 'products') loadProducts();
    else loadOverview();
}

function applyRange(from, to) {
    state.dateFrom = from;
    state.dateTo = to;
    state.allDates = false;
    state.pickerOpen = false;
    state.productFilters.page = 1;
    reloadActivePage();
}

app.addEventListener('submit', (event) => {
    if (event.target.id !== 'product-filters') return;
    event.preventDefault();
    state.productFilters.location = document.querySelector('#product-location').value.trim();
    state.productFilters.search = document.querySelector('#product-search').value.trim();
    state.productFilters.page = 1;
    loadProducts();
});

app.addEventListener('click', (event) => {
    const navigation = event.target.closest('[data-report-nav]')?.dataset.reportNav;
    if (navigation && navigation !== state.activePage) {
        state.activePage = navigation;
        state.pickerOpen = false;
        if (navigation === 'products') loadProducts();
        else loadOverview();
        return;
    }

    if (event.target.closest('[data-product-clear]')) {
        state.productFilters.location = '';
        state.productFilters.search = '';
        state.productFilters.page = 1;
        loadProducts();
        return;
    }

    const productPage = Number(event.target.closest('[data-product-page]')?.dataset.productPage);
    if (productPage >= 1 && productPage <= state.productMeta.lastPage && productPage !== state.productFilters.page) {
        state.productFilters.page = productPage;
        loadProducts();
        return;
    }

    if (event.target.closest('[data-picker-toggle]')) {
        state.draftFrom = state.allDates ? losAngelesToday() : state.dateFrom;
        state.draftTo = state.allDates ? losAngelesToday() : state.dateTo;
        state.draftAllDates = state.allDates;
        state.calendarMonth = shiftMonth(state.draftTo, -1);
        state.selectingRange = false;
        state.pickerOpen = !state.pickerOpen;
        render();
        return;
    }

    if (event.target.closest('[data-picker-cancel]')) {
        state.pickerOpen = false;
        render();
        return;
    }

    if (event.target.closest('[data-picker-apply]')) {
        if (state.draftAllDates) {
            state.allDates = true;
            state.pickerOpen = false;
            state.productFilters.page = 1;
            reloadActivePage();
            return;
        }
        applyRange(state.draftFrom, state.draftTo);
        return;
    }

    const monthAction = event.target.closest('[data-month-action]')?.dataset.monthAction;
    if (monthAction) {
        state.calendarMonth = shiftMonth(state.calendarMonth, monthAction === 'previous' ? -1 : 1);
        render();
        return;
    }

    const calendarDate = event.target.closest('[data-calendar-date]')?.dataset.calendarDate;
    if (calendarDate) {
        state.draftAllDates = false;
        if (!state.selectingRange) {
            state.draftFrom = calendarDate;
            state.draftTo = calendarDate;
            state.selectingRange = true;
        } else {
            const firstDate = state.draftFrom;
            state.draftFrom = calendarDate < firstDate ? calendarDate : firstDate;
            state.draftTo = calendarDate < firstDate ? firstDate : calendarDate;
            state.selectingRange = false;
        }
        render();
        return;
    }

    const preset = event.target.closest('[data-range-preset]')?.dataset.rangePreset;
    if (!preset) return;

    if (preset === 'all') {
        state.draftAllDates = true;
        state.selectingRange = false;
        render();
        return;
    }

    const range = presetRange(preset);
    state.draftFrom = range.from;
    state.draftTo = range.to;
    state.draftAllDates = false;
    state.calendarMonth = shiftMonth(range.to, -1);
    state.selectingRange = false;
    render();
});

app.addEventListener('change', (event) => {
    if (event.target.id !== 'product-per-page') return;
    state.productFilters.perPage = Number(event.target.value);
    state.productFilters.page = 1;
    loadProducts();
});

render();
loadOverview();
