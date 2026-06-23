{{-- Populate Compose Email From dropdown (Zoho staff senders + SendGrid system senders) --}}
@include('partials.staff-signature-script')
<script>
(function() {
	var sendersUrl = '{{ route("crm.sendgrid.senders") }}';

	function refreshEmailFromSenders() {
		var selects = document.querySelectorAll('.email-from-sendgrid');
		if (selects.length === 0) return;
		fetch(sendersUrl, {
			headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			credentials: 'same-origin'
		})
			.then(function(r) {
				if (!r.ok) throw new Error('HTTP ' + r.status);
				return r.json();
			})
			.then(function(data) {
				var senders = data.senders || [];
				var defaultFrom = (data.default_from || '').trim();
				window.__crmStaffEmailSignatures = data.signatures_by_email || {};
				window.__crmCurrentUserSignature = data.current_user_signature || '';
				window.__crmDefaultFromEmail = defaultFrom;
				selects.forEach(function(select) {
					select.innerHTML = '<option value="">Select From</option>';
					if (senders.length > 0) {
						senders.forEach(function(s) {
							var opt = document.createElement('option');
							opt.value = s.email || '';
							var provider = s.provider ? (' [' + s.provider + ']') : '';
							opt.textContent = (s.name && s.name !== s.email) ? (s.name + ' <' + s.email + '>' + provider) : ((s.email || '') + provider);
							if (s.email && s.email === defaultFrom) opt.selected = true;
							select.appendChild(opt);
						});
					} else if (defaultFrom) {
						var fallback = document.createElement('option');
						fallback.value = defaultFrom;
						fallback.textContent = defaultFrom;
						fallback.selected = true;
						select.appendChild(fallback);
					} else {
						select.innerHTML = '<option value="">No senders configured — add emails in Admin Console</option>';
					}
				});
			})
			.catch(function() {
				selects.forEach(function(select) {
					select.innerHTML = '<option value="">Unable to load senders</option>';
				});
			});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', refreshEmailFromSenders);
	} else {
		refreshEmailFromSenders();
	}
})();
</script>
