<script>
(function () {
	function toggleZohoSmtpFields() {
		var select = document.querySelector('select[name="mail_provider"]');
		if (!select) return;
		var showZoho = select.value === 'zoho';
		document.querySelectorAll('.zoho-smtp-fields').forEach(function (el) {
			el.style.display = showZoho ? '' : 'none';
		});
	}

	document.addEventListener('DOMContentLoaded', toggleZohoSmtpFields);
	document.addEventListener('change', function (event) {
		if (event.target && event.target.matches('select[name="mail_provider"]')) {
			toggleZohoSmtpFields();
		}
	});
})();
</script>
