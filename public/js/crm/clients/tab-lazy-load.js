/**
 * Lazy-load client detail tab panes and defer tab-specific module scripts.
 */
(function ($) {
    'use strict';
    if (!$) {
        return;
    }

    var loadedScripts = {};

    function cfg() {
        return window.ClientDetailConfig || {};
    }

    function scriptUrl(path) {
        var base = (cfg().assetBase || '').replace(/\/$/, '');
        return base + '/' + String(path).replace(/^\//, '');
    }

    function loadScriptOnce(path) {
        var url = scriptUrl(path);
        if (loadedScripts[url]) {
            return loadedScripts[url];
        }

        loadedScripts[url] = $.Deferred(function (defer) {
            var el = document.createElement('script');
            el.src = url;
            el.async = false;
            el.onload = function () {
                defer.resolve();
            };
            el.onerror = function () {
                defer.reject();
            };
            document.head.appendChild(el);
        }).promise();

        return loadedScripts[url];
    }

    function ensureTabScripts(tabId) {
        var map = cfg().lazyModuleScripts || {};
        var slug = String(tabId || '').toLowerCase();
        var paths = map[slug] || [];
        if (!paths.length) {
            return $.when();
        }

        var chain = $.when();
        paths.forEach(function (path) {
            chain = chain.then(function () {
                return loadScriptOnce(path);
            });
        });

        return chain;
    }

    function executeScripts($root) {
        $root.find('script').addBack('script').each(function () {
            var src = this.src;
            if (src) {
                if (!loadedScripts[src]) {
                    loadedScripts[src] = $.getScript(src);
                }
                return;
            }
            var code = this.textContent || this.innerHTML || '';
            if (!code.trim()) {
                return;
            }
            $.globalEval(code);
        });
    }

    function needsLazyLoad(tabId) {
        var $pane = $('#' + tabId + '-tab');
        return $pane.length && $pane.attr('data-lazy-tab') === tabId && $pane.attr('data-loaded') !== '1';
    }

    function loadIfNeeded(tabId) {
        if (!needsLazyLoad(tabId)) {
            return $.when();
        }

        var $pane = $('#' + tabId + '-tab');
        if ($pane.data('loading')) {
            return $pane.data('loadingPromise') || $.when();
        }

        var urls = cfg().urls || {};
        var base = urls.detailTabHtml || '';
        if (!base) {
            return $.when();
        }

        var requestUrl = base.replace(/\/$/, '') + '/' + encodeURIComponent(tabId);
        var deferred = $.Deferred();
        $pane.data('loading', true);
        $pane.data('loadingPromise', deferred.promise());

        ensureTabScripts(tabId)
            .then(function () {
                return $.ajax({
                    url: requestUrl,
                    type: 'GET',
                    data: {
                        matter_ref: cfg().matterId || '',
                        client_matter_id: cfg().clientMatterId || ''
                    }
                });
            })
            .done(function (html) {
                var $wrapper = $('<div>');
                // keepScripts so inline tab scripts (context menus, drag/drop) survive parse
                $wrapper.append($.parseHTML(html, document, true));
                var $newPane = $wrapper.children('.tab-pane').first();
                if (!$newPane.length) {
                    $newPane = $wrapper.find('.tab-pane').first();
                }

                var $loadedPane = $pane;
                if ($newPane.length) {
                    // Menus/modals/scripts may be siblings after .tab-pane in older markup
                    var $trailing = $newPane.nextAll();
                    var wasActive = $pane.hasClass('active');
                    $pane.replaceWith($newPane);
                    if ($trailing.length) {
                        $newPane.after($trailing);
                    }
                    if (wasActive) {
                        $newPane.addClass('active');
                    }
                    // Drop stale body-mounted context menus from a prior visit to this tab
                    ['fileContextMenu', 'visaFileContextMenu', 'notUsedFileContextMenu'].forEach(function (id) {
                        var nodes = document.querySelectorAll('#' + id);
                        if (nodes.length > 1) {
                            for (var i = 0; i < nodes.length - 1; i++) {
                                nodes[i].parentNode && nodes[i].parentNode.removeChild(nodes[i]);
                            }
                        }
                    });
                    executeScripts($newPane.add($trailing));
                    $newPane.attr('data-loaded', '1');
                    $loadedPane = $newPane;
                } else {
                    $pane.html(html);
                    executeScripts($pane);
                    $pane.attr('data-loaded', '1');
                }

                if (tabId === 'account' && window.ClientAccountsTab && typeof window.ClientAccountsTab.loadIfNeeded === 'function') {
                    window.ClientAccountsTab.loadIfNeeded();
                }

                $loadedPane.find('.grid_data').hide();

                $(document).trigger('clientTabContentLoaded', [tabId]);
                deferred.resolve();
            })
            .fail(function () {
                $pane.find('.client-tab-lazy-placeholder').html(
                    '<p class="text-danger mb-0">Failed to load this section. Please refresh and try again.</p>'
                );
                deferred.reject();
            })
            .always(function () {
                $pane.data('loading', false);
                $pane.removeData('loadingPromise');
            });

        return deferred.promise();
    }

    function preloadActiveTabScripts() {
        var active = (cfg().activeTab || 'personaldetails').toLowerCase();
        ensureTabScripts(active);
    }

    window.ClientTabLazy = {
        needsLazyLoad: needsLazyLoad,
        loadIfNeeded: loadIfNeeded,
        ensureTabScripts: ensureTabScripts,
        preloadActiveTabScripts: preloadActiveTabScripts
    };

    $(preloadActiveTabScripts);

})(typeof jQuery !== 'undefined' ? jQuery : null);
