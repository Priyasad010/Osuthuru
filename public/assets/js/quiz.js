/**
 * Dosha quiz: submit with fetch and draw the result chart.
 * Both work without this script: the form posts normally and percentages are listed as text.
 */
(function () {
    const form = document.getElementById('quizForm');
    if (form) {
        const errorBox = document.getElementById('quizError');

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            fetch('/api/quiz/submit', { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' } })
                .then(response => response.json())
                .then(json => {
                    if (json.ok) {
                        window.location.href = json.data.redirect;
                        return;
                    }
                    errorBox.textContent = json.error;
                    errorBox.classList.remove('d-none');
                    errorBox.scrollIntoView({ block: 'center' });
                })
                // Fall back to a normal form post
                .catch(() => form.submit());
        });
    }

    const canvas = document.getElementById('doshaChart');
    if (canvas && window.Chart) {
        new window.Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: JSON.parse(canvas.dataset.labels),
                datasets: [{
                    data: JSON.parse(canvas.dataset.values),
                    backgroundColor: ['#5b7fa6', '#d9822b', '#2d6a4f']
                }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });
    }
})();
