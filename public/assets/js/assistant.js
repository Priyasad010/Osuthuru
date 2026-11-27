/**
 * Floating shop assistant. All server and model text is inserted with textContent.
 */
(function () {
    const toggle = document.getElementById('assistantToggle');
    const panel = document.getElementById('assistantPanel');
    if (!toggle || !panel) {
        return;
    }

    const closeButton = document.getElementById('assistantClose');
    const log = document.getElementById('assistantLog');
    const form = document.getElementById('assistantForm');
    const input = document.getElementById('assistantInput');
    const token = document.querySelector('meta[name="csrf-token"]');
    let busy = false;

    function element(tag, className, text) {
        const node = document.createElement(tag);
        node.className = className;
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    // Only same-site paths are allowed in links and images
    function localPath(value) {
        return typeof value === 'string' && /^\/(?!\/)/.test(value) ? value : null;
    }

    function link(url, className, text) {
        const a = element('a', className, text);
        a.setAttribute('href', localPath(url) || '#');
        return a;
    }

    function add(node) {
        log.append(node);
        node.scrollIntoView({ block: 'nearest' });
    }

    function card(product) {
        const a = link(product.url, 'assistant-card');
        let picture;
        if (localPath(product.image)) {
            picture = element('img', 'assistant-card-image');
            picture.setAttribute('src', product.image);
            picture.setAttribute('alt', '');
        } else {
            picture = element('span', 'assistant-card-image');
            picture.append(element('i', 'bi bi-flower1'));
        }
        const text = element('span', 'assistant-card-text');
        text.append(element('span', 'fw-semibold', product.name), element('span', 'text-success', product.price));
        a.append(picture, text);
        return a;
    }

    function showAnswer(data) {
        const box = element('div', 'assistant-msg assistant-bot');
        box.append(element('div', 'assistant-reply', data.reply));
        if (data.doctor_note) {
            box.append(element('div', 'alert alert-danger py-1 px-2 mt-2 mb-0 small', data.doctor_note));
        }
        data.products.forEach(p => box.append(card(p)));

        const links = data.concerns.concat(data.herbs);
        if (links.length > 0) {
            const row = element('div', 'd-flex flex-wrap gap-1 mt-2');
            links.forEach(item => row.append(link(item.url, 'badge text-bg-light border text-success text-decoration-none', item.name)));
            box.append(row);
        }
        box.append(element('div', 'assistant-disclaimer', data.disclaimer));
        add(box);
    }

    function open(show) {
        panel.classList.toggle('d-none', !show);
        toggle.setAttribute('aria-expanded', show ? 'true' : 'false');
        (show ? input : toggle).focus();
    }

    toggle.addEventListener('click', () => open(panel.classList.contains('d-none')));
    closeButton.addEventListener('click', () => open(false));
    panel.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            open(false);
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const message = input.value.trim();
        if (!message || busy) {
            return;
        }
        busy = true;
        add(element('div', 'assistant-msg assistant-user', message));
        input.value = '';
        const waiting = element('div', 'assistant-msg assistant-bot text-muted', panel.dataset.thinking);
        add(waiting);

        const body = new FormData();
        body.append('message', message);

        fetch('/api/assistant', {
            method: 'POST',
            body: body,
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': token ? token.content : '' }
        })
            .then(response => response.json())
            .then(json => {
                waiting.remove();
                if (json.ok) {
                    showAnswer(json.data);
                } else {
                    add(element('div', 'assistant-msg assistant-bot text-danger', json.error));
                }
            })
            .catch(() => {
                waiting.remove();
                add(element('div', 'assistant-msg assistant-bot text-danger', panel.dataset.error));
            })
            .finally(() => { busy = false; });
    });
})();
