/**
 * Suwoda HerbHub - Client-side Search, Autocomplete & Voice Search
 */
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('globalSearchInput');
    const suggestBox = document.getElementById('searchSuggestBox');
    const voiceBtn = document.getElementById('voiceSearchBtn');
    const micIcon = document.getElementById('voiceMicIcon');

    // 1. Autocomplete with Debounce
    let debounceTimer = null;
    if (searchInput && suggestBox) {
        searchInput.addEventListener('input', function () {
            const query = this.value.trim();
            clearTimeout(debounceTimer);

            if (query.length < 2) {
                suggestBox.classList.add('d-none');
                suggestBox.innerHTML = '';
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
                        let html = '';

                        // Concerns
                        if (d.concerns && d.concerns.length > 0) {
                            html += '<div class="dropdown-header text-uppercase text-muted" style="font-size:0.75rem;"><i class="bi bi-heart-pulse me-1"></i> Health Concerns</div>';
                            d.concerns.forEach(c => {
                                html += `<a class="dropdown-item py-1 text-truncate" href="${c.url}">
                                            <span class="badge bg-success-subtle text-success me-1">Concern</span> ${c.title}
                                         </a>`;
                            });
                        }

                        // Herbs
                        if (d.herbs && d.herbs.length > 0) {
                            html += '<div class="dropdown-header text-uppercase text-muted mt-1" style="font-size:0.75rem;"><i class="bi bi-journal-medical me-1"></i> Herbs</div>';
                            d.herbs.forEach(h => {
                                html += `<a class="dropdown-item py-1" href="${h.url}">
                                            <div class="fw-semibold text-truncate">${h.title}</div>
                                            <small class="text-muted fst-italic">${h.subtitle}</small>
                                         </a>`;
                            });
                        }

                        // Products
                        if (d.products && d.products.length > 0) {
                            html += '<div class="dropdown-header text-uppercase text-muted mt-1" style="font-size:0.75rem;"><i class="bi bi-box-seam me-1"></i> Products</div>';
                            d.products.forEach(p => {
                                html += `<a class="dropdown-item py-1" href="${p.url}">
                                            <div class="fw-semibold text-truncate">${p.title}</div>
                                            <small class="text-success">${p.subtitle}</small>
                                         </a>`;
                            });
                        }

                        if (html === '') {
                            suggestBox.classList.add('d-none');
                        } else {
                            suggestBox.innerHTML = html;
                            suggestBox.classList.remove('d-none');
                        }
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
                searchInput.placeholder = 'Listening... Speak now';
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
            searchInput.placeholder = 'Search herbs, products or health concerns...';
        }
    } else if (voiceBtn) {
        // Hide microphone button when unsupported in browser
        voiceBtn.classList.add('d-none');
    }
});
