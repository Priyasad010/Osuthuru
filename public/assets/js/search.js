/**
 * Suwoda HerbHub - Client-side Search, Autocomplete & Voice Search
 */
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('globalSearchInput');
    const suggestBox = document.getElementById('searchSuggestBox');
    const voiceBtn = document.getElementById('voiceSearchBtn');
    const micIcon = document.getElementById('voiceMicIcon');

    // Translated labels are provided by the page through data attributes
    const labels = suggestBox ? suggestBox.dataset : {};
    const listeningText = voiceBtn ? (voiceBtn.dataset.listening || '') : '';
    const defaultPlaceholder = searchInput ? searchInput.placeholder : '';

    function element(tag, className, text) {
        const node = document.createElement(tag);
        node.className = className;
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function link(url, className) {
        const a = element('a', className);
        // Only same-site paths are allowed
        a.setAttribute('href', /^\/(?!\/)/.test(url) ? url : '#');
        return a;
    }

    function concernItem(c) {
        const a = link(c.url, 'dropdown-item py-1 text-truncate');
        a.append(
            element('span', 'badge bg-success-subtle text-success me-1', labels.concern || ''),
            document.createTextNode(c.title)
        );
        return a;
    }

    function herbItem(h) {
        const a = link(h.url, 'dropdown-item py-1');
        a.append(
            element('div', 'fw-semibold text-truncate', h.title),
            element('small', 'text-muted fst-italic', h.subtitle)
        );
        return a;
    }

    function productItem(p) {
        const a = link(p.url, 'dropdown-item py-1');
        a.append(
            element('div', 'fw-semibold text-truncate', p.title),
            element('small', 'text-success', p.subtitle)
        );
        return a;
    }

    function addGroup(labelKey, icon, items, build) {
        if (!items || items.length === 0) {
            return;
        }
        const header = element('div', 'dropdown-header text-uppercase text-muted mt-1 small');
        header.append(element('i', 'bi ' + icon + ' me-1'), document.createTextNode(labels[labelKey] || ''));
        suggestBox.append(header);
        items.forEach(item => suggestBox.append(build(item)));
    }

    // 1. Autocomplete with Debounce
    let debounceTimer = null;
    if (searchInput && suggestBox) {
        searchInput.addEventListener('input', function () {
            const query = this.value.trim();
            clearTimeout(debounceTimer);

            if (query.length < 2) {
                suggestBox.classList.add('d-none');
                suggestBox.textContent = '';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch('/api/search/suggest?q=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => {
                        if (!data.ok || !data.data) {
                            suggestBox.classList.add('d-none');
                            return;
                        }

                        const d = data.data;
                        suggestBox.textContent = '';

                        addGroup('concerns', 'bi-heart-pulse', d.concerns, concernItem);
                        addGroup('herbs', 'bi-journal-medical', d.herbs, herbItem);
                        addGroup('products', 'bi-box-seam', d.products, productItem);

                        suggestBox.classList.toggle('d-none', suggestBox.childElementCount === 0);
                    })
                    .catch(() => {
                        suggestBox.classList.add('d-none');
                    });
            }, 180); // 180ms debounce
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !suggestBox.contains(e.target)) {
                suggestBox.classList.add('d-none');
            }
        });
    }

    // 2. Voice Search via Browser Web Speech API
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRecognition && voiceBtn) {
        const recognition = new SpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = false;

        // Choose language based on document lang attribute
        const htmlLang = document.documentElement.lang || 'en';
        recognition.lang = (htmlLang === 'si') ? 'si-LK' : 'en-US';

        let isListening = false;

        voiceBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (isListening) {
                recognition.stop();
                return;
            }

            try {
                recognition.start();
                isListening = true;
                micIcon.classList.remove('bi-mic-fill');
                micIcon.classList.add('bi-mic-mute-fill', 'text-danger');
                searchInput.placeholder = listeningText;
            } catch (err) {
                console.warn('Speech recognition error:', err);
            }
        });

        recognition.onresult = function (event) {
            const transcript = event.results[0][0].transcript;
            if (transcript && searchInput) {
                searchInput.value = transcript;
                searchInput.form.submit();
            }
        };

        recognition.onerror = function () {
            resetMicUI();
        };

        recognition.onend = function () {
            resetMicUI();
        };

        function resetMicUI() {
            isListening = false;
            micIcon.classList.add('bi-mic-fill');
            micIcon.classList.remove('bi-mic-mute-fill', 'text-danger');
            searchInput.placeholder = defaultPlaceholder;
        }
    } else if (voiceBtn) {
        // Hide microphone button when unsupported in browser
        voiceBtn.classList.add('d-none');
    }
});
