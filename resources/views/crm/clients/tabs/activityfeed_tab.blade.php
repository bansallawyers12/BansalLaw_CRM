<div class="tab-pane{{ strtolower((string) ($activeTab ?? '')) === 'activityfeed' ? ' active' : '' }}" id="activityfeed-tab">
    <section class="cdn-timeline-tab" aria-labelledby="cdn-timeline-heading">
        <header class="cdn-timeline-tab__header">
            <div class="cdn-timeline-tab__title-block">
                <h3 id="cdn-timeline-heading" class="cdn-timeline-tab__title">
                    <i class="fa-solid fa-timeline" aria-hidden="true"></i>
                    Timeline
                </h3>
                <p class="cdn-timeline-tab__subtitle">Chronological activity, notes, documents, and account events for this record.</p>
            </div>
        </header>

        @include('crm.clients.tabs.activity_feed')
    </section>
</div>
