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
          callback();
          return;
        }
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        var qEnc = encodeURIComponent(String(query));
        fetch(url + sep + 'q=' + qEnc, {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' }
        })
          .then(function (r) {
            return r.json();
          })
          .then(function (data) {
            callback(data && data.items ? data.items : []);
          })
          .catch(function () {
            callback();
          });
      }
    };
  }

  global.initTS = initTS;
  global.destroyTS = destroyTS;
  global.openTS = openTS;
  global.setDisabledTS = setDisabledTS;
  /** @expose for callers that need duck-typing checks */
  global.getTomSelectInstance = getTS;
  global.buildGetAllClientsTomSelectConfig = buildGetAllClientsTomSelectConfig;
})(typeof window !== 'undefined' ? window : this);
