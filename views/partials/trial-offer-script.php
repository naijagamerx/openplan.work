<?php
/**
 * Click handler for any .trial-offer-start button (printed once per page).
 * The button carries data-endpoint, data-csrf and data-after; an optional
 * .trial-offer-error element inside the nearest .trial-offer shows errors.
 */
?>
<?php if (empty($GLOBALS['trialOfferScriptPrinted'])): $GLOBALS['trialOfferScriptPrinted'] = true; ?>
<script>
document.addEventListener('click', async function (event) {
    const btn = event.target.closest('.trial-offer-start');
    if (!btn || btn.disabled) return;
    const box = btn.closest('.trial-offer');
    const errorEl = box ? box.querySelector('.trial-offer-error') : null;
    const label = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Starting…';
    if (errorEl) { errorEl.classList.add('hidden'); errorEl.textContent = ''; }
    try {
        const response = await fetch(btn.dataset.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': btn.dataset.csrf },
            body: JSON.stringify({ csrf_token: btn.dataset.csrf })
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.success) {
            const message = (result.error && result.error.message) || 'The free trial could not be started. Please try again.';
            throw new Error(message);
        }
        btn.textContent = 'Trial started';
        window.location.href = btn.dataset.after;
    } catch (err) {
        btn.disabled = false;
        btn.textContent = label;
        if (errorEl) { errorEl.textContent = err.message; errorEl.classList.remove('hidden'); }
    }
});
</script>
<?php endif; ?>
