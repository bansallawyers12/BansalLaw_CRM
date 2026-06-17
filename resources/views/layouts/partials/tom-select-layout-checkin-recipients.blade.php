{{-- Check-in modal: multi-select AJAX recipients + utype field. Shared by crm_client_detail layouts. --}}
            @php
                $crmGetRecipientsUrl = route('clients.getrecipients');
            @endphp

            (function () {
                var recipientsUrl = @json($crmGetRecipientsUrl);

                initTS('.js-data-example-ajax-check', {
                    maxItems: null,
                    plugins: ['remove_button'],
                    valueField: 'id',
                    labelField: 'name',
                    searchField: ['name', 'email'],
                    dropdownParent: '#checkinmodal',
                    loadThrottle: 300,
                    placeholder: 'Search contacts...',
                    shouldLoad: function (q) { return q.length > 0; },
                    load: function (query, callback) {
                        var url = recipientsUrl + (recipientsUrl.indexOf('?') >= 0 ? '&' : '?')
                                + 'q=' + encodeURIComponent(query);
                        fetch(url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin'
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (data) { callback(data.items || []); })
                        .catch(function () { callback(); });
                    },
                    render: {
                        option: function (repo, escape) {
                            var statClass = (repo.status === 'Archived')
                                ? 'ui label crm-ts-result__statistics'
                                : 'ui label yellow crm-ts-result__statistics';
                            return '<div class="ag-flex ag-space-between ag-align-center">'
                                + '<div class="ag-flex ag-align-start">'
                                    + '<div class="ag-flex ag-flex-column col-hr-1">'
                                        + '<div class="ag-flex"><span class="crm-ts-result__title text-semi-bold">'
                                        + escape(repo.name || '')
                                        + '</span>&nbsp;</div>'
                                        + '<div class="ag-flex ag-align-center"><small class="crm-ts-result__description">'
                                        + escape(repo.email || '')
                                        + '</small></div>'
                                    + '</div>'
                                + '</div>'
                                + '<div class="ag-flex ag-flex-column ag-align-end">'
                                    + '<span class="crm-ts-result__statistics-wrap">'
                                    + '<span class="' + statClass + '">' + escape(repo.status || '') + '</span>'
                                    + '</span>'
                                + '</div>'
                                + '</div>';
                        },
                        item: function (repo, escape) {
                            return '<div>' + escape(repo.name || repo.text || '') + '</div>';
                        }
                    },
                    onItemAdd: function (value) {
                        var item = this.options[value];
                        if (!item) { return; }
                        var t = (item.status || 'client').toLowerCase();
                        $('#utype').val(t === 'lead' ? 'Lead' : 'Client');
                    },
                    onItemRemove: function () {
                        if (this.items.length === 0) {
                            $('#utype').val('');
                        }
                    },
                    onClear: function () {
                        $('#utype').val('');
                    }
                });
            }());
