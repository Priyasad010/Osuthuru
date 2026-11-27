/** Home page herb shelf: show the plant that was picked. */
(function () {
    const picks = document.querySelectorAll('[data-shelf-pick]');
    picks.forEach(button => button.addEventListener('click', () => {
        const chosen = button.dataset.shelfPick;
        picks.forEach(b => b.setAttribute('aria-pressed', String(b === button)));
        document.querySelectorAll('[data-shelf-panel]').forEach(panel => {
            panel.hidden = panel.dataset.shelfPanel !== chosen;
        });
    }));
})();
