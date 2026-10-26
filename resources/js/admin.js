import { attachDatePicker } from './admin-date-picker';

const app = document.querySelector('#admin-app');
const urls = JSON.parse(app.dataset.config);
const content = document.querySelector('#content');
const detail = document.querySelector('#detail-dialog');
const batch = document.querySelector('#batch-dialog');
const labels = { pending: '待处理', failed: '处理失败', success: '已成功' };
const names = { overview: 'Entries 管理', 'small-parce': 'Small Parce', ltl: 'LTL', exceptions: '数据异常' };
const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
const badge = status => `<span class="badge ${status}">${labels[status]}</span>`;
let zone = 'overview';
let summary = null;
let rows = [];
let meta = {};
let selected = new Set();
let filters = { status: 'pending', per_page: '10', page: 1 };
let loading = false;
let running = false;
let sequence = 0;
let noticeTimer;

function notify(message) {
    const notice = document.querySelector('#notice');
    notice.textContent = message;
    notice.hidden = false;
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => { notice.hidden = true; }, 7000);
}
async function api(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, ...options.headers },
    });
    if (response.status === 401 || response.status === 419) {
        throw new Error('登录已过期，请刷新页面重新登录。');
    }
    const data = await response.json();
    if (!response.ok) {
        throw new Error(Object.values(data.errors ?? {}).flat()[0] ?? data.message ?? '请求失败，请稍后重试。');
    }
    return data;
}
async function refreshSummary() {
    summary = await api(urls.summary);
    document.querySelector('#exception-count').textContent = summary.exceptions;
}
function currentSection() {
    const last = location.pathname.replace(/\/$/, '').split('/').pop();
    return ['small-parce', 'ltl', 'exceptions'].includes(last) ? last : 'overview';
}
function sectionUrl(section) {
    return section === 'overview' ? urls.home : urls.section.replace('__SECTION__', section);
}
function overview() {
    content.innerHTML = `<h1>Entries 管理</h1><p class="muted">按业务分区核对扫描记录，生成 Portal 库存。</p>
        <div class="overview-grid">${['small-parce', 'ltl'].map(key => `<article class="zone-card ${key === 'ltl' ? 'ltl' : ''}">
            <header><span class="zone-icon" aria-hidden="true">${key === 'ltl' ? '▱' : '▣'}</span><div><h2>${names[key]}</h2><p>${key === 'ltl' ? 'N1 – N4' : 'S1 – S6'}</p><p>${key === 'ltl' ? 'Carton' : 'Carton / SKU / TPIN'}</p></div></header>
            <footer><div class="stats">${Object.entries(labels).map(([status, label]) => `<div class="stat ${status}">${label}<strong>${summary[key][status]}</strong></div>`).join('')}</div><a class="primary" data-section="${key}" href="${sectionUrl(key)}">进入 ${names[key]} →</a></footer></article>`).join('')}</div>
        <div class="exception-banner"><span aria-hidden="true">⚠</span><div><h3>数据异常 · ${summary.exceptions}</h3><p>库位或类型不符合分区规则的记录，不能直接生成库存。</p></div><a data-section="exceptions" href="${sectionUrl('exceptions')}">查看 →</a></div>`;
}
function option(value, label, key) {
    return `<option value="${value}" ${String(filters[key] ?? '') === String(value) ? 'selected' : ''}>${label}</option>`;
}
function renderList() {
    const exceptions = zone === 'exceptions';
    const prefixes = zone === 'small-parce' ? ['S1','S2','S3','S4','S5','S6'] : ['N1','N2','N3','N4'];
    content.innerHTML = `<div class="page-heading"><div><h1>${names[zone]} <span class="zone-tag">${exceptions ? '' : zone === 'ltl' ? 'N1 – N4' : 'S1 – S6'}</span></h1><p>${exceptions ? '库位或类型不符合规则，仅供查看。' : zone === 'ltl' ? 'Carton 库存处理' : 'Carton、SKU、TPIN 库存处理'}</p></div>${exceptions ? '' : '<button class="primary" id="generate-selected" disabled>生成所选库存</button>'}</div>
        ${exceptions ? '' : `<div class="tabs">${Object.entries(labels).map(([status,label]) => `<button class="tab ${filters.status === status ? 'active' : ''}" data-status="${status}">${label}<b>${summary[zone][status]}</b></button>`).join('')}</div>`}
        <section class="panel"><form class="filters" id="filters">
        <label>状态<select name="status">${option('all','全部','status')}${Object.entries(labels).map(([key,label]) => option(key,label,'status')).join('')}</select></label>
        ${zone === 'small-parce' ? `<label>类型<select name="type">${option('','全部','type')}${['carton','sku','tpin'].map(type => option(type,type.toUpperCase(),'type')).join('')}</select></label>` : ''}
        ${exceptions ? '' : `<label>区域<select name="prefix">${option('','全部区域','prefix')}${prefixes.map(prefix => option(prefix,prefix,'prefix')).join('')}</select></label>`}
        <label>库位<input name="location" placeholder="输入完整库位" maxlength="16" value="${escape(filters.location)}"></label>
        <label>${zone === 'ltl' ? '箱号' : '编码'}<input name="code" placeholder="搜索编码" value="${escape(filters.code)}"></label>
        <input type="hidden" name="from" value="${escape(filters.from)}"><input type="hidden" name="to" value="${escape(filters.to)}">
        <button type="button" class="date-trigger" data-date-picker aria-haspopup="dialog" aria-expanded="false" aria-controls="admin-date-popover"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg><span><small>ENTRY DATE</small><strong>${filters.from ? escape(filters.from === filters.to ? filters.from : `${filters.from} – ${filters.to}`) : 'All Dates'}</strong></span><span class="date-chevron">⌄</span></button>
        <div class="filter-actions"><button class="primary" type="submit">查询</button><button type="button" id="reset">重置</button></div>
        </form><div class="table-toolbar"><span id="selection-count">已选 0 条</span><span>时间：America/Los_Angeles · 已删除记录不显示</span></div>
        <div class="table-wrap"><table><thead><tr><th><input type="checkbox" id="select-all" aria-label="选择当前页可处理记录" ${exceptions ? 'disabled' : ''}></th><th>ID</th><th>库位</th><th>类型</th><th>编码</th><th>数量</th><th>状态</th><th>创建时间</th><th>操作</th></tr></thead><tbody id="rows"><tr><td colspan="9" class="empty">正在加载…</td></tr></tbody></table></div><div id="pagination" class="pagination"></div></section>`;
    attachDatePicker(document.querySelector('#filters'), app.dataset.today);
    document.querySelector('#filters').addEventListener('submit', event => {
        event.preventDefault();
        const form = event.currentTarget;
        if (form.elements.from.value && form.elements.to.value && form.elements.from.value > form.elements.to.value) return notify('结束日期不能早于开始日期。');
        filters = { ...filters, ...Object.fromEntries(new FormData(form)), page: 1 };
        renderList(); loadRows();
    });
    document.querySelector('#reset').onclick = () => { filters = { status: exceptions ? 'all' : 'pending', per_page: '10', page: 1 }; renderList(); loadRows(); };
    document.querySelectorAll('[data-status]').forEach(button => { button.onclick = () => { filters.status = button.dataset.status; filters.page = 1; renderList(); loadRows(); }; });
    document.querySelector('#generate-selected')?.addEventListener('click', () => confirmBatch(rows.filter(row => selected.has(row.id))));
    document.querySelector('#select-all').onchange = event => {
        selected = new Set(event.target.checked ? rows.filter(row => row.status !== 'success').map(row => row.id) : []);
        paintRows();
    };
}
async function loadRows() {
    const requestId = ++sequence;
    loading = true; selected.clear();
    const generate = document.querySelector('#generate-selected');
    if (generate) generate.disabled = true;
    document.querySelector('#select-all').disabled = true;
    document.querySelector('#rows').innerHTML = '<tr><td colspan="9" class="empty">正在加载…</td></tr>';
    document.querySelector('#pagination').innerHTML = '';
    document.querySelector('#selection-count').textContent = '已选 0 条';
    try {
        const params = new URLSearchParams({ zone, ...Object.fromEntries(Object.entries(filters).filter(([,value]) => value !== '' && value != null)) });
        const response = await api(`${urls.entries}?${params}`);
        if (requestId !== sequence) return;
        rows = response.data; meta = response.meta;
        loading = false; paintRows();
    } catch (error) {
        if (requestId !== sequence) return;
        loading = false;
        document.querySelector('#rows').innerHTML = `<tr><td colspan="9" class="empty">${escape(error.message)} <button id="retry-load">重试</button></td></tr>`;
        document.querySelector('#retry-load').onclick = loadRows;
    }
}
function paintRows() {
    const exceptions = zone === 'exceptions';
    document.querySelector('#rows').innerHTML = rows.length ? rows.map(row => `<tr class="${selected.has(row.id) ? 'selected' : ''}">
        <td><input type="checkbox" data-select="${row.id}" aria-label="选择 Entry ${row.id}" ${selected.has(row.id) ? 'checked' : ''} ${exceptions || row.status === 'success' ? 'disabled' : ''}></td>
        <td>${row.id}</td><td>${escape(row.location)}</td><td>${escape(row.type.toUpperCase())}</td><td>${escape(row.code)}</td><td>${row.quantity ?? '—'}</td><td>${badge(row.status)}</td><td>${escape(row.createdAt)}</td><td><button class="text-button" data-detail="${row.id}">查看</button></td></tr>`).join('') : '<tr><td colspan="9" class="empty">没有符合筛选条件的记录。</td></tr>';
    document.querySelectorAll('[data-select]').forEach(input => { input.onchange = () => { const id = Number(input.dataset.select); input.checked ? selected.add(id) : selected.delete(id); paintRows(); }; });
    document.querySelectorAll('[data-detail]').forEach(button => { button.onclick = () => openDetail(button.dataset.detail); });
    document.querySelector('#selection-count').textContent = `已选 ${selected.size} 条`;
    const eligible = rows.filter(row => row.status !== 'success');
    const all = document.querySelector('#select-all');
    all.disabled = exceptions || !eligible.length;
    all.checked = eligible.length > 0 && selected.size === eligible.length;
    all.indeterminate = selected.size > 0 && selected.size < eligible.length;
    const generate = document.querySelector('#generate-selected');
    if (generate) generate.disabled = !selected.size || running;
    document.querySelector('#pagination').innerHTML = `<span>共 ${meta.total} 条 · 已选 ${selected.size} 条</span><div><button id="prev" aria-label="上一页" ${meta.current_page <= 1 ? 'disabled' : ''}>‹</button><span>${meta.current_page} / ${meta.last_page}</span><button id="next" aria-label="下一页" ${meta.current_page >= meta.last_page ? 'disabled' : ''}>›</button><select id="per-page" aria-label="每页条数">${[10,25,50,100].map(n => option(n,`${n} 条/页`,'per_page')).join('')}</select></div>`;
    document.querySelector('#prev').onclick = () => { filters.page--; loadRows(); };
    document.querySelector('#next').onclick = () => { filters.page++; loadRows(); };
    document.querySelector('#per-page').onchange = event => { filters.per_page = event.target.value; filters.page = 1; loadRows(); };
}
async function openDetail(id) {
    detail.innerHTML = '<div class="dialog-head"><h2>Entry 详情</h2><button class="close" aria-label="关闭">×</button></div><p class="empty">正在加载…</p>';
    detail.querySelector('.close').onclick = () => detail.close();
    detail.showModal();
    try {
        const { data: row } = await api(urls.detail.replace('__ID__', id));
        if (!detail.open) return;
        detail.innerHTML = `<div class="dialog-head"><h2>Entry #${row.id}</h2><button class="close" aria-label="关闭">×</button></div><span class="badge">${names[zone]}</span> ${badge(row.status)}
        <div class="detail-grid"><div>${row.imageUrl ? `<a href="${escape(row.imageUrl)}" target="_blank" rel="noopener"><img class="entry-photo" src="${escape(row.imageUrl)}" alt="Entry ${row.id} 图片附件"><p>查看原图 ↗</p></a>` : '<div class="no-photo">暂无图片附件</div>'}</div><div>
        <div class="detail-card"><dl>${[['库位',row.location],['类型',row.type.toUpperCase()],['编码',row.code],['数量',row.quantity ?? '—'],['Carton ID',row.cartonId ?? '—'],['创建时间',row.createdAt],['更新时间',row.updatedAt]].map(([key,value]) => `<dt>${key}</dt><dd>${escape(value)}</dd>`).join('')}</dl></div>
        ${row.status !== 'success' ? `<form id="edit-code" class="detail-card"><h3>修改编码</h3><div class="code-edit-controls"><input name="code" aria-label="新的编码" value="${escape(row.code)}" maxlength="255" required><button type="submit" class="primary">保存编码</button></div><p class="muted">保存后记录修改前后编码、操作账号和时间。</p><p id="code-error" class="error-box" role="alert" hidden></p></form>` : ''}
        ${row.error ? `<div class="error-box"><strong>报错信息</strong><p>${escape(row.error)}</p></div>` : ''}
        ${row.type === 'carton' ? `<div class="detail-card"><h3>商品明细</h3>${row.inventoryError ? `<p class="error-box">${escape(row.inventoryError)}</p>` : ''}${row.products.length ? `<div class="table-wrap"><table class="inventory-comparison"><thead><tr><th>TPIN</th><th>Quantity On Hand</th><th>Quantity Available</th><th>Quantity Scanned</th></tr></thead><tbody>${row.products.map(product => `<tr><td>${escape(product.tpin)}</td><td>${escape(product.qtyOnHand ?? '—')}</td><td>${escape(product.qtyAvailable ?? '—')}</td><td>${escape(product.quantity)}</td></tr>`).join('')}</tbody></table></div>` : '<p class="muted">暂无商品明细</p>'}</div>` : ''}</div></div>
        <div class="detail-card"><h3>编码修改记录</h3>${row.codeChanges?.length ? `<div class="table-wrap"><table><thead><tr><th>原编码</th><th>新编码</th><th>操作账号</th><th>时间</th></tr></thead><tbody>${row.codeChanges.map(log => `<tr><td>${escape(log.oldCode)}</td><td>${escape(log.newCode)}</td><td>${escape(log.actor)}</td><td>${escape(log.createdAt)}</td></tr>`).join('')}</tbody></table></div>` : '<p class="muted">暂无修改记录</p>'}</div>
        <div class="detail-card"><h3>处理日志</h3>${row.logs.length ? `<div class="table-wrap"><table class="logs"><thead><tr><th>操作</th><th>TPIN</th><th>库存数量</th><th>Carton / Bin</th></tr></thead><tbody>${row.logs.map(log => `<tr><td>${escape(log.action)}</td><td>${escape(log.tpin)}</td><td>${log.qty_on_hand_before ?? '—'} → ${log.qty_on_hand_after}</td><td>${log.carton_id} / ${log.bin_id}</td></tr>`).join('')}</tbody></table></div>` : '<p class="muted">尚无库存生成日志</p>'}</div>
        ${zone !== 'exceptions' && row.status !== 'success' ? `<div class="detail-footer"><button class="primary" id="generate-one">${row.status === 'failed' ? '重试生成库存' : '生成此条库存'}</button></div>` : ''}`;
        detail.querySelector('.close').onclick = () => detail.close();
        detail.querySelector('#edit-code')?.addEventListener('submit', async event => {
            event.preventDefault();
            const form = event.currentTarget;
            const button = form.querySelector('button');
            const errorBox = form.querySelector('#code-error');
            const code = form.elements.code.value.trim();
            if (!code || code === row.code) return;
            button.disabled = true;
            errorBox.hidden = true;
            const generateButton = detail.querySelector('#generate-one');
            if (generateButton) generateButton.disabled = true;
            try {
                await api(urls.updateCode.replace('__ID__', row.id), { method: 'PATCH', body: JSON.stringify({ code, original_code: row.code }) });
                await loadRows();
                if (detail.open) await openDetail(row.id);
            } catch (error) {
                errorBox.textContent = error.message;
                errorBox.hidden = false;
                button.disabled = false;
                if (generateButton) generateButton.disabled = false;
            }
        });
        detail.querySelector('#generate-one')?.addEventListener('click', () => confirmBatch([row]));
    } catch (error) {
        detail.innerHTML = `<div class="dialog-head"><h2>加载失败</h2><button class="close" aria-label="关闭">×</button></div><p class="error-box">${escape(error.message)}</p>`;
        detail.querySelector('.close').onclick = () => detail.close();
    }
}
function confirmBatch(entries) {
    if (running || !entries.length || zone === 'exceptions') return;
    const counts = entries.reduce((result,row) => { result[row.type] = (result[row.type] ?? 0) + 1; return result; }, {});
    batch.innerHTML = `<h2>确认生成库存</h2><p>将处理 <strong>${names[zone]}</strong> 的 <strong>${entries.length}</strong> 条记录：</p><p>${Object.entries(counts).map(([type,count]) => `${type.toUpperCase()} ${count} 条`).join('、')}</p><p class="muted">确认后将写入 Portal 库存，已有库存数量可能被更新。处理期间请保持页面打开。</p><div class="dialog-actions"><button id="cancel-batch">取消</button><button class="primary" id="start-batch">确认生成</button></div>`;
    batch.showModal();
    document.querySelector('#cancel-batch').onclick = () => batch.close();
    document.querySelector('#start-batch').onclick = () => runBatch(entries);
}
async function runBatch(entries) {
    running = true;
    const processingZone = zone;
    let done = 0, succeeded = 0, failed = 0;
    batch.innerHTML = `<h2 id="batch-title">正在生成库存…</h2><progress max="${entries.length}" value="0"></progress><p id="batch-progress" aria-live="polite">已处理 0 / ${entries.length}</p><div class="batch-results"></div><div class="dialog-actions" id="batch-actions"></div>`;
    for (const row of entries) {
        let message;
        let success = false;
        try {
            const { data } = await api(urls.generate.replace('__ID__', row.id), { method: 'POST', body: JSON.stringify({ zone: processingZone }) });
            success = data.status === 'success';
            message = success ? '生成成功' : data.error || '生成失败';
        } catch (error) { message = error.message; }
        success ? succeeded++ : failed++;
        done++;
        batch.querySelector('progress').value = done;
        batch.querySelector('#batch-progress').textContent = `已处理 ${done} / ${entries.length} · 成功 ${succeeded} · 失败 ${failed}`;
        const result = document.createElement('div');
        result.className = 'batch-result';
        result.innerHTML = `<span class="badge ${success ? 'success' : 'failed'}">#${row.id}</span><span>${escape(message)}</span>`;
        batch.querySelector('.batch-results').append(result);
        if (/登录已过期/.test(message)) break;
    }
    running = false;
    batch.querySelector('#batch-title').textContent = done === entries.length ? '处理完成' : '处理已中断';
    batch.querySelector('#batch-actions').innerHTML = '<button class="primary" id="finish-batch">返回列表</button>';
    document.querySelector('#finish-batch').onclick = async () => {
        batch.close(); detail.close();
        try { await refreshSummary(); renderList(); await loadRows(); } catch (error) { notify(error.message); }
    };
}
batch.addEventListener('cancel', event => { if (running) event.preventDefault(); });
window.addEventListener('beforeunload', event => { if (running) { event.preventDefault(); event.returnValue = ''; } });
async function navigate(section, push = true) {
    if (running) return;
    sequence++;
    zone = section; selected.clear();
    filters = { status: zone === 'exceptions' ? 'all' : 'pending', per_page: '10', page: 1 };
    if (push) history.pushState({}, '', sectionUrl(section));
    document.querySelectorAll('nav [data-section]').forEach(link => link.classList.toggle('active', link.dataset.section === zone));
    if (zone === 'overview') { document.querySelector('#admin-date-popover')?.remove(); overview(); }
    else { renderList(); await loadRows(); }
}
app.addEventListener('click', event => {
    const link = event.target.closest('a[data-section]');
    if (!link || event.ctrlKey || event.metaKey) return;
    event.preventDefault(); navigate(link.dataset.section);
});
window.addEventListener('popstate', () => navigate(currentSection(), false));
(async () => {
    try { await refreshSummary(); await navigate(currentSection(), false); }
    catch (error) { content.innerHTML = `<div class="empty"><p>${escape(error.message)}</p><button id="reload">重新加载</button></div>`; document.querySelector('#reload').onclick = () => location.reload(); }
})();
