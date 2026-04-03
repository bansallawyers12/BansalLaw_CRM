// Client detail page scripts will be progressively migrated here.

(() => {
    // Placeholder init to be expanded during migration
    function initClientDetailPage() {
        // Quarantine legacy Sales Forecast without touching Blade markup
        const $ = window.jQuery || window.$;
        if (!$) return;

        // Soft-disable any remaining inline triggers if present on this page
        $('[onclick*="saleforcast"]').each(function(){
            try { this.removeAttribute('onclick'); } catch (e) {}
            this.classList.add('disabled');
            this.setAttribute('aria-disabled', 'true');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initClientDetailPage);
    } else {
        initClientDetailPage();
    }
})();


