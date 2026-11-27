/**
 * Form checks shown in the visitor's language, next to each field. The server
 * checks everything again (src/Core/FormRules.php and the services).
 */
(function () {
    const source = document.getElementById('formMessages');
    if (!source) {
        return;
    }
    const messages = JSON.parse(source.textContent);
    const MAX_FILE = 2 * 1024 * 1024;

    const money = (max, min) => ({ type: 'number', step: '0.01', min: min || '0', max: String(max), required: true });
    const whole = (min, max) => ({ type: 'number', step: '1', min: String(min), max: String(max), required: true });
    const email = { required: true, maxlength: '100', autocomplete: 'email' };
    const newPassword = { required: true, minlength: '8', maxlength: '72', 'data-rule': 'password', autocomplete: 'new-password' };
    const confirmPassword = { required: true, maxlength: '72', 'data-match': 'password', autocomplete: 'new-password' };
    const person = { required: true, minlength: '2', maxlength: '100', 'data-rule': 'name' };
    const phone = { type: 'tel', inputmode: 'tel', maxlength: '16', 'data-rule': 'phone' };
    const required = max => ({ required: true, maxlength: String(max) });
    const optional = max => ({ maxlength: String(max) });

    // [form address, { field name: attributes }]
    const rules = [
        [/^\/(admin\/)?login$/, { email: email, password: { required: true, maxlength: '200' } }],
        [/^\/forgot-password$/, { email: email }],
        [/^\/register$/, { name: person, email: email, phone: phone, password: newPassword, password_confirmation: confirmPassword }],
        [/^\/reset-password$/, { password: newPassword, password_confirmation: confirmPassword }],
        [/^\/profile$/, { name: person, phone: phone, address: optional(1000) }],
        [/^\/profile\/password$/, { current_password: { required: true }, password: newPassword, password_confirmation: confirmPassword }],
        [/^\/checkout$/, {
            shipping_name: person, shipping_phone: Object.assign({ required: true }, phone),
            district: { required: true }, shipping_address: { required: true, minlength: '10', maxlength: '500' },
            coupon_code: { maxlength: '30', pattern: '[A-Za-z0-9_\\-]{3,30}' }
        }],
        [/^\/cart\/(add|update)$/, { quantity: { required: true, step: '1', min: '1' } }],
        [/^\/search$/, { q: optional(100) }],
        [/^\/products\/\d+\/reviews$/, { rating: { required: true }, comment: required(2000), 'photos[]': { 'data-max-files': '3' } }],
        [/^\/admin\/coupons\/create$/, {
            code: { required: true, maxlength: '30', pattern: '[A-Za-z0-9_\\-]{3,30}' }, value: Object.assign(money(1000000, '0.01'), { 'data-rule': 'coupon' }),
            min_order: money(10000000), max_uses: whole(1, 1000000), ends_at: { required: true, 'data-future': '1' }
        }],
        [/^\/admin\/zones\/save$/, { district: { required: true, minlength: '2', maxlength: '100' }, fee: money(100000), free_over: money(10000000) }],
        [/^\/admin\/products\/stock$/, { quantity: whole(0, 100000) }],
        [/^\/admin\/products\/save$/, {
            product_name: required(255), product_name_si: optional(255), category_id: { required: true }, price: money(10000000, '0.01'),
            quantity: whole(0, 100000), description: optional(5000), description_si: optional(5000)
        }],
        [/^\/admin\/categories\/save$/, { name: required(255), name_si: optional(255) }],
        [/^\/admin\/discounts\/save$/, {
            product_id: { required: true }, discount_percentage: money(90, '0.01'),
            start_date: { required: true }, end_date: { required: true, 'data-after': 'start_date' }
        }],
        [/^\/admin\/herbs\/save$/, { name: required(100), name_si: optional(150), scientific_name: required(150), uses: optional(5000), uses_si: optional(5000) }],
        [/^\/admin\/herbs\/topic$/, { name_en: required(150), name_si: required(150) }],
        [/^\/admin\/blogs\/save$/, { title: required(255), title_si: optional(255), content: required(60000), content_si: optional(60000) }],
        [/^\/admin\/blogs\/tag$/, { name_en: required(80), name_si: optional(80) }],
        [/^\/admin\/batches\/save$/, {
            product_id: { required: true }, batch_code: required(60), manufactured_on: { required: true, 'data-not-future': '1' },
            expires_on: { required: true, 'data-not-before': 'manufactured_on' }, units: { step: '1', min: '0', max: '1000000' },
            source_location: optional(150), supplier_name: optional(150), notes: optional(2000)
        }],
        [/^\/admin\/batches\/labels$/, { count: { required: true, step: '1' } }],
        [/^\/admin\/seasonal\/save$/, {
            name_en: required(150), name_si: optional(150), concern_id: { required: true },
            start: { required: true, 'data-rule': 'monthday' }, end: { required: true, 'data-rule': 'monthday' }
        }],
        [/^\/admin\/quiz\/question$/, { text_en: required(255), text_si: optional(255), sort_order: { step: '1', min: '0', max: '1000' } }],
        [/^\/admin\/quiz\/option$/, { text_en: required(255), text_si: optional(255), vata: whole(0, 5), pitta: whole(0, 5), kapha: whole(0, 5) }],
        [/^\/admin\/quiz\/profile$/, { description_en: optional(5000), description_si: optional(5000), tips_en: optional(5000), tips_si: optional(5000) }],
        [/^\/admin\/safety\/flag$/, { code: { maxlength: '50', pattern: '[a-z0-9_]{2,50}' }, name_en: required(150), name_si: optional(150) }],
        [/^\/admin\/safety\/entry$/, { herb_id: { required: true }, flag_id: { required: true }, note_en: optional(2000), note_si: optional(2000), source_note: optional(500) }],
        [/^\/admin\/assistant\/keyword$/, { keyword: { required: true, minlength: '2', maxlength: '100' } }],
        [/^\/admin\/orders\/status$/, { note: optional(255) }],
        [/^\/admin\/(orders|reports)$/, { to: { 'data-not-before': 'from' } }]
    ];

    const address = form => new URL(form.getAttribute('action') || location.pathname, location.origin).pathname;
    const field = (form, name) => form.elements.namedItem(name);

    function prepare(form) {
        if (form.dataset.noValidate !== undefined || new URL(form.action || location.href).origin !== location.origin) {
            return;
        }
        for (const [pattern, fields] of rules) {
            if (!pattern.test(address(form))) {
                continue;
            }
            for (const [name, attributes] of Object.entries(fields)) {
                form.querySelectorAll(`[name="${name}"]`).forEach(input => {
                    for (const [key, value] of Object.entries(attributes)) {
                        if (key === 'type' && input.tagName !== 'INPUT') {
                            continue;
                        }
                        if (value === true) {
                            input.setAttribute(key, '');
                        } else {
                            input.setAttribute(key, value);
                        }
                    }
                });
            }
        }
        // our messages replace the browser's own bubbles
        form.noValidate = true;
        form.dataset.checked = '';
    }

    const text = (key, values) => Object.entries(values || {}).reduce((s, [k, v]) => s.replace(':' + k, v), messages[key]);

    /** The problem with one field, or an empty string. */
    function problem(input) {
        if (input.disabled || ['hidden', 'submit', 'button'].includes(input.type)) {
            return '';
        }
        const value = (input.value || '').trim();
        const state = input.validity;

        if (input.required && (input.type === 'checkbox' ? !input.checked : input.type === 'radio' ? state.valueMissing : input.type === 'file' ? !input.files.length : value === '')) {
            return text(input.type === 'checkbox' ? 'tick' : (input.type === 'radio' || input.tagName === 'SELECT') ? 'choose' : 'required');
        }
        if (input.type === 'file') {
            const files = [...input.files];
            const allowed = (input.accept || '').split(',').map(a => a.trim().toLowerCase()).filter(a => a.startsWith('.'));
            if (input.dataset.maxFiles && files.length > Number(input.dataset.maxFiles)) {
                return text('files', { max: input.dataset.maxFiles });
            }
            if (allowed.length && files.some(f => !allowed.some(a => f.name.toLowerCase().endsWith(a)))) {
                return text('filetype', { types: allowed.join(', ') });
            }
            return files.some(f => f.size > MAX_FILE) ? text('filesize') : '';
        }
        if (state.badInput) {
            return text(input.type === 'number' ? 'number' : 'date');
        }
        if (value === '') {
            return '';
        }
        if (input.type === 'email' && (state.typeMismatch || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(value))) {
            return text('email');
        }
        // the browser reports length only after typing, so check it here as well
        const min = Number(input.getAttribute('minlength') || 0);
        if (min && value.length < min) {
            return text('short', { min: min });
        }
        const max = Number(input.getAttribute('maxlength') || 0);
        if (max && value.length > max) {
            return text('long', { max: max });
        }
        if (input.type === 'number') {
            if (state.rangeUnderflow) {
                return text('min', { min: input.min });
            }
            if (state.rangeOverflow) {
                return text('max', { max: input.max });
            }
            if (state.stepMismatch) {
                return text(input.step === '1' || input.step === '' ? 'whole' : 'number');
            }
        }
        if (state.patternMismatch) {
            return text('pattern');
        }
        switch (input.dataset.rule) {
            case 'name':
                if (!/^\p{L}[\p{L}\p{M}‌‍ .'’-]*$/u.test(value)) {
                    return text('name');
                }
                break;
            case 'phone':
                if (!/^0[1-9]\d{8}$/.test(value.replace(/[\s()-]/g, '').replace(/^(\+94|0094|94)(?=\d{9}$)/, '0'))) {
                    return text('phone');
                }
                break;
            case 'password':
                if (!/\p{L}/u.test(value) || !/\d/.test(value)) {
                    return text('password');
                }
                break;
            case 'monthday': {
                const m = /^(\d{2})-(\d{2})$/.exec(value);
                const day = m && new Date(2024, m[1] - 1, m[2]);
                if (!day || day.getMonth() !== m[1] - 1) {
                    return text('monthday');
                }
                break;
            }
            case 'coupon': {
                const type = field(input.form, 'type');
                if (type && type.value === 'percent' && Number(value) > 90) {
                    return text('max', { max: 90 });
                }
                break;
            }
        }
        const other = name => (field(input.form, name) || {}).value || '';
        if (input.dataset.match && input.value !== other(input.dataset.match)) {
            return text('match');
        }
        if (input.dataset.future && new Date(value) <= new Date()) {
            return text('future');
        }
        if (input.dataset.notFuture && new Date(value + 'T00:00') > new Date()) {
            return text('notfuture');
        }
        if (input.dataset.after && other(input.dataset.after) && value <= other(input.dataset.after)) {
            return text('after');
        }
        if (input.dataset.notBefore && other(input.dataset.notBefore) && value < other(input.dataset.notBefore)) {
            return text('notbefore');
        }
        return '';
    }

    let counter = 0;

    function show(input) {
        const message = problem(input);
        let note = input.formErrorNote;
        if (!message) {
            input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');
            if (note) {
                note.remove();
                input.formErrorNote = null;
                input.removeAttribute('aria-describedby');
            }
            return true;
        }
        if (!note) {
            note = document.createElement('div');
            note.className = 'field-error';
            note.id = 'field-error-' + (++counter);
            note.setAttribute('role', 'alert');
            // below the whole group for grouped controls, tick boxes and radios
            const anchor = input.closest('.input-group') || (input.type === 'radio' ? input.closest('fieldset, .card') : null) || input.closest('.form-check') || input;
            anchor.insertAdjacentElement('afterend', note);
            input.formErrorNote = note;
            input.setAttribute('aria-describedby', note.id);
        }
        note.textContent = message;
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
        return false;
    }

    function checkAll(form) {
        const seen = new Set();
        const bad = [...form.elements].filter(input => {
            if (input.type === 'radio') {
                if (seen.has(input.name)) {
                    return false;
                }
                seen.add(input.name);
            }
            return input.willValidate !== false && !show(input);
        });
        if (bad.length) {
            const details = bad[0].closest('details');
            if (details) {
                details.open = true;
            }
            bad[0].focus();
        }
        return !bad.length;
    }

    document.querySelectorAll('form').forEach(prepare);

    // capture: runs before the page's own submit handlers
    document.addEventListener('submit', event => {
        const form = event.target;
        if (form.dataset.checked === undefined) {
            return;
        }
        if (form.dataset.sending !== undefined || !checkAll(form)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    // stop double orders and double saves
    document.addEventListener('submit', event => {
        const form = event.target;
        if (form.dataset.checked === undefined || form.method !== 'post' || form.target === '_blank') {
            return;
        }
        setTimeout(() => {
            if (!event.defaultPrevented) {
                form.dataset.sending = '';
                form.querySelectorAll('[type="submit"]').forEach(b => b.setAttribute('aria-disabled', 'true'));
            }
        }, 0);
    });
    // returning with the Back button must leave forms usable
    window.addEventListener('pageshow', () => document.querySelectorAll('form[data-sending]').forEach(form => {
        delete form.dataset.sending;
        form.querySelectorAll('[aria-disabled]').forEach(b => b.removeAttribute('aria-disabled'));
    }));

    ['change', 'focusout'].forEach(type => document.addEventListener(type, event => {
        const input = event.target;
        if (input.form && input.form.dataset.checked !== undefined && (type === 'change' || input.value !== '')) {
            show(input);
        }
    }));
    document.addEventListener('input', event => {
        const input = event.target;
        if (input.classList && input.classList.contains('is-invalid')) {
            show(input);
        }
    });
})();
