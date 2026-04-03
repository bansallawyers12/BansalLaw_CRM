// Client Portal tab scripts (renamed from application.js).
// Legacy sales forecast functionality is intentionally disabled (quarantined).

(() => {
    const SALES_FORECAST_DISABLED = true;

    function disableLegacySalesForecastUI() {
        if (!SALES_FORECAST_DISABLED) return;

        const $ = window.jQuery || window.$;
        if (!$) return;

        // Hide legacy modals if present
        $('#application_opensaleforcast').hide();
        $('#application_opensaleforcastservice').hide();

        // Disable legacy trigger buttons/links and mark as legacy
        $('.opensaleforcast, .opensaleforcastservice').each(function(){
            const el = this;
            el.style.pointerEvents = 'none';
            el.setAttribute('aria-disabled', 'true');
            el.classList.add('disabled');
            // Remove inline onclicks that target legacy flows
            try {
                if (el.hasAttribute('onclick')) {
                    const val = el.getAttribute('onclick') || '';
                    if (val.includes('saleforcast')) {
                        el.removeAttribute('onclick');
                    }
                }
            } catch (e) {}

            // Add a subtle legacy indicator
            if (!el.dataset.legacyLabeled) {
                el.dataset.legacyLabeled = 'true';
                el.title = (el.title ? el.title + ' — ' : '') + 'Legacy (disabled)';
                try {
                    const badge = document.createElement('small');
                    badge.textContent = ' (Legacy disabled)';
                    badge.style.opacity = '0.7';
                    badge.style.fontSize = '85%';
                    el.appendChild(badge);
                } catch (_) {}
            }
        });

        // Disable any buttons inside modals that would save legacy forecast
        $('[onclick*="saleforcast"]').each(function(){
            const el = this;
            el.style.pointerEvents = 'none';
            el.setAttribute('aria-disabled', 'true');
            el.classList.add('disabled');
            try { el.removeAttribute('onclick'); } catch (e) {}

            // Label as legacy
            if (!el.dataset.legacyLabeled) {
                el.dataset.legacyLabeled = 'true';
                el.title = (el.title ? el.title + ' — ' : '') + 'Legacy (disabled)';
                try {
                    const badge = document.createElement('small');
                    badge.textContent = ' (Legacy disabled)';
                    badge.style.opacity = '0.7';
                    badge.style.fontSize = '85%';
                    el.appendChild(badge);
                } catch (_) {}
            }
        });
    }

    function initClientPortalTab() {
        disableLegacySalesForecastUI();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initClientPortalTab);
    } else {
        initClientPortalTab();
    }
})();
