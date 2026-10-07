{{-- Local-only manual UI test suite (tab=ui_tests). Progress stored in browser localStorage. --}}
<div class="ui-test-suite" id="uiManualTestSuite">
    <div class="ui-test-suite__hero">
        <div>
            <h2 class="ui-test-suite__title">Manual UI Test Suite</h2>
            <p class="ui-test-suite__lead">
                Interactive checklist for full-application smoke testing. This tab is available only when <code>APP_ENV=local</code>.
                Automated E2E is not included — you confirm each behaviour manually in the CRM.
            </p>
            @if(empty(config('ui_manual_tests.client_detail_url')))
                <p class="ui-test-suite__hint">
                    <strong>Tip:</strong> Set <code>UI_MANUAL_TEST_CLIENT_DETAIL_URL</code> in <code>.env</code> to a test client detail URL (e.g. ending in <code>/personaldocuments</code>)
                    so “Open page” links work for client tabs.
                </p>
            @endif
            @if(!empty($uiManualTestMeta['lazy_tab_note']))
                <p class="ui-test-suite__hint">{{ $uiManualTestMeta['lazy_tab_note'] }}</p>
            @endif
        </div>
        <div class="ui-test-suite__hero-actions">
            <div class="ui-test-suite__progress-wrap">
                <div class="ui-test-suite__progress-label">
                    Progress: <span id="uiTestProgressText">0%</span>
                    <span class="ui-test-suite__progress-count" id="uiTestProgressCount"></span>
                </div>
                <div class="ui-test-suite__progress-bar" aria-hidden="true">
                    <div class="ui-test-suite__progress-fill" id="uiTestProgressFill" style="width:0%;"></div>
                </div>
            </div>
            <button type="button" class="btn btn-outline" id="uiTestResetBtn" title="Clear all checkmarks">Reset checklist</button>
            <button type="button" class="btn btn-primary" id="uiTestCopyBtn" title="Copy pass/fail summary">Copy report</button>
        </div>
    </div>

    @foreach($uiManualTestSections as $section)
        <section class="ui-test-section" data-section-id="{{ $section['id'] }}">
            <h3 class="ui-test-section__title">{{ $section['title'] }}</h3>
            <div class="ui-test-items">
                @foreach($section['items'] as $item)
                    <article class="ui-test-item {{ !empty($item['critical']) ? 'ui-test-item--critical' : '' }}" data-test-id="{{ $item['id'] }}">
                        <label class="ui-test-item__head">
                            <input type="checkbox" class="ui-test-checkbox" data-test-id="{{ $item['id'] }}">
                            <span class="ui-test-item__title">
                                @if(!empty($item['critical']))
                                    <span class="ui-test-badge">Critical</span>
                                @endif
                                {{ $item['title'] }}
                            </span>
                        </label>
                        <ol class="ui-test-steps">
                            @foreach($item['steps'] as $step)
                                <li>{{ $step }}</li>
                            @endforeach
                        </ol>
                        <div class="ui-test-item__actions">
                            @if(!empty($item['href']))
                                <a href="{{ $item['href'] }}" target="_blank" rel="noopener" class="btn btn-outline ui-test-open-link">Open page ↗</a>
                            @elseif(!empty($item['client_detail_tab']))
                                <span class="ui-test-muted">Configure <code>UI_MANUAL_TEST_CLIENT_DETAIL_URL</code> for quick link.</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach

    <p class="ui-test-footer">
        Full process &amp; patterns: <code>{{ $uiManualTestMeta['doc_path'] ?? 'docs/UI_REGRESSION_TRACKING_AND_TESTING_GUIDE.md' }}</code>
    </p>
</div>

<style>
    .ui-test-suite { display: flex; flex-direction: column; gap: 22px; }
    .ui-test-suite__hero {
        display: flex; flex-wrap: wrap; gap: 20px; justify-content: space-between; align-items: flex-start;
        padding: 18px 20px; border-radius: 12px; border: 1px solid rgba(56, 189, 248, 0.25);
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(14, 21, 35, 0.85));
    }
    .ui-test-suite__title { margin: 0 0 8px; font-size: 18px; font-weight: 700; color: #f1f5f9; }
    .ui-test-suite__lead { margin: 0 0 10px; font-size: 13px; color: #94a3b8; max-width: 720px; line-height: 1.55; }
    .ui-test-suite__hint { margin: 6px 0 0; font-size: 12px; color: #cbd5e1; max-width: 720px; }
    .ui-test-suite__hero-actions { display: flex; flex-direction: column; gap: 10px; min-width: 260px; }
    .ui-test-suite__progress-label { font-size: 12px; color: #94a3b8; font-weight: 600; }
    .ui-test-suite__progress-count { margin-left: 6px; color: #64748b; font-weight: 500; }
    .ui-test-suite__progress-bar { height: 8px; border-radius: 999px; background: #1e293b; overflow: hidden; margin-top: 6px; }
    .ui-test-suite__progress-fill { height: 100%; background: linear-gradient(90deg, #10b981, #38bdf8); transition: width 0.25s ease; }
    .ui-test-section__title { font-size: 14px; font-weight: 700; color: #e2e8f0; margin: 0 0 12px; text-transform: uppercase; letter-spacing: 0.04em; }
    .ui-test-items { display: flex; flex-direction: column; gap: 12px; }
    .ui-test-item {
        background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 14px 16px;
    }
    .ui-test-item--critical { border-color: rgba(251, 191, 36, 0.35); }
    .ui-test-item__head { display: flex; align-items: flex-start; gap: 10px; cursor: pointer; margin-bottom: 8px; }
    .ui-test-checkbox { width: 18px; height: 18px; margin-top: 2px; flex-shrink: 0; accent-color: #38bdf8; }
    .ui-test-item__title { font-size: 14px; font-weight: 600; color: #f8fafc; }
    .ui-test-badge {
        display: inline-block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
        padding: 2px 6px; border-radius: 4px; margin-right: 8px; background: rgba(251, 191, 36, 0.15); color: #fcd34d;
        border: 1px solid rgba(251, 191, 36, 0.35);
    }
    .ui-test-steps { margin: 0 0 10px 28px; padding: 0; font-size: 12.5px; color: #94a3b8; line-height: 1.5; }
    .ui-test-item__actions { margin-left: 28px; }
    .ui-test-open-link { padding: 4px 12px !important; font-size: 12px !important; }
    .ui-test-muted { font-size: 12px; color: #64748b; }
    .ui-test-footer { font-size: 12px; color: #64748b; margin-top: 8px; }
    .ui-test-item.is-done { border-color: rgba(16, 185, 129, 0.35); background: rgba(16, 185, 129, 0.05); }
</style>

<script>
(function () {
    var storageKey = @json($uiManualTestStorageKey);
    var suite = document.getElementById('uiManualTestSuite');
    if (!suite || !storageKey) return;

    function loadState() {
        try {
            return JSON.parse(localStorage.getItem(storageKey) || '{}') || {};
        } catch (e) {
            return {};
        }
    }

    function saveState(state) {
        try {
            localStorage.setItem(storageKey, JSON.stringify(state));
        } catch (e) { /* ignore */ }
    }

    function allCheckboxes() {
        return suite.querySelectorAll('.ui-test-checkbox');
    }

    function updateProgress() {
        var boxes = allCheckboxes();
        var total = boxes.length;
        var done = 0;
        boxes.forEach(function (cb) {
            if (cb.checked) done++;
        });
        var pct = total ? Math.round((done / total) * 100) : 0;
        var fill = document.getElementById('uiTestProgressFill');
        var text = document.getElementById('uiTestProgressText');
        var count = document.getElementById('uiTestProgressCount');
        if (fill) fill.style.width = pct + '%';
        if (text) text.textContent = pct + '%';
        if (count) count.textContent = '(' + done + '/' + total + ')';
    }

    function applyState() {
        var state = loadState();
        allCheckboxes().forEach(function (cb) {
            var id = cb.getAttribute('data-test-id');
            cb.checked = !!state[id];
            var card = cb.closest('.ui-test-item');
            if (card) card.classList.toggle('is-done', cb.checked);
        });
        updateProgress();
    }

    suite.addEventListener('change', function (e) {
        if (!e.target.classList.contains('ui-test-checkbox')) return;
        var state = loadState();
        state[e.target.getAttribute('data-test-id')] = e.target.checked;
        saveState(state);
        var card = e.target.closest('.ui-test-item');
        if (card) card.classList.toggle('is-done', e.target.checked);
        updateProgress();
    });

    var resetBtn = document.getElementById('uiTestResetBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (!confirm('Clear all UI test checkmarks on this browser?')) return;
            localStorage.removeItem(storageKey);
            applyState();
        });
    }

    var copyBtn = document.getElementById('uiTestCopyBtn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var lines = ['Bansal Law CRM — Manual UI Test Report', 'Environment: local', 'Date: ' + new Date().toISOString(), ''];
            suite.querySelectorAll('.ui-test-item').forEach(function (item) {
                var cb = item.querySelector('.ui-test-checkbox');
                var title = item.querySelector('.ui-test-item__title');
                if (!cb || !title) return;
                lines.push((cb.checked ? '[PASS] ' : '[FAIL] ') + title.textContent.trim());
            });
            var body = lines.join('\n');
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(body).then(function () {
                    alert('Report copied to clipboard.');
                }).catch(function () {
                    prompt('Copy report:', body);
                });
            } else {
                prompt('Copy report:', body);
            }
        });
    }

    applyState();
})();
</script>
