{{-- Toastify + iziToast shim + crmNotify facade (replaces native alert via crmAlert) --}}
<script type="text/javascript" src="{{ asset('js/toastify.js') }}"></script>
<script>
    if (typeof window.iziToast === 'undefined') {
        window.iziToast = {
            success: function(opts) {
                Toastify({
                    text: (opts.title ? opts.title + " - " : "") + (opts.message || ""),
                    duration: opts.timeout || 3000,
                    close: true,
                    gravity: opts.position && opts.position.toLowerCase().includes('bottom') ? 'bottom' : 'top',
                    position: opts.position && opts.position.toLowerCase().includes('left') ? 'left' : (opts.position && opts.position.toLowerCase().includes('center') ? 'center' : 'right'),
                    style: { background: "linear-gradient(to right, #00b09b, #96c93d)" }
                }).showToast();
            },
            error: function(opts) {
                Toastify({
                    text: (opts.title ? opts.title + " - " : "") + (opts.message || ""),
                    duration: opts.timeout || 5000,
                    close: true,
                    gravity: opts.position && opts.position.toLowerCase().includes('bottom') ? 'bottom' : 'top',
                    position: opts.position && opts.position.toLowerCase().includes('left') ? 'left' : (opts.position && opts.position.toLowerCase().includes('center') ? 'center' : 'right'),
                    style: { background: "linear-gradient(to right, #ff5f6d, #ffc371)" }
                }).showToast();
            },
            warning: function(opts) {
                Toastify({
                    text: (opts.title ? opts.title + " - " : "") + (opts.message || ""),
                    duration: opts.timeout || 4000,
                    close: true,
                    gravity: opts.position && opts.position.toLowerCase().includes('bottom') ? 'bottom' : 'top',
                    position: opts.position && opts.position.toLowerCase().includes('left') ? 'left' : (opts.position && opts.position.toLowerCase().includes('center') ? 'center' : 'right'),
                    style: { background: "linear-gradient(to right, #f6d365, #fda085)" }
                }).showToast();
            },
            info: function(opts) {
                Toastify({
                    text: (opts.title ? opts.title + " - " : "") + (opts.message || ""),
                    duration: opts.timeout || 3000,
                    close: true,
                    gravity: opts.position && opts.position.toLowerCase().includes('bottom') ? 'bottom' : 'top',
                    position: opts.position && opts.position.toLowerCase().includes('left') ? 'left' : (opts.position && opts.position.toLowerCase().includes('center') ? 'center' : 'right'),
                    style: { background: "linear-gradient(to right, #36d1dc, #5b86e5)" }
                }).showToast();
            },
            show: function(opts) {
                Toastify({
                    text: (opts.title ? opts.title + " - " : "") + (opts.message || ""),
                    duration: opts.timeout || 3000,
                    close: true,
                    gravity: opts.position && opts.position.toLowerCase().includes('bottom') ? 'bottom' : 'top',
                    position: opts.position && opts.position.toLowerCase().includes('left') ? 'left' : (opts.position && opts.position.toLowerCase().includes('center') ? 'center' : 'right'),
                    style: { background: opts.color || "#333" }
                }).showToast();
            }
        };
    }
</script>
<script src="{{ asset('js/crm-notify.js') }}?v={{ @filemtime(public_path('js/crm-notify.js')) ?: time() }}"></script>
