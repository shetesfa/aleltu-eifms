/**
 * Aleltu Farm EIFMS - Global Ethiopian Calendar & Live Clock System
 * Provides:
 * 1. Exact Julian Day Number (JDN) based Gregorian <-> Ethiopian date conversions.
 * 2. Automatic dynamic Ethiopian date preview attached to all <input type="date"> fields.
 * 3. Interactive Ethiopian date picker modal for effortless Amharic date selection.
 * 4. Real-time Ethiopian 12-hour clock synchronizer for topbar.
 */

const EthCalendar = (function () {
    const ETHIOPIC_EPOCH = 1723856;

    const MONTHS = [
        '',
        'መስከረም', 'ጥቅምት', 'ህዳር', 'ታህሳስ',
        'ጥር', 'የካቲት', 'መጋቢት', 'ሚያዝያ',
        'ግንቦት', 'ሰኔ', 'ሐምሌ', 'ነሐሴ', 'ጳጉሜን'
    ];

    const WEEKDAYS = [
        'እሁድ', 'ሰኞ', 'ማክሰኞ', 'ረቡዕ', 'ሐሙስ', 'አርብ', 'ቅዳሜ'
    ];

    function gregorianToJdn(year, month, day) {
        const a = Math.floor((14 - month) / 12);
        const y = year + 4800 - a;
        const m = month + 12 * a - 3;
        return day + Math.floor((153 * m + 2) / 5) + 365 * y + Math.floor(y / 4) - Math.floor(y / 100) + Math.floor(y / 400) - 32045;
    }

    function jdnToGregorian(jdn) {
        const l = jdn + 68569;
        const n = Math.floor((4 * l) / 146097);
        const l2 = l - Math.floor((146097 * n + 3) / 4);
        const i = Math.floor((4000 * (l2 + 1)) / 1461001);
        const l3 = l2 - Math.floor((1461 * i) / 4) + 31;
        const j = Math.floor((80 * l3) / 2447);
        const gd = l3 - Math.floor((2447 * j) / 80);
        const l4 = Math.floor(j / 11);
        const gm = j + 2 - 12 * l4;
        const gy = 100 * (n - 49) + i + l4;
        return [gy, gm, gd];
    }

    function fromGregorian(gy, gm, gd) {
        const jdn = gregorianToJdn(gy, gm, gd);
        let r = (jdn - ETHIOPIC_EPOCH) % 1461;
        if (r < 0) r += 1461;
        const n = (r % 365) + 365 * Math.floor(r / 1460);
        const ey = 4 * Math.floor((jdn - ETHIOPIC_EPOCH) / 1461) + Math.floor(r / 365) - Math.floor(r / 1460);
        const em = Math.floor(n / 30) + 1;
        const ed = (n % 30) + 1;
        return [ey, em, ed];
    }

    function toGregorian(ey, em, ed) {
        const jdn = ETHIOPIC_EPOCH + 365 * ey + Math.floor(ey / 4) + 30 * (em - 1) + ed - 1;
        return jdnToGregorian(jdn);
    }

    function formatDateString(isoDateStr) {
        if (!isoDateStr) return '';
        const parts = isoDateStr.split('-');
        if (parts.length !== 3) return '';
        const gy = parseInt(parts[0], 10);
        const gm = parseInt(parts[1], 10);
        const gd = parseInt(parts[2], 10);
        if (isNaN(gy) || isNaN(gm) || isNaN(gd)) return '';

        const [ey, em, ed] = fromGregorian(gy, gm, gd);
        const monthName = MONTHS[em] || '';
        const d = new Date(gy, gm - 1, gd);
        const weekdayName = WEEKDAYS[d.getDay()] || '';

        return `${weekdayName}፣ ${monthName} ${ed} ቀን ${ey} ዓ.ም`;
    }

    function formatTime(dateObj) {
        const h = dateObj.getHours();
        const m = String(dateObj.getMinutes()).padStart(2, '0');
        const s = String(dateObj.getSeconds()).padStart(2, '0');

        let ethHour = (h >= 6) ? (h - 6) : (h + 6);
        if (ethHour === 0) ethHour = 12;

        let period = '';
        if (h >= 6 && h < 12) {
            period = 'ከጠዋቱ';
        } else if (h >= 12 && h < 18) {
            period = 'ከቀኑ';
        } else if (h >= 18 && h < 24) {
            period = 'ከምሽቱ';
        } else {
            period = 'ከሌሊቱ';
        }

        return `${period} ${ethHour}:${m}:${s}`;
    }

    // Interactive Ethiopian Date Picker Modal
    let modalEl = null;
    let activeInput = null;

    function createPickerModal() {
        if (modalEl) return modalEl;

        modalEl = document.createElement('div');
        modalEl.id = 'ethPickerModal';
        modalEl.style.cssText = `
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 99999;
            padding: 16px;
        `;

        modalEl.innerHTML = `
            <div style="background:#fff;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.25);width:100%;max-width:360px;overflow:hidden;font-family:inherit;">
                <div style="background:#2e7d32;color:#fff;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;">
                    <strong style="font-size:15px">📅 የኢትዮጵያ ቀን መምረጫ</strong>
                    <button type="button" id="ethModalClose" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;line-height:1;">&times;</button>
                </div>
                <div style="padding:16px;">
                    <div style="display:flex;gap:8px;margin-bottom:14px;">
                        <div style="flex:1;">
                            <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;">ዓመት</label>
                            <select id="ethSelYear" style="width:100%;padding:6px 8px;border:1px solid #ccc;border-radius:6px;font-size:14px;background:#fff;"></select>
                        </div>
                        <div style="flex:1.2;">
                            <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;">ወር</label>
                            <select id="ethSelMonth" style="width:100%;padding:6px 8px;border:1px solid #ccc;border-radius:6px;font-size:14px;background:#fff;"></select>
                        </div>
                        <div style="flex:0.8;">
                            <label style="display:block;font-size:11px;font-weight:700;color:#555;margin-bottom:4px;">ቀን</label>
                            <select id="ethSelDay" style="width:100%;padding:6px 8px;border:1px solid #ccc;border-radius:6px;font-size:14px;background:#fff;"></select>
                        </div>
                    </div>

                    <div id="ethSelectedDisplay" style="text-align:center;padding:10px;background:#e8f5e9;color:#1b5e20;border-radius:6px;font-weight:700;font-size:14px;margin-bottom:14px;">
                        —
                    </div>

                    <div style="display:flex;gap:8px;justify-content:space-between;">
                        <button type="button" id="ethBtnToday" style="padding:8px 12px;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;cursor:pointer;font-weight:600;">
                            ዛሬ
                        </button>
                        <div style="display:flex;gap:8px;">
                            <button type="button" id="ethBtnCancel" style="padding:8px 12px;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;cursor:pointer;">
                                ሰርዝ
                            </button>
                            <button type="button" id="ethBtnConfirm" style="padding:8px 16px;background:#2e7d32;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer;">
                                ✓ አረጋግጥ
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modalEl);

        // Populate months
        const selMonth = modalEl.querySelector('#ethSelMonth');
        for (let m = 1; m <= 13; m++) {
            const opt = document.createElement('option');
            opt.value = m;
            opt.textContent = `${m}. ${MONTHS[m]}`;
            selMonth.appendChild(opt);
        }

        function updateDaysDropdown() {
            const y = parseInt(modalEl.querySelector('#ethSelYear').value, 10);
            const m = parseInt(modalEl.querySelector('#ethSelMonth').value, 10);
            const selDay = modalEl.querySelector('#ethSelDay');
            const prevVal = parseInt(selDay.value, 10) || 1;
            selDay.innerHTML = '';

            let maxDays = 30;
            if (m === 13) {
                maxDays = (y % 4 === 3) ? 6 : 5;
            }

            for (let d = 1; d <= maxDays; d++) {
                const opt = document.createElement('option');
                opt.value = d;
                opt.textContent = d;
                if (d === Math.min(prevVal, maxDays)) opt.selected = true;
                selDay.appendChild(opt);
            }
            updatePreviewDisplay();
        }

        function updatePreviewDisplay() {
            const y = parseInt(modalEl.querySelector('#ethSelYear').value, 10);
            const m = parseInt(modalEl.querySelector('#ethSelMonth').value, 10);
            const d = parseInt(modalEl.querySelector('#ethSelDay').value, 10);
            const disp = modalEl.querySelector('#ethSelectedDisplay');
            if (isNaN(y) || isNaN(m) || isNaN(d)) return;

            const [gy, gm, gd] = toGregorian(y, m, d);
            const dt = new Date(gy, gm - 1, gd);
            const wName = WEEKDAYS[dt.getDay()] || '';
            disp.textContent = `${wName}፣ ${MONTHS[m]} ${d} ቀን ${y} ዓ.ም`;
        }

        modalEl.querySelector('#ethSelYear').addEventListener('change', updateDaysDropdown);
        modalEl.querySelector('#ethSelMonth').addEventListener('change', updateDaysDropdown);
        modalEl.querySelector('#ethSelDay').addEventListener('change', updatePreviewDisplay);

        function closeModal() {
            modalEl.style.display = 'none';
            activeInput = null;
        }

        modalEl.querySelector('#ethModalClose').addEventListener('click', closeModal);
        modalEl.querySelector('#ethBtnCancel').addEventListener('click', closeModal);
        modalEl.addEventListener('click', (e) => {
            if (e.target === modalEl) closeModal();
        });

        modalEl.querySelector('#ethBtnToday').addEventListener('click', () => {
            const now = new Date();
            const [ey, em, ed] = fromGregorian(now.getFullYear(), now.getMonth() + 1, now.getDate());
            modalEl.querySelector('#ethSelYear').value = ey;
            modalEl.querySelector('#ethSelMonth').value = em;
            updateDaysDropdown();
            modalEl.querySelector('#ethSelDay').value = ed;
            updatePreviewDisplay();
        });

        modalEl.querySelector('#ethBtnConfirm').addEventListener('click', () => {
            if (!activeInput) return;
            const y = parseInt(modalEl.querySelector('#ethSelYear').value, 10);
            const m = parseInt(modalEl.querySelector('#ethSelMonth').value, 10);
            const d = parseInt(modalEl.querySelector('#ethSelDay').value, 10);
            const [gy, gm, gd] = toGregorian(y, m, d);
            const iso = `${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`;
            activeInput.value = iso;
            activeInput.dispatchEvent(new Event('input', { bubbles: true }));
            activeInput.dispatchEvent(new Event('change', { bubbles: true }));
            closeModal();
        });

        return modalEl;
    }

    function openEthPicker(input) {
        activeInput = input;
        const modal = createPickerModal();

        // Determine current date to set
        let curEy, curEm, curEd;
        if (input.value && input.value.split('-').length === 3) {
            const [gy, gm, gd] = input.value.split('-').map(Number);
            [curEy, curEm, curEd] = fromGregorian(gy, gm, gd);
        } else {
            const now = new Date();
            [curEy, curEm, curEd] = fromGregorian(now.getFullYear(), now.getMonth() + 1, now.getDate());
        }

        // Populate years around current
        const selYear = modal.querySelector('#ethSelYear');
        selYear.innerHTML = '';
        const minYear = curEy - 8;
        const maxYear = curEy + 3;
        for (let y = minYear; y <= maxYear; y++) {
            const opt = document.createElement('option');
            opt.value = y;
            opt.textContent = `${y} ዓ.ም`;
            if (y === curEy) opt.selected = true;
            selYear.appendChild(opt);
        }

        modal.querySelector('#ethSelMonth').value = curEm;

        // Populate days
        const selDay = modal.querySelector('#ethSelDay');
        selDay.innerHTML = '';
        let maxDays = 30;
        if (curEm === 13) maxDays = (curEy % 4 === 3) ? 6 : 5;
        for (let d = 1; d <= maxDays; d++) {
            const opt = document.createElement('option');
            opt.value = d;
            opt.textContent = d;
            if (d === curEd) opt.selected = true;
            selDay.appendChild(opt);
        }

        const [gy, gm, gd] = toGregorian(curEy, curEm, curEd);
        const dt = new Date(gy, gm - 1, gd);
        const wName = WEEKDAYS[dt.getDay()] || '';
        modal.querySelector('#ethSelectedDisplay').textContent = `${wName}፣ ${MONTHS[curEm]} ${curEd} ቀን ${curEy} ዓ.ም`;

        modal.style.display = 'flex';
    }

    // Attach dynamic Ethiopian Date Badges and Picker Buttons to all date inputs
    function initDateInputs() {
        const dateInputs = document.querySelectorAll('input[type="date"]');
        dateInputs.forEach(input => {
            if (input.dataset.ethAttached) return;
            input.dataset.ethAttached = '1';

            const container = document.createElement('div');
            container.className = 'eth-date-container';
            container.style.cssText = 'display:flex;align-items:center;gap:6px;margin-top:4px;flex-wrap:wrap;';

            const preview = document.createElement('span');
            preview.className = 'eth-date-preview';
            preview.style.cssText = 'font-size:12px;font-weight:600;color:var(--primary,#2e7d32);';

            const pickBtn = document.createElement('button');
            pickBtn.type = 'button';
            pickBtn.className = 'eth-picker-btn';
            pickBtn.innerHTML = '📅 በኢትዮጵያ ቀን ምረጥ';
            pickBtn.style.cssText = 'padding:2px 7px;font-size:11px;border-radius:4px;border:1px solid #2e7d32;background:#e8f5e9;color:#1b5e20;cursor:pointer;font-weight:600;white-space:nowrap;';
            pickBtn.title = 'የኢትዮጵያ ዘመን አቆጣጠር መምረጫ ይክፈቱ';

            pickBtn.addEventListener('click', (ev) => {
                ev.preventDefault();
                openEthPicker(input);
            });

            container.appendChild(preview);
            container.appendChild(pickBtn);

            function updatePreview() {
                const val = input.value;
                if (val) {
                    const ethStr = formatDateString(val);
                    preview.innerHTML = `<span>የኢትዮጵያ ቀን፦ <strong>${ethStr}</strong></span>`;
                } else {
                    preview.innerHTML = '';
                }
            }

            input.parentNode.insertBefore(container, input.nextSibling);
            input.addEventListener('change', updatePreview);
            input.addEventListener('input', updatePreview);
            updatePreview();
        });
    }

    // Live Header Clock
    function initLiveClock() {
        const clockEl = document.getElementById('ethLiveClock');
        if (!clockEl) return;

        function tick() {
            const now = new Date();
            clockEl.textContent = `🕒 ${formatTime(now)}`;
        }
        setInterval(tick, 1000);
        tick();
    }

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initDateInputs();
            initLiveClock();
        });
    } else {
        initDateInputs();
        initLiveClock();
    }

    return {
        fromGregorian,
        toGregorian,
        formatDateString,
        formatTime,
        initDateInputs,
        openEthPicker,
        MONTHS,
        WEEKDAYS
    };
})();

