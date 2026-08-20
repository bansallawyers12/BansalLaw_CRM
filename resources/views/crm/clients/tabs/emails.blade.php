           <!-- Emails Tab -->
           <div class="tab-pane{{ strtolower((string) ($activeTab ?? '')) === 'emails' ? ' active' : '' }}" id="emails-tab">
                @include('crm.emails_outlook', ['compactPagination' => true])
            </div>
