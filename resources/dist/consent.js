/**
 * Statamic Consent — frontend runtime.
 *
 * Deliberately dependency-free and hand-written rather than bundled: an addon
 * that ships a build artefact ships the chance of shipping a stale one, and
 * this file is small enough not to need the machinery.
 */
(function () {
  'use strict';

  var el = document.getElementById('statamic-consent-config');
  if (!el) return;

  var config;
  try {
    config = JSON.parse(el.textContent || '{}');
  } catch (e) {
    return;
  }

  var required = config.required || [];
  var cookieCfg = config.cookie || {};
  var STORAGE_KEY = 'statamic_consent';

  /* ---------------------------------------------------------------- storage */

  function readCookie(name) {
    var parts = document.cookie ? document.cookie.split('; ') : [];
    for (var i = 0; i < parts.length; i++) {
      var pair = parts[i];
      var eq = pair.indexOf('=');
      if (eq > -1 && pair.slice(0, eq) === name) return pair.slice(eq + 1);
    }
    return null;
  }

  function writeCookie(name, value, days) {
    var expires = new Date(Date.now() + days * 864e5).toUTCString();
    var attrs = '; expires=' + expires + '; path=/; SameSite=' + (cookieCfg.sameSite || 'Lax');
    if (cookieCfg.secure) attrs += '; Secure';
    document.cookie = name + '=' + value + attrs;
  }

  function parse(raw) {
    if (!raw) return null;
    try {
      var data = JSON.parse(decodeURIComponent(raw));
      // A decision only covers the services that existed when it was made. When
      // the version moves, the old yes cannot stand in for the new service.
      if (!data || data.v !== config.version) return null;
      if (!Array.isArray(data.granted)) return null;
      return data;
    } catch (e) {
      return null;
    }
  }

  function load() {
    var fromCookie = parse(readCookie(cookieCfg.name));
    if (fromCookie) return fromCookie;

    // Pages served from a cache that strips cookies still have localStorage.
    try {
      return parse(localStorage.getItem(STORAGE_KEY));
    } catch (e) {
      return null;
    }
  }

  function save(granted, how) {
    var data = {
      v: config.version,
      granted: unique(required.concat(granted)),
      ts: Date.now(),
      how: how,
      // A random id, so a later dispute has something to point at. It is not
      // sent anywhere by this addon; it exists to be quoted.
      id: state && state.id ? state.id : randomId()
    };

    var raw = encodeURIComponent(JSON.stringify(data));
    writeCookie(cookieCfg.name, raw, cookieCfg.days || 182);
    try {
      localStorage.setItem(STORAGE_KEY, decodeURIComponent(raw));
    } catch (e) {
      /* private mode — the cookie carries it */
    }

    state = data;
    apply();
    document.dispatchEvent(new CustomEvent('consent:changed', { detail: { granted: data.granted, how: how } }));
  }

  function randomId() {
    if (window.crypto && window.crypto.randomUUID) return window.crypto.randomUUID();
    return 'c-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
  }

  function unique(list) {
    return list.filter(function (item, index) {
      return list.indexOf(item) === index;
    });
  }

  function isGranted(handle) {
    if (required.indexOf(handle) > -1) return true;
    return !!state && state.granted.indexOf(handle) > -1;
  }

  /* -------------------------------------------------------------- unlocking */

  /**
   * Move a gated embed out of its <template> and into the document. Until this
   * runs, nothing inside it has been requested — that is the whole point of the
   * two-click rule, and why the markup is a template and not a hidden div.
   */
  function unlockGates() {
    var gates = document.querySelectorAll('[data-consent-gate]');
    Array.prototype.forEach.call(gates, function (gate) {
      var handle = gate.getAttribute('data-consent-gate');
      if (!isGranted(handle) || gate.hasAttribute('data-consent-unlocked')) return;

      var template = gate.querySelector('template[data-consent-embed]');
      if (!template) return;

      gate.setAttribute('data-consent-unlocked', '');
      var placeholder = gate.querySelector('.csnt-gate__placeholder');
      if (placeholder) placeholder.remove();
      gate.appendChild(template.content.cloneNode(true));
      template.remove();
    });
  }

  /**
   * Scripts parked as type="text/plain" are re-created as real scripts. Setting
   * .type on the existing node does nothing — the browser decided how to treat
   * it at parse time, so a fresh node is the only way.
   */
  function unlockScripts() {
    var scripts = document.querySelectorAll('script[type="text/plain"][data-consent-service]');
    Array.prototype.forEach.call(scripts, function (node) {
      if (!isGranted(node.getAttribute('data-consent-service'))) return;

      var script = document.createElement('script');
      Array.prototype.forEach.call(node.attributes, function (attr) {
        if (attr.name === 'type' || attr.name === 'data-consent-service') return;
        script.setAttribute(attr.name, attr.value);
      });
      if (!node.src) script.text = node.textContent || '';
      node.parentNode.replaceChild(script, node);
    });
  }

  function apply() {
    unlockGates();
    unlockScripts();
    syncToggles();
    document.documentElement.setAttribute(
      'data-consent',
      state ? state.granted.join(' ') : ''
    );
  }

  /* ------------------------------------------------------------------- view */

  var root = document.querySelector('[data-consent-root]');
  var banner = root && root.querySelector('[data-consent-banner]');
  var modal = root && root.querySelector('[data-consent-modal]');
  var lastFocused = null;

  function show(node) {
    if (node) node.hidden = false;
  }

  function hide(node) {
    if (node) node.hidden = true;
  }

  function showBanner() {
    if (root) root.hidden = false;
    show(banner);
  }

  function hideBanner() {
    hide(banner);
    if (root && modal && modal.hidden) root.hidden = true;
  }

  function openModal() {
    if (!modal) return;
    // The banner steps aside while the panel is open: they occupy opposite
    // corners, but on a narrow screen the panel covers the whole width.
    hide(banner);
    lastFocused = document.activeElement;
    if (root) root.hidden = false;
    show(modal);
    syncToggles();
    var first = modal.querySelector('button, input');
    if (first) first.focus();
  }

  function closeModal() {
    hide(modal);

    // Closing without deciding is not a yes. Whichever way the panel was
    // closed — the button, Escape, the gate — an undecided visitor gets the
    // banner back rather than a page that pretends consent was given. This
    // lives here, not on the close button, because Escape used to walk past it.
    if (!state) {
      showBanner();
    } else if (root && banner && banner.hidden) {
      root.hidden = true;
    }

    if (lastFocused && lastFocused.focus) lastFocused.focus();
  }

  function syncToggles() {
    if (!modal) return;
    var inputs = modal.querySelectorAll('[data-consent-service]');
    Array.prototype.forEach.call(inputs, function (input) {
      input.checked = isGranted(input.getAttribute('data-consent-service'));
    });
  }

  function optionalHandles() {
    var handles = [];
    (config.categories || []).forEach(function (category) {
      (category.services || []).forEach(function (service) {
        if (!service.required) handles.push(service.handle);
      });
    });
    return handles;
  }

  function checkedHandles() {
    if (!modal) return [];
    var handles = [];
    var inputs = modal.querySelectorAll('[data-consent-service]');
    Array.prototype.forEach.call(inputs, function (input) {
      if (input.checked) handles.push(input.getAttribute('data-consent-service'));
    });
    return handles;
  }

  /* ---------------------------------------------------------------- wiring */

  document.addEventListener('click', function (event) {
    var target = event.target.closest ? event.target.closest('[data-consent-open],[data-consent-close],[data-consent-accept-all],[data-consent-necessary],[data-consent-reject-all],[data-consent-save],[data-consent-allow]') : null;
    if (!target) return;

    if (target.hasAttribute('data-consent-open')) {
      event.preventDefault();
      hideBanner();
      openModal();
      return;
    }

    if (target.hasAttribute('data-consent-close')) {
      event.preventDefault();
      closeModal();
      return;
    }

    if (target.hasAttribute('data-consent-accept-all')) {
      event.preventDefault();
      save(optionalHandles(), 'accept_all');
      closeModal();
      hideBanner();
      return;
    }

    if (target.hasAttribute('data-consent-necessary') || target.hasAttribute('data-consent-reject-all')) {
      event.preventDefault();
      save([], target.hasAttribute('data-consent-necessary') ? 'necessary_only' : 'reject_all');
      closeModal();
      hideBanner();
      return;
    }

    if (target.hasAttribute('data-consent-save')) {
      event.preventDefault();
      save(checkedHandles(), 'custom');
      closeModal();
      hideBanner();
      return;
    }

    if (target.hasAttribute('data-consent-allow')) {
      event.preventDefault();
      var handle = target.getAttribute('data-consent-allow');
      save((state ? state.granted : []).concat([handle]), 'gate');
      hideBanner();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && modal && !modal.hidden) closeModal();
  });

  /* -------------------------------------------------------------- start-up */

  var state = load();

  if (!state && config.respectGpc && navigator.globalPrivacyControl === true) {
    // A GPC header is an objection the visitor already made. Asking again would
    // be asking them to repeat themselves, so it is recorded as a rejection.
    save([], 'gpc');
  }

  apply();

  if (!state) {
    showBanner();
  }

  window.StatamicConsent = {
    granted: isGranted,
    open: openModal,
    acceptAll: function () { save(optionalHandles(), 'accept_all'); },
    rejectAll: function () { save([], 'reject_all'); },
    reset: function () {
      writeCookie(cookieCfg.name, '', -1);
      try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
      state = null;
      showBanner();
    },
    decision: function () { return state; }
  };
})();
