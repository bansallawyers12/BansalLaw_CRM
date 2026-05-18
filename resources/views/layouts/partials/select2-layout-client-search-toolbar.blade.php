{{-- Topbar AJAX client search + check-in assignee. Shared by crm_client_detail layouts. --}}
            @php
                $crmClientsDetailPrefix = url('/clients/detail');
                $crmLeadsHistoryPrefix  = url('/leads/history');
                $crmGetAllClientsUrl    = route('clients.getallclients');
            @endphp

            initTS('.assineeselect2', {
                plugins: ['clear_button'],
                dropdownParent: '#checkinmodal',
                create: false
            });

            (function () {
                var clientDetailPrefix = @json($crmClientsDetailPrefix);
                var leadsHistoryPrefix  = @json($crmLeadsHistoryPrefix);
                var allClientsUrl       = @json($crmGetAllClientsUrl);

                initTS('.js-data-example-ajaxccsearch', {
                    valueField: 'id',
                    labelField: 'name',
                    searchField: ['name', 'email'],
                    loadThrottle: 300,
                    preload: false,
                    placeholder: 'Search',
                    dropdownParent: 'body',
                    shouldLoad: function (q) { return q.length > 0; },
                    load: function (query, callback) {
                        var url = allClientsUrl + (allClientsUrl.indexOf('?') >= 0 ? '&' : '?')
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
                                ? 'ui label select2-result-repository__statistics'
                                : 'ui label yellow select2-result-repository__statistics';
                            var badges = '';
                            if (repo.locked) {
                                var ui = repo.access_ui || {};
                                if (ui.show_quick)      { badges += '<span class="ui label tiny">Quick</span> '; }
                                if (ui.show_supervisor) { badges += '<span class="ui label tiny">Supervisor</span> '; }
                            }
                            return '<div class="selectclient ag-flex ag-space-between ag-align-center'
                                + (repo.locked ? ' opacity-75' : '') + '"'
                                + ' data-cid="' + escape((repo.cid || '').toString()) + '">'
                                + '<div class="ag-flex ag-align-start">'
                                    + '<div class="ag-flex ag-flex-column col-hr-1">'
                                        + '<div class="ag-flex"><span class="select2-result-repository__title text-semi-bold">'
                                        + (repo.locked ? '&#128274; ' : '')
                                        + escape(repo.name || '')
                                        + '</span>&nbsp;</div>'
                                        + '<div class="ag-flex ag-align-center"><small class="select2-result-repository__description">'
                                        + escape(repo.email || '')
                                        + '</small></div>'
                                    + '</div>'
                                + '</div>'
                                + '<div class="ag-flex ag-flex-column ag-align-end">'
                                    + '<span class="select2resultrepositorystatistics">'
                                    + badges
                                    + '<span class="' + statClass + '">' + escape(repo.status || '') + '</span>'
                                    + '</span>'
                                + '</div>'
                                + '</div>';
                        },
                        item: function (repo, escape) {
                            return '<div>' + escape(repo.name || repo.text || '') + '</div>';
                        }
                    },
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
                });
            }());
