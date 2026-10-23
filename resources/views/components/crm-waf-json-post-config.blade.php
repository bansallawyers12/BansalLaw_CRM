@if(\App\Support\CrmWafJsonPost::enabled())
    <script>
        window.CRM_WAF_JSON_POST_ENABLED = true;
        window.CRM_WAF_JSON_POST_MAP = {!! json_encode(\App\Support\CrmWafJsonPost::routeUrlMap(), JSON_UNESCAPED_SLASHES) !!};
    </script>
@endif
@if(\App\Support\CrmWafJsonPost::noteAttachmentsDisabled())
    <script>window.CRM_NOTES_ATTACHMENTS_DISABLED = true;</script>
@endif
