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

  global.initTS = initTS;
  global.destroyTS = destroyTS;
  global.openTS = openTS;
  global.setDisabledTS = setDisabledTS;
  /** @expose for callers that need duck-typing checks */
  global.getTomSelectInstance = getTS;
})(typeof window !== 'undefined' ? window : this);
