const presets = [['today', 'Today'], ['yesterday', 'Yesterday'], ['last3', 'Last 3 Days'], ['last7', 'Last 7 Days'], ['last30', 'Last 30 Days'], ['all', 'All Dates']];
const shiftDate = (value, days) => {
    const date = new Date(`${value}T12:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);
    return date.toISOString().slice(0, 10);
};
const shiftMonth = (value, months) => {
    const date = new Date(`${value.slice(0, 7)}-15T12:00:00Z`);
    date.setUTCMonth(date.getUTCMonth() + months, 1);
    return date.toISOString().slice(0, 10);
};

export function attachDatePicker(form, today) {
    document.querySelector('#admin-date-popover')?.remove();
    const trigger = form.querySelector('[data-date-picker]');
    const from = form.elements.from;
    const to = form.elements.to;
    const picker = document.createElement('div');
    picker.id = 'admin-date-popover';
    picker.className = 'date-popover';
    picker.setAttribute('popover', 'auto');
    picker.setAttribute('role', 'dialog');
    picker.setAttribute('aria-label', 'Entry Date 日期范围');
    document.body.append(picker);
    let draftFrom, draftTo, allDates, month, selecting;
    const range = preset => {
        if (preset === 'yesterday') return { from: shiftDate(today, -1), to: shiftDate(today, -1) };
        const days = { today: 0, last3: -2, last7: -6, last30: -29 }[preset] ?? 0;
        return { from: shiftDate(today, days), to: today };
    };
    function position() {
        const rect = trigger.getBoundingClientRect();
        const width = Math.min(820, window.innerWidth - 24);
        picker.style.width = `${width}px`;
        picker.style.left = `${Math.max(12, Math.min(rect.left, window.innerWidth - width - 12))}px`;
        const height = picker.getBoundingClientRect().height;
        picker.style.top = `${Math.max(12, Math.min(rect.bottom + 8, window.innerHeight - height - 12))}px`;
    }
    function calendar(value, previous, next) {
        const date = new Date(`${value}T12:00:00Z`);
        const year = date.getUTCFullYear();
        const monthIndex = date.getUTCMonth();
        const first = new Date(Date.UTC(year, monthIndex, 1)).getUTCDay();
        const count = new Date(Date.UTC(year, monthIndex + 1, 0)).getUTCDate();
        const label = new Intl.DateTimeFormat('en-US', { month: 'short', year: 'numeric', timeZone: 'UTC' }).format(date);
        const days = Array.from({ length: count }, (_, index) => {
            const day = index + 1;
            const key = `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const endpoint = !allDates && (key === draftFrom || key === draftTo);
            const inside = !allDates && key >= draftFrom && key <= draftTo;
            return `<button type="button" data-day="${key}" aria-label="${key}" aria-pressed="${endpoint}" class="${endpoint ? 'endpoint' : inside ? 'in-range' : ''}">${day}</button>`;
        }).join('');
        return `<section class="date-month"><header>${previous ? '<button type="button" data-month="-1" aria-label="上个月">‹</button>' : '<span></span>'}<strong>${label}</strong>${next ? '<button type="button" data-month="1" aria-label="下个月">›</button>' : '<span></span>'}</header><div class="date-week">${['Su','Mo','Tu','We','Th','Fr','Sa'].map(day => `<span>${day}</span>`).join('')}</div><div class="date-days">${'<span></span>'.repeat(first)}${days}</div></section>`;
    }
    function render() {
        picker.innerHTML = `<div class="date-picker-body"><div class="date-presets">${presets.map(([key,label]) => {
            const value = range(key);
            const active = key === 'all' ? allDates : !allDates && value.from === draftFrom && value.to === draftTo;
            return `<button type="button" data-preset="${key}" class="${active ? 'active' : ''}">${label}</button>`;
        }).join('')}</div><div class="date-calendars">${calendar(month,true,false)}${calendar(shiftMonth(month,1),false,true)}</div></div><footer><span>${allDates ? 'All Dates' : `${draftFrom} – ${draftTo}`}</span><div><button type="button" data-cancel>Cancel</button><button type="button" class="primary" data-confirm>Confirm</button></div></footer>`;
        if (picker.matches(':popover-open')) position();
    }
    trigger.onclick = () => {
        if (picker.matches(':popover-open')) { picker.hidePopover(); return; }
        draftFrom = from.value || today;
        draftTo = to.value || today;
        allDates = !from.value && !to.value;
        month = shiftMonth(draftTo, -1);
        selecting = false;
        render();
        picker.showPopover();
        position();
    };
    picker.addEventListener('toggle', event => {
        trigger.setAttribute('aria-expanded', String(event.newState === 'open'));
    });
    picker.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.hasAttribute('data-cancel')) { picker.hidePopover(); trigger.focus(); return; }
        if (button.hasAttribute('data-confirm')) {
            from.value = allDates ? '' : draftFrom;
            to.value = allDates ? '' : draftTo;
            picker.hidePopover();
            form.requestSubmit();
            return;
        }
        if (button.dataset.month) month = shiftMonth(month, Number(button.dataset.month));
        if (button.dataset.preset) {
            const key = button.dataset.preset;
            allDates = key === 'all';
            selecting = false;
            if (!allDates) {
                const value = range(key);
                draftFrom = value.from; draftTo = value.to;
                month = shiftMonth(draftTo, -1);
            }
        }
        if (button.dataset.day) {
            const value = button.dataset.day;
            allDates = false;
            if (!selecting) { draftFrom = value; draftTo = value; selecting = true; }
            else {
                const first = draftFrom;
                draftFrom = value < first ? value : first;
                draftTo = value < first ? first : value;
                selecting = false;
            }
        }
        render();
        const focused = button.dataset.day ? picker.querySelector(`[data-day="${button.dataset.day}"]`) : button.dataset.month ? picker.querySelector(`[data-month="${button.dataset.month}"]`) : picker.querySelector(`[data-preset="${button.dataset.preset}"]`);
        focused?.focus({ preventScroll: true });
    });
}
