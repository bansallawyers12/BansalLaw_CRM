           <!-- Emails Tab -->
           <div class="tab-pane{{ strtolower((string) ($activeTab ?? '')) === 'emails' ? ' active' : '' }}" id="emails-tab">
                @php
                    $cdnEmailRecordIsLead = (($fetchedData->type ?? '') === 'lead')
                        || ($fetchedData->type ?? null) === 1
                        || in_array(strtolower(trim((string) ($fetchedData->type ?? ''))), ['lead', 'l', '1'], true);
                    $cdnHasLeadMailHistory = ! $cdnEmailRecordIsLead
                        && \App\Models\EmailLog::query()
                            ->where('client_id', (int) ($fetchedData->id ?? 0))
                            ->where(function ($q) {
                                $q->where('type', 'lead')
                                    ->orWhereNull('client_matter_id')
                                    ->orWhere('client_matter_id', 0);
                            })
                            ->exists();
                @endphp
                @include('crm.emails_outlook', [
                    'compactPagination' => true,
                    'recordType' => $cdnEmailRecordIsLead ? 'lead' : 'client',
                    'leadScopedMail' => $cdnEmailRecordIsLead,
                    'hasLeadMailHistory' => $cdnHasLeadMailHistory,
                ])
            </div>
