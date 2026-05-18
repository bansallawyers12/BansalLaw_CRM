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
   * Items: { id, name, email, status, cid, ... } (same shape as legacy Select2).
   * Important: if popover content is cloned from a hidden template, the same id may exist twice in the DOM;
   * always pass the visible tip element into initTS (e.g. $tip.find('#assign_client_id')), never rely on $('#id') alone.
   * @param {{ url: string, dropdownParent?: string|HTMLElement, placeholder?: string, loadThrottle?: number, minQueryLength?: number }} opts
   */
  function buildGetAllClientsTomSelectConfig(opts) {
    opts = opts || {};
    var url = opts.url || '';
    var dropdownParent = opts.dropdownParent !== undefined ? opts.dropdownParent : 'body';
    var placeholder = opts.placeholder || 'Search client...';
    var loadThrottle = opts.loadThrottle != null ? opts.loadThrottle : 250;
    var minQueryLen = opts.minQueryLength != null ? opts.minQueryLength : 1;

    return {
      maxItems: 1,
      plugins: ['clear_button'],
      allowEmptyOption: true,
      create: false,
      placeholder: placeholder,
      dropdownParent: dropdownParent,
      valueField: 'id',
      labelField: 'name',
      searchField: ['name', 'email'],
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
          var name = escape(item.name || item.text || '');
          var email = escape(item.email || '');
          var status = item.status || '';
          var statInner = '';
          if (status === 'Archived') {
            statInner = '<span class="ui label select2-result-repository__statistics">' + escape(status) + '</span>';
          } else if (status) {
            statInner = '<span class="ui label yellow select2-result-repository__statistics">' + escape(status) + '</span>';
          }
          return (
            '<div data-id="' + cidAttr + '" class="selectclient select2-result-repository ag-flex ag-space-between ag-align-center">' +
            '<div class="ag-flex ag-align-start">' +
            '<div class="ag-flex ag-flex-column col-hr-1"><div class="ag-flex"><span class="select2-result-repository__title text-semi-bold">' + name + '</span>&nbsp;</div>' +
            '<div class="ag-flex ag-align-center"><small class="select2-result-repository__description">' + email + '</small></div>' +
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
          headers: { Accept: 'application/json' }
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
            callback(data && Array.isArray(data.results) ? data.results : []);
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
})(typeof window !== 'undefined' ? window : this);
