const app = document.querySelector('#app');

const state = {
    screen: 'locations',
    locations: [],
    locationsLoaded: false,
    selectedLocation: '',
    locationSearch: '',
    scanType: 'carton',
    photoFile: null,
    photoUrl: '',
    candidates: [],
    selectedCandidate: null,
    manualCode: '',
    isManualEntry: false,
    products: [],
    quantities: {},
    quantity: '',
    savedEntry: null,
    records: [],
    recordType: 'all',
    recordSearch: '',
    recordMeta: null,
    entryDetail: null,
    busy: false,
    error: '',
};

const icons = {
    location: '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>',
    records: '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/></svg>',
    carton: '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7M12 11v10"/></svg>',
    barcode: '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 5v14M7 5v14M10 5v14M14 5v14M18 5v14M21 5v14"/></svg>',
    camera: '<svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h3l2-3h6l2 3h3v13H4z"/><circle cx="12" cy="13" r="4"/></svg>',
    upload: '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 16V4m0 0L7 9m5-5 5 5M4 15v5h16v-5"/></svg>',
    back: '<svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>',
    check: '<svg viewBox="0 0 24 24" class="h-14 w-14" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m5 12 4 4L19 6"/></svg>',
    search: '<svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>',
};

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function button(label, action, options = {}) {
    const variant = options.secondary
        ? 'border border-blue-600 bg-white text-blue-700 hover:bg-blue-50'
        : 'bg-blue-600 text-white shadow-sm hover:bg-blue-700';
    const disabled = options.disabled ? 'disabled opacity-45' : '';

    return `<button type="button" data-action="${action}" ${disabled} class="min-h-10 rounded-lg px-4 py-2.5 text-sm font-bold transition ${variant}">${label}</button>`;
}

function shell(content, options = {}) {
    const title = options.title ?? 'PalletScan';
    const back = options.back
        ? `<button type="button" data-action="back" class="grid h-11 w-11 place-items-center rounded-xl text-white hover:bg-white/10" aria-label="Go back">${icons.back}</button>`
        : '<div class="h-11 w-11"></div>';

    return `
        <div class="min-h-dvh bg-slate-100">
            <header class="safe-top sticky top-0 z-30 bg-[#0b2550] text-white shadow-sm">
                <div class="mx-auto flex h-16 max-w-6xl items-center gap-2 px-3 sm:px-6">
                    ${back}
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-lg font-bold">${escapeHtml(title)}</div>
                        ${options.subtitle ? `<div class="truncate text-xs text-blue-100">${escapeHtml(options.subtitle)}</div>` : ''}
                    </div>
                    <a href="/manual/" class="rounded-lg px-3 py-2 text-sm font-semibold text-blue-100 hover:bg-white/10 hover:text-white">Guide</a>
                </div>
            </header>
            <main class="mx-auto w-full max-w-6xl px-3 py-4 sm:px-6 sm:py-6 ${options.nav ? 'pb-28' : 'pb-8'}">${content}</main>
            ${options.nav ? bottomNav(options.nav) : ''}
        </div>`;
}

function bottomNav(active) {
    return `
        <nav class="safe-bottom fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 shadow-[0_-8px_24px_rgba(15,23,42,.08)] backdrop-blur">
            <div class="mx-auto grid h-18 max-w-xl grid-cols-2">
                <button type="button" data-action="show-locations" class="grid place-items-center gap-0.5 text-xs font-semibold ${active === 'locations' ? 'text-blue-600' : 'text-slate-500'}"><span>${icons.location}</span>Locations</button>
                <button type="button" data-action="show-records" class="grid place-items-center gap-0.5 text-xs font-semibold ${active === 'records' ? 'text-blue-600' : 'text-slate-500'}"><span>${icons.records}</span>Records</button>
            </div>
        </nav>`;
}

function alertMessage() {
    if (!state.error) return '';

    return `<div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">${escapeHtml(state.error)}</div>`;
}

function loading(label = 'Loading…') {
    return `<div class="grid min-h-48 place-items-center rounded-2xl bg-white p-8 text-center shadow-sm"><div><div class="mx-auto h-8 w-8 animate-spin rounded-full border-3 border-blue-100 border-t-blue-600"></div><p class="mt-3 text-sm font-semibold text-slate-600">${escapeHtml(label)}</p></div></div>`;
}

function currentLocation() {
    return `<div class="flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-3 text-sm"><span class="text-blue-600">${icons.location}</span><span class="text-slate-500">Current location:</span><strong class="text-blue-700">${escapeHtml(state.selectedLocation)}</strong></div>`;
}

async function api(url, options = {}) {
    const headers = { Accept: 'application/json', ...(options.headers ?? {}) };
    const response = await fetch(url, { ...options, headers });
    let body = null;

    try {
        body = await response.json();
    } catch {
        body = null;
    }

    if (!response.ok) {
        const validation = body?.errors ? Object.values(body.errors).flat().join(' ') : '';
        throw new Error(validation || body?.message || `The server returned status ${response.status}.`);
    }

    return body;
}

function loadImage(url) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('The selected image could not be opened.'));
        image.src = url;
    });
}

function canvasToBlob(canvas) {
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => blob ? resolve(blob) : reject(new Error('The image could not be prepared for upload.')),
            'image/jpeg',
            0.84,
        );
    });
}

async function prepareImage(file) {
    if (!file.type.startsWith('image/')) throw new Error('Choose a JPG, PNG, or WebP image.');

    const sourceUrl = URL.createObjectURL(file);

    try {
        const image = await loadImage(sourceUrl);
        const maximumDimension = 1920;
        const scale = Math.min(1, maximumDimension / Math.max(image.naturalWidth, image.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
        canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
        const context = canvas.getContext('2d');

        if (!context) throw new Error('Image processing is not supported by this browser.');

        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        const blob = await canvasToBlob(canvas);

        if (blob.size > 8 * 1024 * 1024) throw new Error('The image is still too large. Retake it at a lower resolution.');

        const baseName = file.name.replace(/\.[^.]+$/, '') || 'pallet-scan';
        return new File([blob], `${baseName}.jpg`, { type: 'image/jpeg' });
    } finally {
        URL.revokeObjectURL(sourceUrl);
    }
}

function setScreen(screen) {
    state.screen = screen;
    state.error = '';
    window.scrollTo({ top: 0, behavior: 'instant' });
    render();
}

function resetScan() {
    if (state.photoUrl) URL.revokeObjectURL(state.photoUrl);
    state.photoFile = null;
    state.photoUrl = '';
    state.candidates = [];
    state.selectedCandidate = null;
    state.manualCode = '';
    state.isManualEntry = false;
    state.products = [];
    state.quantities = {};
    state.quantity = '';
    state.error = '';
}

function renderLocations() {
    const query = state.locationSearch.trim().toUpperCase();
    const locations = state.locations.filter((location) => location.startsWith(query));
    const cards = locations.map((location) => `
        <button type="button" data-location="${escapeHtml(location)}" class="flex min-h-16 items-center justify-between rounded-xl border px-4 text-left transition ${state.selectedLocation === location ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-100' : 'border-slate-200 bg-white hover:border-blue-300'}">
            <span class="text-lg font-bold ${state.selectedLocation === location ? 'text-blue-700' : 'text-slate-900'}">${escapeHtml(location)}</span>
            <span class="text-sm font-semibold ${state.selectedLocation === location ? 'text-blue-600' : 'text-slate-400'}">${state.selectedLocation === location ? 'Selected' : '›'}</span>
        </button>`).join('');

    const content = `
        <section class="rounded-2xl bg-white p-4 shadow-sm sm:p-6">
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-4 grid place-items-center text-slate-400">${icons.search}</span>
                <input id="location-search" value="${escapeHtml(state.locationSearch)}" autocomplete="off" enterkeyhint="search" class="h-14 w-full rounded-xl border-2 border-blue-500 bg-white pr-12 pl-12 text-lg font-semibold outline-none focus:ring-4 focus:ring-blue-100" placeholder="e.g. S1 or S1-A1">
                ${state.locationSearch ? '<button type="button" data-action="clear-location-search" class="absolute inset-y-0 right-3 px-2 text-2xl text-slate-400" aria-label="Clear">×</button>' : ''}
            </div>
            <div class="mt-2 flex items-center justify-between gap-3 text-xs text-slate-500"><span>Keep typing to narrow the results</span><span class="flex items-center gap-3"><strong class="text-blue-600">${locations.length} locations</strong>${state.locationSearch ? '<button type="button" data-action="finish-location-search" class="rounded-lg bg-blue-50 px-3 py-1.5 font-bold text-blue-700">Done</button>' : ''}</span></div>
        </section>
        ${alertMessage()}
        <section class="mt-4">
            ${state.busy ? loading('Loading pallet locations') : locations.length ? `<div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">${cards}</div>` : '<div class="rounded-2xl bg-white p-12 text-center shadow-sm"><strong>No pallet locations found</strong><p class="mt-2 text-sm text-slate-500">Valid locations range from S1-A1-A1 to S6-A15-E2.</p></div>'}
        </section>
        <div class="sticky bottom-20 mt-5 flex items-center gap-4 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur sm:bottom-24">
            <div class="min-w-0 flex-1"><div class="text-xs text-slate-500">Selected location</div><div class="truncate text-xl font-bold">${escapeHtml(state.selectedLocation || 'Select one')}</div></div>
            ${button('Start', 'start-scan', { disabled: !state.selectedLocation })}
        </div>`;

    return shell(content, { title: 'Select Pallet Location', nav: 'locations' });
}

function renderScanner() {
    const preview = state.photoUrl
        ? `<img src="${state.photoUrl}" alt="Selected label" class="h-full w-full object-cover">`
        : `<div class="grid h-full place-items-center bg-[linear-gradient(135deg,#14294c,#07152d)] text-center text-white"><div><div class="mx-auto grid h-20 w-20 place-items-center rounded-full border border-white/30 bg-white/10">${icons.camera}</div><p class="mt-4 text-lg font-bold">Photograph ${state.scanType === 'carton' ? 'Carton' : 'TPIN'}</p><p class="mt-1 text-sm text-blue-100">Keep the full code clear and inside the frame</p></div></div>`;
    const controls = `
        <div class="rounded-2xl bg-white p-4 shadow-sm sm:p-6">
            ${currentLocation()}
            <div class="mt-4 grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1.5">
                <button type="button" data-scan-type="carton" class="flex min-h-12 items-center justify-center gap-2 rounded-lg font-bold ${state.scanType === 'carton' ? 'bg-blue-600 text-white shadow' : 'text-slate-600'}">${icons.carton} Carton</button>
                <button type="button" data-scan-type="tpin" class="flex min-h-12 items-center justify-center gap-2 rounded-lg font-bold ${state.scanType === 'tpin' ? 'bg-blue-600 text-white shadow' : 'text-slate-600'}">${icons.barcode} TPIN</button>
            </div>
            ${alertMessage()}
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                <button type="button" data-action="take-photo" ${state.busy ? 'disabled' : ''} class="flex min-h-14 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 font-bold text-white shadow-sm hover:bg-blue-700 disabled:opacity-50">${icons.camera} Take Photo</button>
                <button type="button" data-action="choose-photo" ${state.busy ? 'disabled' : ''} class="flex min-h-14 items-center justify-center gap-2 rounded-xl border border-blue-600 bg-white px-5 font-bold text-blue-700 hover:bg-blue-50 disabled:opacity-50">${icons.upload} Choose Photo</button>
            </div>
            <input id="camera-input" class="hidden" type="file" accept="image/*" capture="environment">
            <input id="gallery-input" class="hidden" type="file" accept="image/*">
            ${state.busy ? '<div class="mt-4 flex items-center justify-center gap-3 rounded-xl bg-blue-50 p-4 text-sm font-semibold text-blue-700"><span class="h-5 w-5 animate-spin rounded-full border-2 border-blue-200 border-t-blue-600"></span>Recognizing image…</div>' : ''}
        </div>`;

    return shell(`
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(320px,.65fr)] lg:items-start">
            <div class="relative aspect-[4/5] min-h-96 overflow-hidden rounded-2xl bg-slate-900 shadow-lg sm:aspect-[4/3] lg:sticky lg:top-22 lg:aspect-[4/3]">${preview}<div class="pointer-events-none absolute inset-8 rounded-2xl border-2 border-white/75"></div></div>
            ${controls}
        </div>`, { back: true, subtitle: state.selectedLocation });
}

function renderResults() {
    const isCarton = state.scanType === 'carton';
    const selectedCode = isCarton ? state.selectedCandidate?.cartonNumber : state.selectedCandidate;
    const candidates = state.candidates.map((candidate, index) => {
        const code = isCarton ? candidate.cartonNumber : candidate;
        const detail = isCarton ? `Carton ID: ${candidate.cartonID}` : `Candidate ${index + 1}`;
        const selected = selectedCode === code;

        return `<button type="button" data-candidate-index="${index}" class="flex min-h-20 w-full items-center gap-3 rounded-xl border p-4 text-left ${selected ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-100' : 'border-slate-200 bg-white'}"><span class="grid h-6 w-6 place-items-center rounded-full border-2 ${selected ? 'border-blue-600' : 'border-slate-400'}">${selected ? '<span class="h-3 w-3 rounded-full bg-blue-600"></span>' : ''}</span><span class="min-w-0 flex-1"><strong class="block truncate text-lg">${escapeHtml(code)}</strong><span class="text-xs text-slate-500">${escapeHtml(detail)}</span></span></button>`;
    }).join('');

    const manualEntry = state.isManualEntry ? `
        <div class="mt-4 rounded-2xl bg-white p-4 shadow-sm sm:p-6">
            <label for="manual-code" class="text-sm font-semibold">${isCarton ? 'Carton Number' : 'TPIN'}</label>
            <input id="manual-code" value="${escapeHtml(state.manualCode)}" autocapitalize="characters" enterkeyhint="done" class="mt-2 h-13 w-full rounded-xl border-2 border-blue-500 px-4 uppercase outline-none ring-4 ring-blue-100" placeholder="ENTER MANUALLY">
        </div>` : '';

    return shell(`
        <div class="mx-auto max-w-3xl pb-28">
            <div class="rounded-2xl bg-white p-4 shadow-sm sm:p-6">
                ${currentLocation()}
                <div class="mt-4 flex items-center gap-2 text-sm text-slate-500">${isCarton ? icons.carton : icons.barcode}<strong class="text-slate-900">${isCarton ? 'Carton' : 'TPIN'} recognition results</strong></div>
                <p class="mt-2 text-sm text-slate-500">Select the correct result or enter it manually.</p>
            </div>
            ${alertMessage()}
            <div class="mt-4 grid gap-3 sm:grid-cols-2">${candidates || '<div class="col-span-full rounded-xl bg-white p-8 text-center text-slate-500">No matching result was found.</div>'}</div>
            ${manualEntry}
        </div>
        <div class="safe-bottom fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-[0_-8px_24px_rgba(15,23,42,.08)] backdrop-blur sm:px-6">
            <div class="mx-auto grid max-w-3xl grid-cols-2 gap-2">
                ${button(state.isManualEntry ? 'Choose Candidate' : 'Enter Manually', state.isManualEntry ? 'choose-candidate' : 'enter-manual', { secondary: true })}
                ${button(`Confirm ${isCarton ? 'Carton' : 'TPIN'}`, 'confirm-result')}
            </div>
        </div>`, { title: 'Recognition Results', back: true, subtitle: state.selectedLocation });
}

function renderCount() {
    const isCarton = state.scanType === 'carton';
    const code = isCarton ? state.selectedCandidate.cartonNumber : state.selectedCandidate;
    const productFields = state.products.map((product) => `
        <label class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4">
            <span class="min-w-0 flex-1"><span class="block text-xs text-slate-500">TPIN</span><strong class="block truncate text-lg">${escapeHtml(product.tpin)}</strong></span>
            <span class="w-28"><span class="block text-xs text-slate-500">Actual quantity</span><input data-product-tpin="${escapeHtml(product.tpin)}" type="number" min="1" step="1" inputmode="numeric" value="${escapeHtml(state.quantities[product.tpin] ?? '')}" class="mt-1 h-11 w-full rounded-lg border border-slate-300 px-3 text-lg font-bold outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100"></span>
        </label>`).join('');

    return shell(`
        <div class="mx-auto max-w-3xl pb-28">
            ${alertMessage()}
            <div class="rounded-2xl bg-white p-4 shadow-sm sm:p-6">
                ${currentLocation()}
                <div class="mt-4 rounded-xl border border-slate-200 p-4"><div class="text-xs text-slate-500">${isCarton ? 'Carton Number' : 'TPIN'}</div><div class="mt-1 text-xl font-bold">${escapeHtml(code)}</div></div>
            </div>
            <section class="mt-5">
                <div class="mb-3"><h2 class="text-lg font-bold">${isCarton ? 'Products in this carton' : 'Actual quantity'}</h2><p class="text-sm text-slate-500">${isCarton ? 'Enter at least one quantity. Leave products blank if they are not being submitted.' : 'Enter the quantity physically counted at this location.'}</p></div>
                ${isCarton ? `<div class="grid gap-3 sm:grid-cols-2">${productFields}</div>` : `<div class="rounded-2xl bg-white p-5 shadow-sm"><label class="text-sm font-semibold" for="single-quantity">Actual quantity</label><input id="single-quantity" type="number" min="1" step="1" inputmode="numeric" value="${escapeHtml(state.quantity)}" class="mt-2 h-14 w-full rounded-xl border border-slate-300 px-4 text-xl font-bold outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"></div>`}
            </section>
        </div>
        <div class="safe-bottom fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-[0_-8px_24px_rgba(15,23,42,.08)] backdrop-blur sm:px-6">
            <div class="mx-auto grid max-w-3xl grid-cols-2 gap-2">${button('Back', 'back', { secondary: true })}${button(state.busy ? 'Saving…' : isCarton ? 'Save Carton' : 'Save', 'save-entry', { disabled: state.busy })}</div>
        </div>`, { title: isCarton ? 'Carton Product Quantities' : 'TPIN Entry', back: true, subtitle: state.selectedLocation });
}

function renderSuccess() {
    const entry = state.savedEntry;
    const isCarton = entry.type === 'carton';
    const products = isCarton ? `<div class="mt-4 border-t border-slate-100 pt-4"><div class="text-xs text-slate-500">Products</div>${entry.products.map((product) => `<div class="mt-2 flex justify-between gap-4 text-sm"><strong>${escapeHtml(product.tpin)}</strong><span>Quantity <strong>${product.quantity}</strong></span></div>`).join('')}</div>` : `<div class="mt-4 flex justify-between border-t border-slate-100 pt-4"><span class="text-sm text-slate-500">Actual quantity</span><strong>${entry.quantity}</strong></div>`;

    return shell(`
        <div class="mx-auto max-w-xl py-6 text-center sm:py-12">
            <div class="mx-auto grid h-28 w-28 place-items-center rounded-full bg-emerald-100 text-emerald-600 ring-12 ring-emerald-50">${icons.check}</div>
            <h1 class="mt-7 text-3xl font-bold">Saved Successfully</h1>
            <p class="mt-2 text-slate-500">${isCarton ? 'Carton' : 'TPIN'} Saved</p>
            <div class="mt-7 rounded-2xl bg-white p-5 text-left shadow-sm">
                ${currentLocation()}
                <div class="mt-4"><div class="text-xs text-slate-500">${isCarton ? 'Carton Number' : 'TPIN'}</div><strong class="mt-1 block text-xl">${escapeHtml(entry.code)}</strong></div>
                ${products}
            </div>
            <div class="mt-5 grid gap-3">${button('Continue Scanning', 'continue-scanning')}${button('Complete Location', 'complete-location', { secondary: true })}</div>
        </div>`, { title: 'Saved' });
}

function renderRecords() {
    const filters = ['all', 'carton', 'tpin'].map((type) => `<button type="button" data-record-type="${type}" class="min-h-10 rounded-full px-4 text-sm font-semibold ${state.recordType === type ? 'bg-blue-600 text-white shadow' : 'text-slate-600'}">${type === 'all' ? 'All' : type === 'tpin' ? 'TPIN' : 'Carton'}</button>`).join('');
    const records = state.records.map((entry) => `
        <button type="button" data-entry-id="${entry.id}" class="flex min-h-24 w-full items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-blue-300">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl ${entry.type === 'carton' ? 'bg-blue-100 text-blue-700' : 'bg-violet-100 text-violet-700'} text-xl font-bold">${entry.type === 'carton' ? 'C' : 'T'}</span>
            <span class="min-w-0 flex-1"><span class="text-xs text-slate-500">${entry.type === 'carton' ? 'Carton' : 'TPIN'}</span><strong class="block truncate text-lg">${escapeHtml(entry.code)}</strong><span class="text-xs text-slate-500">${entry.type === 'carton' ? `${entry.productCount ?? 0} products` : `Quantity ${entry.quantity ?? 0}`} · ${escapeHtml(entry.time)}</span></span>
            <span class="text-right"><span class="block rounded-lg bg-blue-50 px-2 py-1 text-xs font-bold text-blue-700">${escapeHtml(entry.locationCode)}</span><span class="mt-1 block text-xl text-slate-400">›</span></span>
        </button>`).join('');

    return shell(`
        <section class="rounded-2xl bg-white p-4 shadow-sm sm:p-6">
            <h1 class="text-2xl font-bold">Records</h1><p class="mt-1 text-sm text-slate-500">Review saved Carton and TPIN entries.</p>
            <div class="relative mt-4"><span class="pointer-events-none absolute inset-y-0 left-4 grid place-items-center text-slate-400">${icons.search}</span><input id="record-search" value="${escapeHtml(state.recordSearch)}" class="h-13 w-full rounded-xl border border-slate-300 pr-4 pl-12 outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100" placeholder="Search Carton or TPIN"></div>
            <div class="mt-3 grid grid-cols-3 gap-2 rounded-full bg-slate-100 p-1">${filters}</div>
        </section>
        ${alertMessage()}
        <section class="mt-4">${state.busy ? loading('Loading records') : state.records.length ? `<div class="grid gap-3 md:grid-cols-2">${records}</div>` : '<div class="rounded-2xl bg-white p-12 text-center shadow-sm"><strong>No entries found</strong><p class="mt-2 text-sm text-slate-500">Try another search or record type.</p></div>'}</section>`, { title: 'PalletScan', nav: 'records' });
}

function renderEntryDetail() {
    const entry = state.entryDetail;
    if (state.busy) return shell(loading('Loading entry'), { title: 'Entry Details', back: true });
    if (!entry) return shell(`${alertMessage()}<div class="rounded-2xl bg-white p-10 text-center">Unable to load this entry.</div>`, { title: 'Entry Details', back: true });

    const products = entry.type === 'carton'
        ? `<section class="mt-5"><div class="flex items-center justify-between"><h2 class="text-lg font-bold">Products</h2><span class="text-sm text-slate-500">${entry.productCount ?? 0} products</span></div><div class="mt-3 grid gap-3 sm:grid-cols-2">${entry.products.map((product) => `<div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4"><span><span class="block text-xs text-slate-500">TPIN</span><strong class="text-lg">${escapeHtml(product.tpin)}</strong></span><span class="text-right"><span class="block text-xs text-slate-500">Quantity</span><strong class="text-xl text-blue-600">${product.quantity}</strong></span></div>`).join('')}</div></section>`
        : `<section class="mt-5"><h2 class="text-lg font-bold">Recorded Quantity</h2><div class="mt-3 rounded-2xl bg-white p-8 text-center shadow-sm"><strong class="text-5xl text-blue-600">${entry.quantity ?? 0}</strong><span class="ml-2 text-slate-500">units</span></div></section>`;

    return shell(`
        <div class="mx-auto max-w-3xl">
            <section class="rounded-2xl bg-white p-5 shadow-sm">
                <div class="flex items-center gap-4"><span class="grid h-14 w-14 place-items-center rounded-xl ${entry.type === 'carton' ? 'bg-blue-100 text-blue-700' : 'bg-violet-100 text-violet-700'} text-2xl font-bold">${entry.type === 'carton' ? 'C' : 'T'}</span><div><div class="text-sm text-slate-500">${entry.type === 'carton' ? 'Carton' : 'TPIN'}</div><strong class="text-2xl">${escapeHtml(entry.code)}</strong></div></div>
                <dl class="mt-5 grid gap-3 border-t border-slate-100 pt-4 text-sm sm:grid-cols-2"><div class="flex justify-between gap-4"><dt class="text-slate-500">Location</dt><dd class="font-bold text-blue-700">${escapeHtml(entry.locationCode)}</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-500">Recorded</dt><dd class="font-semibold">${escapeHtml(formatDate(entry.createdAt))}</dd></div></dl>
            </section>
            ${products}
        </div>`, { title: 'Entry Details', back: true });
}

function render() {
    if (state.screen === 'locations') app.innerHTML = renderLocations();
    if (state.screen === 'scanner') app.innerHTML = renderScanner();
    if (state.screen === 'results') app.innerHTML = renderResults();
    if (state.screen === 'count') app.innerHTML = renderCount();
    if (state.screen === 'success') app.innerHTML = renderSuccess();
    if (state.screen === 'records') app.innerHTML = renderRecords();
    if (state.screen === 'detail') app.innerHTML = renderEntryDetail();
}

async function loadLocations() {
    if (state.locationsLoaded) return;
    state.busy = true;
    state.error = '';
    render();

    try {
        const response = await api('/api/v1/locations');
        state.locations = response.data.map((item) => item.code);
        state.locationsLoaded = true;
    } catch (error) {
        state.error = error.message;
    } finally {
        state.busy = false;
        render();
    }
}

async function recognize(file) {
    if (!file) return;
    resetScan();
    state.photoFile = file;
    state.photoUrl = URL.createObjectURL(file);
    state.busy = true;
    render();

    try {
        const uploadFile = await prepareImage(file);
        const data = new FormData();
        data.append('type', state.scanType);
        data.append('image', uploadFile);
        const response = await api('/api/v1/ocr/carton', { method: 'POST', body: data });
        state.candidates = state.scanType === 'carton' ? response.data.cartons : response.data.tpins;
        state.selectedCandidate = state.candidates.length === 1 ? state.candidates[0] : null;
        setScreen('results');
    } catch (error) {
        state.error = error.message;
    } finally {
        state.busy = false;
        render();
    }
}

async function confirmResult() {
    const manual = state.manualCode.trim().toUpperCase();
    if (manual) {
        state.selectedCandidate = state.scanType === 'carton'
            ? { cartonNumber: manual, cartonID: /^[0-9]{5,6}$/.test(manual) ? Number(manual) : null }
            : manual;
    }
    if (!state.selectedCandidate) return;
    state.error = '';

    if (state.scanType === 'tpin') {
        setScreen('count');
        return;
    }

    state.busy = true;
    render();

    try {
        const query = state.selectedCandidate.cartonID
            ? `cartonID=${encodeURIComponent(state.selectedCandidate.cartonID)}`
            : `cartonNumber=${encodeURIComponent(state.selectedCandidate.cartonNumber)}`;
        const response = await api(`/api/v1/cartons/products?${query}`);
        const carton = response.data?.[0];
        if (!carton || !carton.products?.length) throw new Error('This carton has no products. Check the Carton Number and try again.');
        state.selectedCandidate = { cartonID: carton.cartonID, cartonNumber: carton.cartonNumber };
        state.products = carton.products;
        state.quantities = Object.fromEntries(carton.products.map((product) => [product.tpin, '']));
        setScreen('count');
    } catch (error) {
        state.error = error.message;
    } finally {
        state.busy = false;
        render();
    }
}

function positiveInteger(value) {
    const number = Number(value);
    return Number.isInteger(number) && number > 0 ? number : null;
}

async function saveEntry() {
    state.error = '';
    let body;

    if (state.scanType === 'carton') {
        const enteredProducts = state.products.filter((product) => String(state.quantities[product.tpin] ?? '').trim() !== '');

        if (enteredProducts.length === 0) {
            state.error = 'Enter a quantity for at least one product.';
            render();
            return;
        }

        const products = enteredProducts.map((product) => ({ tpin: product.tpin, quantity: positiveInteger(state.quantities[product.tpin]) }));

        if (products.some((product) => product.quantity === null)) {
            state.error = 'Each entered quantity must be a whole number greater than 0.';
            render();
            return;
        }
        body = { locationCode: state.selectedLocation, cartonNumber: state.selectedCandidate.cartonNumber, products };
    } else {
        const quantity = positiveInteger(state.quantity);
        if (quantity === null) {
            state.error = 'Enter a whole number greater than 0.';
            render();
            return;
        }
        body = { locationCode: state.selectedLocation, tpin: state.selectedCandidate, quantity };
    }

    state.busy = true;
    render();

    try {
        const url = state.scanType === 'carton' ? '/api/v1/entries/cartons' : '/api/v1/entries/products/tpin';
        await api(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        state.savedEntry = state.scanType === 'carton'
            ? { type: 'carton', code: body.cartonNumber, products: body.products }
            : { type: 'tpin', code: body.tpin, quantity: body.quantity };
        setScreen('success');
    } catch (error) {
        state.error = error.message;
    } finally {
        state.busy = false;
        render();
    }
}

async function loadRecords() {
    state.busy = true;
    state.error = '';
    render();

    try {
        const params = new URLSearchParams({ type: state.recordType, search: state.recordSearch.trim(), perPage: '100' });
        const response = await api(`/api/v1/entries?${params}`);
        state.records = response.data.filter((entry) => entry.type === 'carton' || entry.type === 'tpin');
        state.recordMeta = response.meta;
    } catch (error) {
        state.error = error.message;
    } finally {
        state.busy = false;
        render();
    }
}

async function loadEntry(id) {
    state.screen = 'detail';
    state.entryDetail = null;
    state.busy = true;
    state.error = '';
    render();

    try {
        const response = await api(`/api/v1/entries/${id}`);
        state.entryDetail = response.data;
    } catch (error) {
        state.error = error.message;
    } finally {
        state.busy = false;
        render();
    }
}

function formatDate(value) {
    if (!value) return '';
    return value.slice(0, 10) + '  ' + value.slice(11, 16);
}

function goBack() {
    if (state.screen === 'scanner') setScreen('locations');
    else if (state.screen === 'results') {
        resetScan();
        setScreen('scanner');
    }
    else if (state.screen === 'count') setScreen('results');
    else if (state.screen === 'detail') setScreen('records');
}

app.addEventListener('click', (event) => {
    const actionElement = event.target.closest('[data-action]');
    const action = actionElement?.dataset.action;
    const location = event.target.closest('[data-location]')?.dataset.location;
    const scanType = event.target.closest('[data-scan-type]')?.dataset.scanType;
    const candidateIndex = event.target.closest('[data-candidate-index]')?.dataset.candidateIndex;
    const recordType = event.target.closest('[data-record-type]')?.dataset.recordType;
    const entryId = event.target.closest('[data-entry-id]')?.dataset.entryId;

    if (location) {
        document.querySelector('#location-search')?.blur();
        state.selectedLocation = location;
        render();
    }
    if (scanType && scanType !== state.scanType) {
        state.scanType = scanType;
        resetScan();
        render();
    }
    if (candidateIndex !== undefined) {
        state.selectedCandidate = state.candidates[Number(candidateIndex)];
        state.manualCode = '';
        state.isManualEntry = false;
        render();
    }
    if (recordType && recordType !== state.recordType) {
        state.recordType = recordType;
        loadRecords();
    }
    if (entryId) loadEntry(entryId);
    if (action === 'back') goBack();
    if (action === 'clear-location-search') {
        state.locationSearch = '';
        render();
    }
    if (action === 'finish-location-search') document.querySelector('#location-search')?.blur();
    if (action === 'start-scan' && state.selectedLocation) {
        resetScan();
        setScreen('scanner');
    }
    if (action === 'take-photo') document.querySelector('#camera-input')?.click();
    if (action === 'choose-photo') document.querySelector('#gallery-input')?.click();
    if (action === 'retake') {
        resetScan();
        setScreen('scanner');
    }
    if (action === 'enter-manual') {
        state.isManualEntry = true;
        state.selectedCandidate = null;
        render();
        document.querySelector('#manual-code')?.focus();
    }
    if (action === 'choose-candidate') {
        state.isManualEntry = false;
        state.manualCode = '';
        render();
    }
    if (action === 'confirm-result') confirmResult();
    if (action === 'save-entry') saveEntry();
    if (action === 'continue-scanning') {
        resetScan();
        setScreen('scanner');
    }
    if (action === 'complete-location' || action === 'show-locations') {
        resetScan();
        state.selectedLocation = '';
        setScreen('locations');
    }
    if (action === 'show-records') {
        setScreen('records');
        loadRecords();
    }
});

app.addEventListener('input', (event) => {
    if (event.target.id === 'location-search') {
        const selectionStart = event.target.selectionStart ?? event.target.value.length;
        const selectionEnd = event.target.selectionEnd ?? selectionStart;
        state.locationSearch = event.target.value;
        render();
        const searchInput = document.querySelector('#location-search');
        searchInput?.focus();
        searchInput?.setSelectionRange(selectionStart, selectionEnd);
    }
    if (event.target.id === 'manual-code') {
        state.manualCode = event.target.value.toUpperCase();
        state.selectedCandidate = null;
    }
    if (event.target.id === 'single-quantity') state.quantity = event.target.value;
    if (event.target.dataset.productTpin) state.quantities[event.target.dataset.productTpin] = event.target.value;
    if (event.target.id === 'record-search') {
        state.recordSearch = event.target.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadRecords, 300);
    }
});

let searchTimer;
app.addEventListener('change', (event) => {
    if (event.target.id === 'camera-input' || event.target.id === 'gallery-input') recognize(event.target.files?.[0]);
});

app.addEventListener('keyup', (event) => {
    if (event.target.id === 'location-search' && event.key === 'Enter') event.target.blur();
    if (event.target.id === 'record-search' && event.key === 'Enter') {
        state.recordSearch = event.target.value;
        loadRecords();
    }
});

render();
loadLocations();
