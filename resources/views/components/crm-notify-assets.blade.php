{{-- Toastify CSS for CRM toast notifications (crmNotify / crmToast / crmAlert) --}}
<link rel="stylesheet" type="text/css" href="{{ asset('css/toastify.css') }}">
<script>
    // Early stub so inline scripts can call crmAlert before footer scripts load.
    window.crmAlert = window.crmAlert || function (message) {
        window.__crmAlertQueue = window.__crmAlertQueue || [];
        window.__crmAlertQueue.push(message == null ? '' : String(message));
    };
</script>
