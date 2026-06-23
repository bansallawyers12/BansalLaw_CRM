{{-- Populate Compose Email From dropdown (Zoho staff senders + SendGrid system senders) --}}
<script>
(function() {
	var sendersUrl = '{{ route("crm.sendgrid.senders") }}';
	var staffSignatureUrl = '{{ route("crm.staff.email-signature") }}';

	window.crmFetchStaffSignature = function(fromEmail) {
		fromEmail = (fromEmail || '').trim();
		var url = staffSignatureUrl;
		if (fromEmail) {
			url += (url.indexOf('?') >= 0 ? '&' : '?') + 'from_email=' + encodeURIComponent(fromEmail);
		}
		return fetch(url, {
			headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			credentials: 'same-origin'
		})
			.then(function(r) {
				if (!r.ok) throw new Error('HTTP ' + r.status);
				return r.json();
			})
			.then(function(data) {
				var sig = (data && data.signature) ? String(data.signature) : '';
				if (!fromEmail && sig) {
					window.__crmCurrentUserSignature = sig;
				}
				return sig;
			})
			.catch(function() {
				var fallback = window.__crmCurrentUserSignature || '';
				if (!fallback) {
					var emailModal = document.getElementById('emailmodal');
					if (emailModal) {
						fallback = emailModal.getAttribute('data-staff-signature') || '';
					}
				}
				if (!fallback) {
					var outlookContainer = document.getElementById('outlookContainer');
					if (outlookContainer) {
						fallback = outlookContainer.getAttribute('data-staff-signature') || '';
					}
				}
				return fallback;
			});
	};

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
