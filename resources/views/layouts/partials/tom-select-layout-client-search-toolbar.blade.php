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

                if (typeof buildGetAllClientsTomSelectConfig !== 'function') {
                    return;
                }

                initTS('.js-data-example-ajaxccsearch', buildGetAllClientsTomSelectConfig({
                    url: allClientsUrl,
                    dropdownParent: 'body',
                    placeholder: 'Search name, email, phone…',
                    loadThrottle: 300,
                    showAccessBadges: true,
                    onChange: function (value) {
                        if (!value) { return; }
                        var item = this.options[value];
                        if (!item) { return; }
                        if (item.locked && typeof window.openCrmAccessModal === 'function') {
                            this.clear(true);
                            window.openCrmAccessModal(item);
                            return;
                        }
                        var s = String(value).split('/');
                        if (s[1] === 'Matter' && s[2]) {
                            window.location = clientDetailPrefix + '/' + s[0] + '/' + s[2];
                        } else if (s[1] === 'Client') {
                            window.location = clientDetailPrefix + '/' + s[0];
                        } else {
                            window.location = leadsHistoryPrefix + '/' + s[0];
                        }
                    }
                }));
            }());
