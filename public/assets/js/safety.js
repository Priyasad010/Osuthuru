/**
 * Safety checker panel.
 * Answers are sent to the session only. They are written to this browser's
 * localStorage solely when the customer switches on "remember on this device".
 */
(function () {
    const form = document.getElementById('safetyForm');
    if (!form) {
        return;
    }

    const STORAGE_KEY = 'osuthuru_safety_flags';
    const results = document.getElementById('safetyResults');
    const remember = document.getElementById('safetyRemember');
    const clearButton = document.getElementById('safetyClear');
    const boxes = Array.from(form.querySelectorAll('input[name="flags[]"]'));
    const severityClass = { avoid: 'alert-danger', caution: 'alert-warning', info: 'alert-info' };

    // Storage can be unavailable (for example in private browsing)
    function readStored() {
        try {
            const value = JSON.parse(localStorage.getItem(STORAGE_KEY));
            return Array.isArray(value) ? value : null;
        } catch (e) {
            return null;
        }
    }

    function writeStored() {
        try {
            if (remember.checked) {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(boxes.filter(b => b.checked).map(b => b.value)));
            } else {
                localStorage.removeItem(STORAGE_KEY);
            }
        } catch (e) {
            // Nothing to do: the answers simply are not remembered
        }
    }

    function line(className, text) {
        const node = document.createElement('div');
        node.className = className;
        node.textContent = text;
        return node;
    }

    function render(data) {
        results.textContent = '';
        if (data.selected === 0) {
            results.append(line('small text-muted mb-2', form.dataset.nothing));
        } else if (data.warnings.length === 0) {
            results.append(line('small mb-2', form.dataset.none));
        }
        data.warnings.forEach(w => {
            const box = line('alert py-2 small mb-2 ' + (severityClass[w.severity] || 'alert-info'), '');
            const title = document.createElement('strong');
            title.textContent = w.severity_label;
            box.append(
                title,
                document.createTextNode(' · ' + w.product_name + ' (' + w.herb_name + ') · ' + w.flag_name),
                line('', w.note)
            );
            results.append(box);
        });
        if (data.unknown.length > 0) {
            const names = data.unknown.map(u => u.product_name).join(', ');
            results.append(line('alert alert-secondary py-2 small mb-0', form.dataset.unknown + ' ' + names));
        }
    }

    function check() {
        const body = new FormData(form);
        fetch('/api/safety/check', { method: 'POST', body: body, headers: { 'Accept': 'application/json' } })
            .then(response => response.json())
            .then(json => {
                if (!json.ok) {
                    throw new Error(json.error);
                }
                render(json.data);
            })
            .catch(() => {
                results.textContent = '';
                results.append(line('alert alert-danger py-2 small mb-0', form.dataset.error));
            });
    }

    const stored = readStored();
    remember.checked = stored !== null;
    // Restore remembered answers only when this session has none
    if (stored !== null && results.childElementCount === 0) {
        boxes.forEach(b => { b.checked = stored.includes(b.value); });
        if (form.dataset.ajax) {
            check();
        }
    }

    remember.addEventListener('change', writeStored);

    form.addEventListener('submit', function (event) {
        if (event.submitter === clearButton) {
            remember.checked = false;
            writeStored();
            return;
        }
        writeStored();
        if (form.dataset.ajax) {
            event.preventDefault();
            check();
        }
    });
})();
