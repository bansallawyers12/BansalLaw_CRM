{{-- Assignee selects in check-in modal + topbar AJAX client/search. Shared by crm_client_detail layouts. --}}
            @php
                $crmClientsDetailPrefix = url('/clients/detail');
                $crmLeadsHistoryPrefix = url('/leads/history');
            @endphp

            $('.assineeselect2').select2({
                dropdownParent: $('#checkinmodal'),
            });

            $('.js-data-example-ajaxccsearch').select2({
                closeOnSelect: true,
                ajax: {
                    url: @json(route('clients.getallclients')),
                    dataType: 'json',
                    processResults: function (data) {
                        return {
                            results: data.items
                        };
                    },
                    cache: true
                },
                templateResult: formatRepomain,
                templateSelection: formatRepoSelectionmain
            });

            function formatRepomain (repo) {
                if (repo.loading) {
                    return repo.text;
                }

                var $container = $(
                    "<div dataid='" + (repo.cid || '').toString().replace(/'/g, '&#39;').replace(/&/g, '&amp;') + "' class='selectclient select2-result-repository ag-flex ag-space-between ag-align-center'>" +

                    "<div  class='ag-flex ag-align-start'>" +
                        "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='select2-result-repository__title text-semi-bold'></span>\u00A0</div>" +
                        "<div class='ag-flex ag-align-center'><small class='select2-result-repository__description'></small ></div>" +

                    "</div>" +
                    "</div>" +
                    "<div class='ag-flex ag-flex-column ag-align-end'>" +

                        "<span class='select2resultrepositorystatistics'>" +

                        "</span>" +
                    "</div>" +
                    "</div>"
                );

                $container.find(".select2-result-repository__title").text(repo.name);
                $container.find(".select2-result-repository__description").text(repo.email);
                if (repo.locked) {
                    $container.addClass('opacity-75');
                    $container.find(".select2-result-repository__title").prepend('<span class="mr-1" title="No access">&#128274;</span> ');
                    var ui = repo.access_ui || {};
                    if (ui.show_quick) {
                        $container.find(".select2resultrepositorystatistics").append('<span class="ui label tiny">Quick</span> ');
                    }
                    if (ui.show_supervisor) {
                        $container.find(".select2resultrepositorystatistics").append('<span class="ui label tiny">Supervisor</span> ');
                    }
                }
                var statClass = (repo.status == 'Archived') ? 'ui label select2-result-repository__statistics' : 'ui label yellow select2-result-repository__statistics';
                $container.find(".select2resultrepositorystatistics").append($('<span class="' + statClass + '"></span>').text(repo.status || ''));
                return $container;
            }

            function formatRepoSelectionmain (repo) {
                return repo.name || repo.text;
            }

            $('.js-data-example-ajaxccsearch').on('select2:select', function (e) {
                var data = e.params.data || {};
                if (data.locked && typeof window.openCrmAccessModal === 'function') {
                    $(this).val(null).trigger('change');
                    window.openCrmAccessModal(data);
                    return;
                }
                var v = data.id;
                if (!v) { return; }
                var s = String(v).split('/');
                var clientDetailPrefix = @json($crmClientsDetailPrefix);
                var leadsHistoryPrefix = @json($crmLeadsHistoryPrefix);
                if(s[1] == 'Matter' && s[2] != ''){
                    window.location = clientDetailPrefix + '/' + s[0] + '/' + s[2];
                } else {
                    if(s[1] == 'Client'){
                        window.location = clientDetailPrefix + '/' + s[0];
                    }  else{
                        window.location = leadsHistoryPrefix + '/' + s[0];
                    }
                }
            });
