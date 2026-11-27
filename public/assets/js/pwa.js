/** Register the service worker (secure pages only) and offer installation. */
(function () {
    // iPhones and iPads have no install prompt: show how to add the app by hand
    const apple = /iphone|ipad|ipod/i.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const installed = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
    const hint = document.getElementById('installHint');
    const installButton = document.getElementById('installApp');
    if (apple && !installed && hint && installButton) {
        installButton.classList.remove('d-none');
        installButton.addEventListener('click', () => hint.classList.toggle('d-none'));
    }

    if (!('serviceWorker' in navigator) || !window.isSecureContext) {
        return;
    }

    const tellLanguage = () => navigator.serviceWorker.ready.then(registration => {
        if (registration.active) {
            registration.active.postMessage({ language: document.documentElement.lang });
        }
    });

    navigator.serviceWorker.register('/service-worker.js').then(tellLanguage).catch(() => null);

    const button = document.getElementById('installApp');
    let prompt = null;
    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        prompt = event;
        if (button) {
            button.classList.remove('d-none');
        }
    });
    window.addEventListener('appinstalled', () => button && button.classList.add('d-none'));

    if (button) {
        button.addEventListener('click', async () => {
            if (!prompt) {
                return;
            }
            prompt.prompt();
            await prompt.userChoice;
            prompt = null;
            button.classList.add('d-none');
        });
    }
})();
