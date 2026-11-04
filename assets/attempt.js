// Cached pages share server-rendered HTML. Allocate a key per form instance.
document.addEventListener('submit', function (event) {
    const input = event.target.querySelector('input[name="submission_token"]');
    if (input && !input.dataset.initialized && window.crypto && crypto.randomUUID) {
        input.value = crypto.randomUUID();
        input.dataset.initialized = '1';
    }
}, true);
window.addEventListener('pageshow', function (event) {
    if (event.persisted) document.querySelectorAll('input[name="submission_token"]').forEach(function (input) {
        delete input.dataset.initialized;
    });
});
