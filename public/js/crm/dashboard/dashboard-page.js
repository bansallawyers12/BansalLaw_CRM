/**
 * Dashboard page shell (AJAX).
 * Owns: KPI/summary refresh, My Tasks + Recent Activity infinite scroll,
 * todo filters, toast/loading helpers, refresh button / Alt+R.
 * Does NOT own: task complete/extend/detail panel (see public/js/dashboard.js).
 */
(function () {
    'use strict';

    function cfg() {
        return window.dashboardRoutes || {};
    }

    /** Avoid toast spam when scroll keeps retrying a failed load. */
    var lastLoadMoreToastAt = 0;
    function toastLoadMoreFailure(message) {
        var now = Date.now();
        if (now - lastLoadMoreToastAt < 5000) {
            return;
        }
        lastLoadMoreToastAt = now;
        if (typeof window.showToast === 'function') {
            window.showToast(message, 'error');
        }
    }

    function formatCount(n) {
        var num = Number(n);
        if (!Number.isFinite(num)) {
            return '0';
        }
        try {
            return num.toLocaleString();
        } catch (e) {
            return String(num);
        }
    }

    window.showToast = function (message, type) {
        type = type || 'info';
        if (typeof window.crmNotify !== 'undefined') {
            var kind = type === 'danger' ? 'error' : type;
            if (['success', 'error', 'warning', 'info'].indexOf(kind) !== -1 &&
                typeof window.crmNotify[kind] === 'function') {
                window.crmNotify[kind]({ message: message });
            } else {
                window.crmNotify.info({ message: message });
            }
            return;
        }

        var container = document.getElementById('toastContainer');
        if (!container) {
            return;
        }

        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        var icon = {
            success: 'fa-circle-check',
            error: 'fa-circle-exclamation',
            warning: 'fa-triangle-exclamation',
            info: 'fa-circle-info'
        }[type] || 'fa-circle-info';

        toast.innerHTML =
            '<i class="fa-solid ' + icon + '"></i>' +
            '<span class="toast-message">' + message + '</span>' +
            '<button type="button" class="toast-close" aria-label="Close">&times;</button>';
        toast.querySelector('.toast-close').addEventListener('click', function () {
            toast.remove();
        });
        container.appendChild(toast);

        window.setTimeout(function () {
            toast.classList.add('toast-slide-out');
            window.setTimeout(function () {
                toast.remove();
            }, 300);
        }, 5000);
    };

    window.showNotification = function (message, type) {
        window.showToast(message, type || 'info');
    };

    window.showLoading = function () {
        var el = document.getElementById('loadingOverlay');
        if (el) {
            el.style.display = 'flex';
        }
    };

    window.hideLoading = function () {
        var el = document.getElementById('loadingOverlay');
        if (el) {
            el.style.display = 'none';
        }
    };

    function updateKpis(kpis) {
        if (!kpis) {
            return;
        }

        Object.keys(kpis).forEach(function (key) {
            var valueEl = document.querySelector('[data-kpi-value="' + key + '"]');
            if (valueEl) {
                valueEl.textContent = formatCount(kpis[key]);
            }
        });

        var noteSubtitle = document.querySelector('[data-kpi-subtitle="note_deadline"]');
        if (noteSubtitle && typeof kpis.note_deadline !== 'undefined') {
            var shown = Math.min(
                parseInt(document.getElementById('todo-task-list-root')?.getAttribute('data-per-page') || '10', 10) || 10,
                kpis.note_deadline
            );
            noteSubtitle.textContent = shown + ' shown below';
        }
    }

    function updateCalendarStats(stats) {
        if (!stats) {
            return;
        }
        var map = {
            calStatToday: stats.today,
            calStatWeek: stats.this_week,
            calStatOverdue: stats.overdue_actions
        };
        Object.keys(map).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.textContent = String(map[id] ?? 0);
            }
        });
    }

    function refreshCalendarFeed() {
        if (window.staffDashboardCalendar && typeof window.staffDashboardCalendar.refetchEvents === 'function') {
            window.staffDashboardCalendar.refetchEvents();
        }
        if (typeof window.refreshUpcomingList === 'function') {
            window.refreshUpcomingList();
        }
    }

    function replaceListRoot(rootId, section, reinit) {
        var root = document.getElementById(rootId);
        if (!root || !section) {
            return;
        }

        root.innerHTML = section.html || '';
        root.setAttribute('data-current-page', String(section.current_page || 1));
        root.setAttribute('data-last-page', String(section.last_page || 1));
        root.setAttribute('data-per-page', String(section.per_page || 10));
        root.setAttribute('data-total', String(section.total || 0));

        if (typeof reinit === 'function') {
            reinit(root);
        }
    }

    function applyTodoFilter(filter) {
        document.querySelectorAll('.todo-task-item').forEach(function (li) {
            var u = li.getAttribute('data-urgency') || '';
            var show = filter === 'all'
                || (filter === 'upcoming' && ['tomorrow', 'this-week', 'upcoming'].indexOf(u) !== -1)
                || (filter !== 'upcoming' && filter !== 'all' && u === filter);
            li.style.display = show ? '' : 'none';
        });
    }

    function bindTodoFilterTabs(scope) {
        (scope || document).querySelectorAll('.todo-filter-tab').forEach(function (tab) {
            if (tab.dataset.filterBound === '1') {
                return;
            }
            tab.dataset.filterBound = '1';
            tab.addEventListener('click', function () {
                var filter = tab.getAttribute('data-todo-filter');
                document.querySelectorAll('.todo-filter-tab').forEach(function (btn) {
                    btn.classList.toggle('is-active', btn === tab);
                });
                applyTodoFilter(filter);
            });
        });
    }

    function initTodoInfiniteScroll(root) {
        root = root || document.getElementById('todo-task-list-root');
        if (!root || root.getAttribute('data-infinite-scroll') !== '1') {
            return;
        }

        if (root.dataset.scrollBound === '1') {
            return;
        }
        root.dataset.scrollBound = '1';

        var list = document.getElementById('todo-task-list');
        var loader = document.getElementById('todoInfiniteLoader');
        var isLoading = false;

        function currentFilter() {
            var active = document.querySelector('.todo-filter-tab.is-active');
            return active ? active.getAttribute('data-todo-filter') : 'all';
        }

        function hasMore() {
            var current = parseInt(root.getAttribute('data-current-page'), 10) || 1;
            var last = parseInt(root.getAttribute('data-last-page'), 10) || 1;
            return current < last;
        }

        function loadMore() {
            if (isLoading || !hasMore() || !list) {
                return;
            }

            var current = parseInt(root.getAttribute('data-current-page'), 10) || 1;
            var nextPage = current + 1;
            var perPage = parseInt(root.getAttribute('data-per-page'), 10) || 10;
            var url = root.getAttribute('data-tasks-url') || cfg().dashboardTasks;
            if (!url) {
                return;
            }

            isLoading = true;
            if (loader) {
                loader.hidden = false;
            }

            fetch(url + '?page=' + nextPage + '&per_page=' + perPage, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json'
                },
                credentials: 'same-origin'
            })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('HTTP ' + res.status);
                    }
                    return res.json();
                })
                .then(function (data) {
                    if (!data || !data.success) {
                        toastLoadMoreFailure('Could not load more tasks. Please try again.');
                        return;
                    }
                    if (data.html) {
                        list.insertAdjacentHTML('beforeend', data.html);
                        applyTodoFilter(currentFilter());
                    }
                    root.setAttribute('data-current-page', String(data.current_page || nextPage));
                    root.setAttribute('data-last-page', String(data.last_page || nextPage));
                    if (typeof data.total !== 'undefined') {
                        root.setAttribute('data-total', String(data.total));
                        var badge = document.querySelector('.todo-count-badge');
                        if (badge) {
                            badge.textContent = String(data.total);
                        }
                    }
                })
                .catch(function (err) {
                    console.error('My Tasks load more failed', err);
                    toastLoadMoreFailure('Could not load more tasks. Please try again.');
                })
                .finally(function () {
                    isLoading = false;
                    if (loader) {
                        loader.hidden = true;
                    }
                    if (hasMore() && root.scrollHeight <= root.clientHeight + 4) {
                        loadMore();
                    }
                });
        }

        function maybeLoadMore() {
            if (!hasMore() || isLoading) {
                return;
            }
            var remaining = root.scrollHeight - root.scrollTop - root.clientHeight;
            if (remaining < 80) {
                loadMore();
            }
        }

        root.addEventListener('scroll', maybeLoadMore, { passive: true });
        window.setTimeout(function () {
            if (hasMore() && root.scrollHeight <= root.clientHeight + 4) {
                loadMore();
            }
        }, 0);
    }

    function initCasesInfiniteScroll(root) {
        root = root || document.getElementById('cases-attention-list-root');
        if (!root || root.getAttribute('data-infinite-scroll') !== '1') {
            return;
        }

        if (root.dataset.scrollBound === '1') {
            return;
        }
        root.dataset.scrollBound = '1';

        var list = document.getElementById('cases-attention-list');
        var loader = document.getElementById('casesInfiniteLoader');
        var isLoading = false;

        function hasMore() {
            var current = parseInt(root.getAttribute('data-current-page'), 10) || 1;
            var last = parseInt(root.getAttribute('data-last-page'), 10) || 1;
            return current < last;
        }

        function loadMore() {
            if (isLoading || !hasMore() || !list) {
                return;
            }

            var current = parseInt(root.getAttribute('data-current-page'), 10) || 1;
            var nextPage = current + 1;
            var perPage = parseInt(root.getAttribute('data-per-page'), 10) || 10;
            var url = root.getAttribute('data-cases-url') || cfg().dashboardCases;
            if (!url) {
                return;
            }

            isLoading = true;
            if (loader) {
                loader.hidden = false;
            }

            fetch(url + '?page=' + nextPage + '&per_page=' + perPage, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json'
                },
                credentials: 'same-origin'
            })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('HTTP ' + res.status);
                    }
                    return res.json();
                })
                .then(function (data) {
                    if (!data || !data.success) {
                        toastLoadMoreFailure('Could not load more activity. Please try again.');
                        return;
                    }
                    if (data.html) {
                        list.insertAdjacentHTML('beforeend', data.html);
                    }
                    root.setAttribute('data-current-page', String(data.current_page || nextPage));
                    root.setAttribute('data-last-page', String(data.last_page || nextPage));
                    if (typeof data.total !== 'undefined') {
                        root.setAttribute('data-total', String(data.total));
                        var badge = document.getElementById('cases-attention-badge');
                        if (badge) {
                            badge.textContent = String(data.total);
                        }
                    }
                })
                .catch(function (err) {
                    console.error('Recent matter activity load more failed', err);
                    toastLoadMoreFailure('Could not load more activity. Please try again.');
                })
                .finally(function () {
                    isLoading = false;
                    if (loader) {
                        loader.hidden = true;
                    }
                    if (hasMore() && root.scrollHeight <= root.clientHeight + 4) {
                        loadMore();
                    }
                });
        }

        function maybeLoadMore() {
            if (!hasMore() || isLoading) {
                return;
            }
            var remaining = root.scrollHeight - root.scrollTop - root.clientHeight;
            if (remaining < 80) {
                loadMore();
            }
        }

        root.addEventListener('scroll', maybeLoadMore, { passive: true });
        window.setTimeout(function () {
            if (hasMore() && root.scrollHeight <= root.clientHeight + 4) {
                loadMore();
            }
        }, 0);
    }

    function reinitTasksPanel(root) {
        bindTodoFilterTabs(root);
        initTodoInfiniteScroll(root);
        if (typeof window.initDashboardAddTaskPopovers === 'function') {
            window.initDashboardAddTaskPopovers(root);
        }
        var badge = document.querySelector('.todo-count-badge');
        if (badge && root) {
            badge.textContent = root.getAttribute('data-total') || '0';
        }
    }

    function reinitCasesPanel(root) {
        initCasesInfiniteScroll(root);
        var badge = document.getElementById('cases-attention-badge');
        if (badge && root) {
            badge.textContent = root.getAttribute('data-total') || '0';
        }
    }

    function refreshDashboard() {
        var summaryUrl = cfg().dashboardSummary;
        if (!summaryUrl) {
            window.location.reload();
            return;
        }

        window.showLoading();

        fetch(summaryUrl + '?fresh=1', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json'
            },
            credentials: 'same-origin'
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    throw new Error('Refresh failed');
                }

                updateKpis(data.kpis);
                updateCalendarStats(data.calendar_stats);

                var tasksRoot = document.getElementById('todo-task-list-root');
                if (tasksRoot && data.tasks) {
                    tasksRoot.dataset.scrollBound = '';
                    replaceListRoot('todo-task-list-root', data.tasks, reinitTasksPanel);
                }

                var casesRoot = document.getElementById('cases-attention-list-root');
                if (casesRoot && data.cases) {
                    casesRoot.dataset.scrollBound = '';
                    replaceListRoot('cases-attention-list-root', data.cases, reinitCasesPanel);
                }

                var responsibleMount = document.getElementById('responsible-matters-mount');
                if (responsibleMount && typeof data.responsible_matters_html === 'string') {
                    responsibleMount.innerHTML = data.responsible_matters_html;
                    initResponsibleMattersPager();
                }

                refreshCalendarFeed();

                if (typeof window.refreshCrmNavPendingTaskCount === 'function') {
                    window.refreshCrmNavPendingTaskCount();
                }

                window.showToast('Dashboard updated.', 'success');
            })
            .catch(function (err) {
                console.error('Dashboard refresh failed', err);
                window.showToast('Could not refresh dashboard. Reloading…', 'warning');
                window.setTimeout(function () {
                    window.location.reload();
                }, 800);
            })
            .finally(function () {
                window.hideLoading();
            });
    }

    function initKpiCardAnimations() {
        document.querySelectorAll('.kpi-card-modern').forEach(function (card, index) {
            card.style.animation = 'dashboardFadeInUp 0.5s ease ' + (index * 0.1) + 's both';
        });
    }

    function bindRefreshControls() {
        var btn = document.getElementById('refreshDashboard');
        if (btn && btn.dataset.bound !== '1') {
            btn.dataset.bound = '1';
            btn.addEventListener('click', refreshDashboard);
        }

        document.addEventListener('keydown', function (e) {
            if (e.altKey && (e.key === 'r' || e.key === 'R')) {
                e.preventDefault();
                refreshDashboard();
            }
        });
    }

    function initResponsibleMattersPager() {
        var section = document.getElementById('responsible-matters');
        if (!section) {
            return;
        }

        var pager = section.querySelector('.responsible-matters-pager');
        var table = section.querySelector('.responsible-matters-table');
        var rows = table ? Array.prototype.slice.call(table.querySelectorAll('tbody tr')) : [];
        if (!pager || !table || rows.length === 0) {
            return;
        }

        var total = rows.length;
        var perPageSelect = pager.querySelector('[data-responsible-per-page]');
        var status = pager.querySelector('[data-responsible-status]');
        var nav = pager.querySelector('[data-responsible-nav]');
        var page = 1;

        function perPage() {
            var value = perPageSelect ? parseInt(perPageSelect.value, 10) : 10;
            return value > 0 ? value : 10;
        }

        function pageCount() {
            return Math.max(1, Math.ceil(total / perPage()));
        }

        function render() {
            var size = perPage();
            var pages = pageCount();
            if (page > pages) {
                page = pages;
            }
            if (page < 1) {
                page = 1;
            }

            var start = (page - 1) * size;
            var end = Math.min(start + size, total);
            table.classList.add('is-paged');
            rows.forEach(function (row, index) {
                row.classList.toggle('is-visible', index >= start && index < end);
            });

            if (status) {
                status.textContent = 'Showing ' + (start + 1) + '–' + end + ' of ' + total;
            }

            if (!nav) {
                return;
            }

            nav.innerHTML = '';
            var prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'responsible-matters-page-btn';
            prev.textContent = 'Prev';
            prev.disabled = page <= 1;
            prev.addEventListener('click', function () {
                page -= 1;
                render();
            });
            nav.appendChild(prev);

            var windowStart = Math.max(1, page - 2);
            var windowEnd = Math.min(pages, windowStart + 4);
            windowStart = Math.max(1, windowEnd - 4);
            for (var i = windowStart; i <= windowEnd; i++) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'responsible-matters-page-btn' + (i === page ? ' is-current' : '');
                btn.textContent = String(i);
                btn.setAttribute('aria-label', 'Page ' + i);
                if (i === page) {
                    btn.setAttribute('aria-current', 'page');
                }
                (function (target) {
                    btn.addEventListener('click', function () {
                        page = target;
                        render();
                    });
                })(i);
                nav.appendChild(btn);
            }

            var next = document.createElement('button');
            next.type = 'button';
            next.className = 'responsible-matters-page-btn';
            next.textContent = 'Next';
            next.disabled = page >= pages;
            next.addEventListener('click', function () {
                page += 1;
                render();
            });
            nav.appendChild(next);
        }

        if (perPageSelect && perPageSelect.dataset.bound !== '1') {
            perPageSelect.dataset.bound = '1';
            perPageSelect.addEventListener('change', function () {
                page = 1;
                render();
            });
        }

        render();
    }

    function initDashboardPage() {
        bindTodoFilterTabs(document);
        initTodoInfiniteScroll();
        initCasesInfiniteScroll();
        initResponsibleMattersPager();
        bindRefreshControls();
        initKpiCardAnimations();
    }

    window.refreshDashboard = refreshDashboard;
    window.initDashboardInfiniteScroll = function () {
        initTodoInfiniteScroll();
        initCasesInfiniteScroll();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboardPage);
    } else {
        initDashboardPage();
    }
})();
