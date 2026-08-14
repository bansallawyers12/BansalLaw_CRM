{{-- Shared helper: fetch logged-in staff email signature for compose/reply/forward --}}
@if(auth('admin')->check())
<script>window.__crmCurrentUserSignature = window.__crmCurrentUserSignature || @json(auth('admin')->user()->email_signature ?? '');</script>
@endif
<script>
(function() {
	window.crmNormalizeSignatureHtml = window.crmNormalizeSignatureHtml || function (html) {
		var value = html == null ? '' : String(html);
		var i, textarea, decoded;
		for (i = 0; i < 3; i++) {
			if (!/&lt;\s*(?:table|div|p|html|body|span|img|font|!DOCTYPE|br|strong|b|em|i|a)\b/i.test(value)) {
				break;
			}
			textarea = document.createElement('textarea');
			textarea.innerHTML = value;
			decoded = textarea.value;
			if (decoded === value) {
				break;
			}
			value = decoded;
		}
		return value;
	};

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
				sig = window.crmNormalizeSignatureHtml(sig);
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
				return window.crmNormalizeSignatureHtml(fallback);
			});
	};
})();
</script>
