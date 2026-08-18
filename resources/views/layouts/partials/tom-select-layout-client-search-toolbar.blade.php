{{-- Topbar AJAX client search + check-in assignee. Shared by crm_client_detail layouts. --}}
            @php
                $crmClientsDetailPrefix = url('/clients/detail');
                $crmLeadsHistoryPrefix  = url('/leads/history');
                $crmGetAllClientsUrl    = route('clients.getallclients');
            @endphp

            initTS('#checkinmodal .crm-ts-assignee', {
                plugins: ['clear_button'],
                dropdownParent: '#checkinmodal',
                create: false,
                allowEmptyOption: true
            });

            (function () {
                var clientDetailPrefix = @json($crmClientsDetailPrefix);
                var leadsHistoryPrefix  = @json($crmLeadsHistoryPrefix);
                var allClientsUrl       = @json($crmGetAllClientsUrl);
                var navigating = false;

                document.querySelectorAll('form.topbar-search').forEach(function (form) {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                    });
                });

                if (typeof buildGetAllClientsTomSelectConfig !== 'function') {
                    return;
                }

                function navigateFromGlobalSearch(value) {
                    if (!value || navigating) {
                        return;
                    }
                    var item = (this && this.options) ? (this.options[value] || this.options[String(value)]) : null;
                    if (item && item.locked && typeof window.openCrmAccessModal === 'function') {
                        if (typeof this.clear === 'function') {
                            this.clear(true);
                        }
                        window.openCrmAccessModal(item);
                        return;
                    }
                    var url = typeof window.buildCrmGlobalSearchUrl === 'function'
                        ? window.buildCrmGlobalSearchUrl(value, item || {}, clientDetailPrefix, leadsHistoryPrefix)
                        : '';
                    if (!url) {
                        return;
                    }
                    navigating = true;
                    window.location.href = url;
                }

                initTS('.js-data-example-ajaxccsearch', buildGetAllClientsTomSelectConfig({
                    url: allClientsUrl,
                    dropdownParent: 'body',
                    dropdownClass: 'ts-dropdown crm-global-search-dropdown',
                    placeholder: 'Search name, email, phone…',
                    loadThrottle: 300,
                    showAccessBadges: true,
                    onItemAdd: navigateFromGlobalSearch,
                    onChange: navigateFromGlobalSearch
                }));
            }());
