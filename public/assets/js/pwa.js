/** Register the service worker (secure pages only) and offer installation. */
(function () {
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
