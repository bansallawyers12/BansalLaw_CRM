/**
 * Thin helpers around Tom Select (vendored alongside Select2 during migration).
 *
 * Select2 → Tom Select (internal reference — verify against pinned Tom Select version):
 * - ajax.transport + processResults  → load(query, callback) + fetch; items must match valueField / labelField
 * - templateResult / templateSelection → render.option / render.item (return HTMLElement or markup as needed)
 * - dropdownParent → dropdownParent (string selector or HTMLElement; test inside modals)
 * - minimumResultsForSearch hide search → case-by-case (plugins / controlInput settings)
 * - select2:select / clear → item_add / change / clear etc. (payload shape differs — rewrite handlers)
 * - .select2('destroy') → destroyTS(el); .select2('open') → openTS(el)
 */

(function (global) {
  'use strict';

  /** @param {JQuery|Element|string} el */
  function getNativeSelect(el) {
    if (!el) return null;
    if (typeof el === 'string') {
      try {
        el = document.querySelector(el);
      } catch (e) {
        return null;
      }
      if (!el) return null;
    }
    if (el && el.jquery) el = el[0];
    if (!el || el.tagName !== 'SELECT') return null;
    return el;
  }

  /** @param {JQuery|Element|string} el */
  function getTS(el) {
    var sel = getNativeSelect(el);
    return sel && sel.tomselect ? sel.tomselect : null;
  }

  /**
   * @param {JQuery|Element|string} el
   * @param {object} [config]
   * @returns {*} TomSelect instance or null
   */
  function initTS(el, config) {
    if (typeof TomSelect === 'undefined') return null;

    var select = getNativeSelect(el);
    if (!select) return null;

    if (select.tomselect && typeof select.tomselect.destroy === 'function') {
      select.tomselect.destroy();
    }

    var instance = new TomSelect(select, config || {});
    select.setAttribute('data-crm-ts', '1');
    return instance;
  }

  /** @param {JQuery|Element|string} el */
  function destroyTS(el) {
    var ts = getTS(el);
    if (!ts || typeof ts.destroy !== 'function') return;
    ts.destroy();
  }

  /** @param {JQuery|Element|string} el */
  function openTS(el) {
    var ts = getTS(el);
    if (!ts || typeof ts.open !== 'function') return;
    ts.focus();
    ts.open();
  }

  /**
   * @param {JQuery|Element|string} el
   * @param {boolean} disabled
   */
  function setDisabledTS(el, disabled) {
    var select = getNativeSelect(el);
    if (!select) return;

    select.disabled = !!disabled;
    var ts = select.tomselect;
    if (!ts) return;
    if (disabled && typeof ts.disable === 'function') ts.disable();
    else if (!disabled && typeof ts.enable === 'function') ts.enable();
  }

  /**
   * Single-select AJAX client picker for GET /clients/get-allclients?q=
   * Items: { id, name, email, status, cid, client_id, matter_ref, search_label, ... }.
   * Important: if popover content is cloned from a hidden template, the same id may exist twice in the DOM;
   * always pass the visible tip element into initTS (e.g. $tip.find('#assign_client_id')), never rely on $('#id') alone.
   * @param {{ url: string, dropdownParent?: string|HTMLElement, placeholder?: string, loadThrottle?: number, minQueryLength?: number, showAccessBadges?: boolean, onChange?: function }} opts
   */
  function buildGetAllClientsTomSelectConfig(opts) {
    opts = opts || {};
    var url = opts.url || '';
    var dropdownParent = opts.dropdownParent !== undefined ? opts.dropdownParent : 'body';
    var placeholder = opts.placeholder || 'Search client...';
    var loadThrottle = opts.loadThrottle != null ? opts.loadThrottle : 250;
    var minQueryLen = opts.minQueryLength != null ? opts.minQueryLength : 1;
    var showAccessBadges = !!opts.showAccessBadges;

    function buildClientSearchDescription(item, escape) {
      var parts = [];
      if (item.email) {
        parts.push(escape(String(item.email)));
      }
      var refLabel = item.search_label || item.client_id || '';
      if (refLabel) {
        parts.push(escape(String(refLabel)));
      }
      return parts.join(' · ');
    }

    function buildClientSearchStatusHtml(item, escape) {
      var status = item.status || '';
      var badges = '';
      if (showAccessBadges && item.locked) {
        var ui = item.access_ui || {};
        if (ui.show_quick) {
          badges += '<span class="ui label tiny">Quick</span> ';
        }
        if (ui.show_supervisor) {
          badges += '<span class="ui label tiny">Supervisor</span> ';
        }
      }
      var statClass = status === 'Archived'
        ? 'ui label select2-result-repository__statistics'
        : 'ui label yellow select2-result-repository__statistics';
      if (status) {
        badges += '<span class="' + statClass + '">' + escape(status) + '</span>';
      }
      return badges;
    }

    var cfg = {
      maxItems: 1,
      plugins: ['clear_button'],
      allowEmptyOption: true,
      create: false,
      placeholder: placeholder,
      dropdownParent: dropdownParent,
      valueField: 'id',
      labelField: 'name',
      // Server already filters by name, email, phone, client ref, matter ref, etc.
      filter: false,
      searchField: ['name', 'email', 'client_id', 'matter_ref', 'search_label'],
      loadThrottle: loadThrottle,
      shouldLoad: function (query) {
        return String(query || '').length >= minQueryLen;
      },
      render: {
        option: function (item, escape) {
          if (!item || item.loading) {
            return '<div class="crm-ts-client-loading">…</div>';
          }
          var cidAttr = escape(String(item.cid != null ? item.cid : ''));
          var name = (showAccessBadges && item.locked ? '&#128274; ' : '') + escape(item.name || item.text || '');
          var description = buildClientSearchDescription(item, escape);
          var statInner = buildClientSearchStatusHtml(item, escape);
          var lockedClass = showAccessBadges && item.locked ? ' opacity-75' : '';
          return (
            '<div data-id="' + cidAttr + '" class="selectclient select2-result-repository ag-flex ag-space-between ag-align-center' + lockedClass + '">' +
            '<div class="ag-flex ag-align-start">' +
            '<div class="ag-flex ag-flex-column col-hr-1"><div class="ag-flex"><span class="select2-result-repository__title text-semi-bold">' + name + '</span>&nbsp;</div>' +
            (description ? '<div class="ag-flex ag-align-center"><small class="select2-result-repository__description">' + description + '</small></div>' : '') +
            '</div></div>' +
            '<div class="ag-flex ag-flex-column ag-align-end">' +
            '<span class="select2resultrepositorystatistics">' + statInner + '</span>' +
            '</div></div>'
          );
        },
        item: function (item, escape) {
          return '<div>' + escape(item.name || item.text || '') + '</div>';
        }
      },
      load: function (query, callback) {
        if (!url) {
          callback([]);
          return;
        }
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        var qEnc = encodeURIComponent(String(query));
        fetch(url + sep + 'q=' + qEnc, {
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
          .then(function (r) {
            if (!r.ok) {
              throw new Error('HTTP ' + r.status);
            }
            return r.json();
          })
          .then(function (data) {
            callback(data && data.items ? data.items : []);
          })
          .catch(function () {
            callback([]);
          });
      }
    };

    if (typeof opts.onChange === 'function') {
      cfg.onChange = opts.onChange;
    }

    return cfg;
  }

  /**
   * Multi-select AJAX for GET /clients/get-recipients?q= (admin recipients).
   * @param {{ url: string, dropdownParent?: string|HTMLElement, enableRemoteLoad?: boolean, loadThrottle?: number }} opts
   */
  function buildCrmGetRecipientsMultiTomSelectConfig(opts) {
    opts = opts || {};
    var url = opts.url || '';
    var dropdownParent = opts.dropdownParent !== undefined ? opts.dropdownParent : 'body';
    var enableRemoteLoad = opts.enableRemoteLoad !== false;
    var loadThrottle = opts.loadThrottle != null ? opts.loadThrottle : 300;

    var cfg = {
      plugins: ['remove_button'],
      maxItems: null,
      closeAfterSelect: false,
      valueField: 'id',
      labelField: 'name',
      searchField: ['name', 'email'],
      loadThrottle: loadThrottle,
      dropdownParent: dropdownParent,
      create: false,
      render: {
        option: function (item, escape) {
          if (!item || item.loading) {
            return '<div class="crm-ts-recipient-loading">Searching…</div>';
          }
          var name = escape(item.name || item.text || '');
          var email = escape(item.email || '');
          var status = escape(item.status || '');
          return (
            '<div class="select2-result-repository ag-flex ag-space-between ag-align-center">' +
            '<div class="ag-flex ag-align-start">' +
            '<div class="ag-flex ag-flex-column col-hr-1"><div class="ag-flex"><span class="select2-result-repository__title text-semi-bold">' + name + '</span>&nbsp;</div>' +
            '<div class="ag-flex ag-align-center"><small class="select2-result-repository__description">' + email + '</small></div>' +
            '</div></div>' +
            '<div class="ag-flex ag-flex-column ag-align-end">' +
            '<span class="ui label yellow select2-result-repository__statistics">' + status + '</span>' +
            '</div></div>'
          );
        },
        item: function (item, escape) {
          return '<div>' + escape(item.name || item.text || '') + '</div>';
        }
      }
    };

    if (enableRemoteLoad && url) {
      cfg.load = function (query, callback) {
        if (!query || !String(query).length) {
          callback([]);
          return;
        }
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        fetch(url + sep + 'q=' + encodeURIComponent(String(query)), {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' }
        })
          .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
          })
          .then(function (data) {
            callback(data && data.items ? data.items : []);
          })
          .catch(function () {
            callback([]);
          });
      };
    }

    return cfg;
  }

  /**
   * Static-option native &lt;select&gt; → single-value Tom Select (staff/office/matter pickers).
   * @param {{ dropdownParent?: string|HTMLElement }} opts
   */
  function buildPlainSingleTomSelectConfig(opts) {
    opts = opts || {};
    var cfg = {
      maxItems: 1,
      create: false,
      allowEmptyOption: true,
      dropdownParent: opts.dropdownParent !== undefined ? opts.dropdownParent : 'body'
    };
    /** Short static lists: hide typeahead filtering (≈ Select2 minimumResultsForSearch: Infinity). */
    if (opts.minimalSearch) {
      cfg.searchField = [];
    }
    return cfg;
  }

  /**
   * Multi-select AJAX partner search (POST) for client/lead edit “related files”.
   * Body: query, exclude_client (optional). Response: { partners: [{ id, first_name, last_name, client_id, email, phone }] }.
   * @param {{ url: string, csrfToken?: string, excludeClientId?: string|number|null, dropdownParent?: string|HTMLElement, placeholder?: string, minQueryLength?: number, loadThrottle?: number }} opts
   */
  function buildPartnerSearchMultiTomSelectConfig(opts) {
    opts = opts || {};
    var url = opts.url || '';
    var csrf = opts.csrfToken || '';
    var excludeClient = opts.excludeClientId !== undefined && opts.excludeClientId !== null ? opts.excludeClientId : '';
    var dropdownParent = opts.dropdownParent !== undefined ? opts.dropdownParent : 'body';
    var minLen = opts.minQueryLength != null ? opts.minQueryLength : 2;
    var placeholder = opts.placeholder || 'Search for clients by name or client ID';
    var loadThrottle = opts.loadThrottle != null ? opts.loadThrottle : 250;

    return {
      plugins: ['remove_button'],
      maxItems: null,
      closeAfterSelect: false,
      valueField: 'id',
      labelField: 'text',
      searchField: ['text'],
      allowEmptyOption: true,
      create: false,
      placeholder: placeholder,
      dropdownParent: dropdownParent,
      loadThrottle: loadThrottle,
      shouldLoad: function (q) {
        return String(q || '').length >= minLen;
      },
      load: function (query, callback) {
        if (!url || String(query).length < minLen) {
          callback([]);
          return;
        }
        var body = new URLSearchParams();
        body.set('query', String(query));
        if (excludeClient !== '' && excludeClient != null) {
          body.set('exclude_client', String(excludeClient));
        }
        fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: body.toString()
        })
          .then(function (r) {
            if (!r.ok) {
              throw new Error('HTTP ' + r.status);
            }
            return r.json();
          })
          .then(function (data) {
            var partners = data && Array.isArray(data.partners) ? data.partners : [];
            callback(
              partners.map(function (p) {
                var fid = p.first_name != null ? String(p.first_name) : '';
                var lid = p.last_name != null ? String(p.last_name) : '';
                var cid = p.client_id != null && p.client_id !== '' ? String(p.client_id) : 'No ID';
                return {
                  id: p.id,
                  text: (fid + ' ' + lid).trim() + ' (' + cid + ')',
                  email: p.email,
                  phone: p.phone,
                  client_id: p.client_id
                };
              })
            );
          })
          .catch(function () {
            callback([]);
          });
      },
      render: {
        option: function (item, escape) {
          if (!item || item.loading) {
            return '<div class="crm-ts-partner-loading">Searching…</div>';
          }
          return (
            '<div class="select2-result-partner" style="padding: 8px;">' +
            '<div class="select2-result-partner__title" style="font-weight: 600; color: #333; font-size: 14px;">' +
            escape(item.text || '') +
            '</div></div>'
          );
        },
        item: function (item, escape) {
          return '<div>' + escape(item.text || '') + '</div>';
        }
      }
    };
  }

  /**
   * Single-select AJAX contact person (company lead create, company edit).
   * GET url with q= and optional exclude_id=; response { results: [{ id, text, first_name, last_name, email, phone, client_id }] }.
   * @param {{ url: string, excludeId?: string|number, dropdownParent?: string|HTMLElement, placeholder?: string, loadThrottle?: number, minQueryLength?: number }} opts
   */
  function buildContactPersonSearchTomSelectConfig(opts) {
    opts = opts || {};
    var url = opts.url || '';
    var dropdownParent = opts.dropdownParent !== undefined ? opts.dropdownParent : 'body';
    var loadThrottle = opts.loadThrottle != null ? opts.loadThrottle : 250;
    var minQueryLen = opts.minQueryLength != null ? opts.minQueryLength : 2;
    var excludeId = opts.excludeId !== undefined && opts.excludeId !== null ? opts.excludeId : null;

    return {
      maxItems: 1,
      plugins: ['clear_button'],
      allowEmptyOption: true,
      create: false,
      placeholder: opts.placeholder || 'Type phone, email, name, or client ID to search...',
      dropdownParent: dropdownParent,
      valueField: 'id',
      labelField: 'text',
      searchField: ['text', 'first_name', 'last_name', 'email', 'phone', 'client_id'],
      loadThrottle: loadThrottle,
      shouldLoad: function (query) {
        return String(query || '').length >= minQueryLen;
      },
      render: {
        option: function (item, escape) {
          if (!item || item.loading) {
            return '<div class="crm-ts-contact-person-loading">Searching…</div>';
          }
          return '<div>' + escape(item.text || '') + '</div>';
        },
        item: function (item, escape) {
          return '<div>' + escape(item.text || '') + '</div>';
        }
      },
      load: function (query, callback) {
        if (!url) {
          callback([]);
          return;
        }
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        var u = url + sep + 'q=' + encodeURIComponent(String(query));
        if (excludeId != null && String(excludeId) !== '') {
          u += '&exclude_id=' + encodeURIComponent(String(excludeId));
        }
        fetch(u, {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' }
        })
          .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
          })
          .then(function (data) {
            var raw = data && Array.isArray(data.results) ? data.results : [];
            var normalized = raw.map(function (item) {
              if (!item || item.id == null) return null;
              var fn = item.first_name != null ? String(item.first_name) : '';
              var ln = item.last_name != null ? String(item.last_name) : '';
              var nameGuess = (fn + ' ' + ln).trim();
              var text =
                item.text ||
                (nameGuess
                  ? nameGuess +
                    (item.email ? ' (' + item.email + ')' : '') +
                    (item.phone ? ' — ' + item.phone : '') +
                    (item.client_id != null && item.client_id !== '' ? ' — ' + item.client_id : '')
                  : item.email || item.phone || String(item.id));
              return {
                id: item.id,
                text: text,
                first_name: item.first_name,
                last_name: item.last_name,
                email: item.email,
                phone: item.phone,
                client_id: item.client_id
              };
            }).filter(Boolean);
            callback(normalized);
          })
          .catch(function () {
            callback([]);
          });
      }
    };
  }

  /**
   * Multi recipient select with preloaded options/items; can keep AJAX search when opts.url is set.
   * @param {JQuery|Element|string} el
   * @param {{ url?: string, dropdownParent?: string|HTMLElement, enableRemoteLoad?: boolean, options: Array<{id:any,name?:string,email?:string,status?:string}>, items: Array<any>, triggerChange?: boolean }} opts
   */
  function initRecipientsMultiTomSelectPreload(el, opts) {
    opts = opts || {};
    var sel = getNativeSelect(el);
    if (!sel) return null;
    destroyTS(sel);
    var url = opts.url != null ? String(opts.url).trim() : '';
    var enableRemote =
      opts.enableRemoteLoad !== undefined && opts.enableRemoteLoad !== null
        ? !!opts.enableRemoteLoad
        : !!url;
    var base = buildCrmGetRecipientsMultiTomSelectConfig({
      url: url,
      dropdownParent: opts.dropdownParent != null ? opts.dropdownParent : '#emailmodal',
      enableRemoteLoad: enableRemote
    });
    var items = (opts.items || []).map(function (x) {
      return String(x);
    });
    var instance = initTS(sel, Object.assign({}, base, { options: opts.options || [], items: items }));
    if (opts.triggerChange !== false && typeof jQuery !== 'undefined') {
      jQuery(sel).trigger('change');
    }
    return instance;
  }

  global.initTS = initTS;
  global.destroyTS = destroyTS;
  global.openTS = openTS;
  global.setDisabledTS = setDisabledTS;
  /** @expose for callers that need duck-typing checks */
  global.getTomSelectInstance = getTS;
  global.buildGetAllClientsTomSelectConfig = buildGetAllClientsTomSelectConfig;
  global.buildCrmGetRecipientsMultiTomSelectConfig = buildCrmGetRecipientsMultiTomSelectConfig;
  global.initRecipientsMultiTomSelectPreload = initRecipientsMultiTomSelectPreload;
  global.buildContactPersonSearchTomSelectConfig = buildContactPersonSearchTomSelectConfig;
  global.buildPlainSingleTomSelectConfig = buildPlainSingleTomSelectConfig;
  global.buildPartnerSearchMultiTomSelectConfig = buildPartnerSearchMultiTomSelectConfig;
})(typeof window !== 'undefined' ? window : this);
