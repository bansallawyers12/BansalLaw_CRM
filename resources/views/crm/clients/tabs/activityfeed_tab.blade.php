<div class="tab-pane{{ strtolower((string) ($activeTab ?? '')) === 'activityfeed' ? ' active' : '' }}" id="activityfeed-tab">
    <section class="cdn-timeline-tab" aria-labelledby="cdn-timeline-heading">
        <header class="cdn-timeline-tab__header">
            <div class="cdn-timeline-tab__title-block">
                <h3 id="cdn-timeline-heading" class="cdn-timeline-tab__title">Timeline</h3>
                <p class="cdn-timeline-tab__subtitle">Everything staff does on this file — notes, emails, documents, tasks, and more.</p>
            </div>
        </header>

        @include('crm.clients.tabs.activity_feed')
    </section>
</div>
