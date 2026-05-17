{{-- Check-in modal: multi-select AJAX recipients + utype field. Shared by crm_client_detail layouts. --}}
            $('.js-data-example-ajax-check').on("select2:select", function(e) {
                var data = e.params.data;
                console.log(data);
                var contactType = data.status || data.type || 'client';
                contactType = contactType.toLowerCase();
                if (contactType === 'lead') {
                    contactType = 'Lead';
                } else if (contactType === 'client') {
                    contactType = 'Client';
                } else {
                    contactType = 'Client';
                }
                $('#utype').val(contactType);
            });

            $('.js-data-example-ajax-check').on("select2:clear", function(e) {
                $('#utype').val('');
            });

            $('.js-data-example-ajax-check').select2({
                multiple: true,
                closeOnSelect: false,
                dropdownParent: $('#checkinmodal'),
                ajax: {
                    url: @json(route('clients.getrecipients')),
                    dataType: 'json',
                    processResults: function (data) {
                        return {
                            results: data.items
                        };
                    },
                    cache: true
                },
                templateResult: formatRepocheck,
                templateSelection: formatRepoSelectioncheck
            });

            function formatRepocheck (repo) {
                if (repo.loading) {
                    return repo.text;
                }

                var $container = $(
                    "<div  class='select2-result-repository ag-flex ag-space-between ag-align-center'>" +

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
                var statClass = (repo.status == 'Archived') ? 'ui label select2-result-repository__statistics' : 'ui label yellow select2-result-repository__statistics';
                $container.find(".select2resultrepositorystatistics").append($('<span class="' + statClass + '"></span>').text(repo.status || ''));
                return $container;
            }

            function formatRepoSelectioncheck (repo) {
                return repo.name || repo.text;
            }
