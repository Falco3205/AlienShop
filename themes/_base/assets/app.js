(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  function cookie(name) {
    var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : '';
  }
  function setBadge(n) {
    $$('.cart-badge').forEach(function (b) {
      b.textContent = n;
      b.style.display = n > 0 ? 'flex' : 'none';
    });
  }
  setBadge(parseInt(cookie('as_cart'), 10) || 0);

  function toast(msg) {
    var t = $('#toast');
    if (!t) return;
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(function () { t.classList.remove('show'); }, 2600);
  }

  var ga = window.ASGA;
  function track(name, params) {
    if (!ga || !ga.loaded) { if (ga) ga.queue.push([name, params]); return; }
    window.gtag('event', name, params);
  }
  function loadGA() {
    if (!ga || ga.loaded) return;
    ga.loaded = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    var s = document.createElement('script');
    s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ga.id);
    document.head.appendChild(s);
    window.gtag('js', new Date());
    window.gtag('config', ga.id);
    ga.queue.forEach(function (e) { window.gtag('event', e[0], e[1]); });
    ga.queue = [];
  }
  if (ga) {
    ga.queue = (ga.events || []).map(function (e) {
      var p = e.params;
      if (e.name === 'purchase') {
        try { if (localStorage.getItem('as_purchase_' + p.transaction_id)) return null; localStorage.setItem('as_purchase_' + p.transaction_id, '1'); } catch (x) {}
      }
      return [e.name, p];
    }).filter(Boolean);
    var consent = null;
    try { consent = localStorage.getItem('as_consent'); } catch (x) {}
    var banner = $('#cookie-banner');
    if (!ga.consent || consent === 'granted') loadGA();
    else if (banner && consent !== 'denied') banner.hidden = false;
    $$('[data-consent]').forEach(function (b) {
      b.addEventListener('click', function () {
        try { localStorage.setItem('as_consent', b.dataset.consent); } catch (x) {}
        if (banner) banner.hidden = true;
        if (b.dataset.consent === 'granted') loadGA();
      });
    });
    $$('[data-cookie-settings]').forEach(function (a) {
      a.addEventListener('click', function (ev) { ev.preventDefault(); if (banner) banner.hidden = false; });
    });
  }

  var toggle = $('.menu-toggle');
  if (toggle) toggle.addEventListener('click', function () { $('.nav').classList.toggle('open'); });
  var searchBtn = $('[data-search-toggle]');
  if (searchBtn) searchBtn.addEventListener('click', function () {
    var box = $('.search-form');
    box.classList.toggle('open');
    if (box.classList.contains('open')) $('input', box).focus();
  });

  $$('.qty').forEach(function (q) {
    var input = $('input', q);
    $$('button', q).forEach(function (b) {
      b.addEventListener('click', function () {
        var v = (parseInt(input.value, 10) || 1) + parseInt(b.dataset.step, 10);
        input.value = Math.max(parseInt(input.min, 10) || 1, v);
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  });

  var data = $('#product-data');
  var form = $('#add-form');
  if (data && form) {
    var cfg = JSON.parse(data.textContent);
    try {
      if (navigator.sendBeacon && cfg.id && !sessionStorage.getItem('as_v' + cfg.id)) {
        sessionStorage.setItem('as_v' + cfg.id, '1');
        navigator.sendBeacon(cfg.beacon, new URLSearchParams({ p: cfg.id }));
      }
    } catch (x) {}
    var priceEl = $('#price');
    var stockEl = $('#stock');
    var btn = $('#add-btn');
    var variantInput = $('#variant-id');
    var mainImg = $('#main-image');

    function selection() {
      var sel = {}, complete = true;
      cfg.attributes.forEach(function (a) {
        var c = $('input[name="opt[' + a + ']"]:checked', form);
        if (c) sel[a] = c.value; else complete = false;
      });
      return { sel: sel, complete: complete };
    }
    function match(sel) {
      return cfg.variants.filter(function (v) {
        return Object.keys(sel).every(function (k) { return v.options[k] === sel[k]; });
      });
    }
    function update() {
      var s = selection();
      var found = match(s.sel);
      cfg.attributes.forEach(function (a) {
        var others = {};
        Object.keys(s.sel).forEach(function (k) { if (k !== a) others[k] = s.sel[k]; });
        var possible = match(others);
        $$('input[name="opt[' + a + ']"]', form).forEach(function (inp) {
          inp.disabled = !possible.some(function (v) { return v.options[a] === inp.value && v.stock_ok; });
        });
      });
      if (s.complete && found.length === 1) {
        var v = found[0];
        variantInput.value = v.id;
        priceEl.innerHTML = v.html;
        stockEl.className = 'stock ' + (v.stock_ok ? 'in' : 'out');
        stockEl.textContent = v.stock_ok ? cfg.t.in : cfg.t.out;
        btn.disabled = !v.stock_ok;
        if (v.image && mainImg) { mainImg.src = v.image; mainImg.removeAttribute('srcset'); }
      } else {
        variantInput.value = '';
        btn.disabled = cfg.attributes.length > 0;
        if (!s.complete) { priceEl.innerHTML = cfg.range; stockEl.textContent = ''; }
      }
    }
    if (cfg.attributes.length) {
      form.addEventListener('change', update);
      update();
    }

    form.addEventListener('submit', function (ev) {
      if (!window.fetch) return;
      ev.preventDefault();
      btn.disabled = true;
      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin'
      }).then(function (r) { return r.json(); }).then(function (j) {
        if (j.ok) {
          setBadge(j.count); toast(j.message);
          var sel = selection().sel, found = match(sel), v = found.length === 1 ? found[0] : null;
          var qty = parseInt($('input[name="qty"]', form).value, 10) || 1;
          if (cfg.item) track('add_to_cart', { currency: ga && ga.currency, value: (v ? v.value : cfg.item.price) * qty, items: [Object.assign({}, cfg.item, { price: v ? v.value : cfg.item.price, quantity: qty, item_variant: v ? Object.keys(v.options).map(function (k) { return v.options[k]; }).join(' / ') : undefined })] });
        } else { toast(j.message); }
      }).catch(function () { form.submit(); }).then(function () { update(); if (!cfg.attributes.length) btn.disabled = false; });
    });
  }

  $$('.thumbs button').forEach(function (b) {
    b.addEventListener('click', function () {
      var img = $('#main-image');
      img.src = b.dataset.src;
      if (b.dataset.srcset) img.srcset = b.dataset.srcset; else img.removeAttribute('srcset');
      $$('.thumbs button').forEach(function (x) { x.classList.remove('active'); });
      b.classList.add('active');
    });
  });

  var wish = [];
  try { wish = JSON.parse(localStorage.getItem('as_wish') || '[]'); } catch (x) {}
  function wishPaint() {
    $$('[data-wish]').forEach(function (b) {
      var on = wish.indexOf(parseInt(b.dataset.wish, 10)) > -1;
      b.classList.toggle('on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false');
      if (b.classList.contains('wish')) b.textContent = on ? '♥' : '♡';
      else b.textContent = (on ? '♥ ' : '♡ ') + (on ? b.dataset.labelOn : b.dataset.labelOff);
    });
    $$('.wish-badge').forEach(function (b) { b.textContent = wish.length; b.style.display = wish.length ? 'flex' : 'none'; });
  }
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-wish]');
    if (!b) return;
    ev.preventDefault(); ev.stopPropagation();
    var id = parseInt(b.dataset.wish, 10), i = wish.indexOf(id);
    if (i > -1) wish.splice(i, 1); else wish.push(id);
    try { localStorage.setItem('as_wish', JSON.stringify(wish)); } catch (x) {}
    wishPaint();
    var grid = $('#wish-grid');
    if (grid && i > -1) { var card = b.closest('.card'); if (card) card.remove(); if (!grid.children.length) $('#wish-empty').hidden = false; }
  });
  var wg = $('#wish-grid');
  if (wg && window.fetch) {
    if (!wish.length) $('#wish-empty').hidden = false;
    else fetch(wg.dataset.url + '?ids=' + wish.join(',')).then(function (r) { return r.json(); }).then(function (j) { wg.innerHTML = j.html; if (!j.html) $('#wish-empty').hidden = false; wishPaint(); });
  }
  wishPaint();

  var nt = $('#notify-form');
  if (nt && window.fetch) {
    nt.addEventListener('submit', function (ev) {
      ev.preventDefault();
      fetch(nt.action, { method: 'POST', body: new FormData(nt), headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); }).then(function (j) { $('#notify-msg').textContent = j.message; if (j.ok) nt.reset(); }).catch(function () { nt.submit(); });
    });
  }

  var ck = $('form[data-refresh] input[name="email"]');
  if (ck && window.fetch) {
    var sendCapture = function () {
      if (!ck.value || ck.validity && !ck.validity.valid) return;
      fetch(ck.form.dataset.refresh.replace('refresh', 'capture'), { method: 'POST', body: new URLSearchParams({ email: ck.value }), credentials: 'same-origin' });
    };
    ck.addEventListener('change', sendCapture);
    if (ck.value) sendCapture();
  }

  var nf = $('#newsletter-form');
  if (nf && window.fetch) {
    nf.addEventListener('submit', function (ev) {
      ev.preventDefault();
      fetch(nf.action, { method: 'POST', body: new FormData(nf), headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); }).then(function (j) { $('#newsletter-msg').textContent = j.message; if (j.ok) nf.reset(); })
        .catch(function () { nf.submit(); });
    });
  }

  var rf = $('#review-form');
  if (rf && window.fetch) {
    rf.addEventListener('submit', function (ev) {
      ev.preventDefault();
      fetch(rf.action, { method: 'POST', body: new FormData(rf), headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); }).then(function (j) { $('#review-msg').textContent = j.message; if (j.ok) rf.reset(); })
        .catch(function () { rf.submit(); });
    });
  }

  $$('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
  });

  $$('input[name="shipping_method"], [data-autosubmit-refresh]').forEach(function (el) {
    el.addEventListener('change', function () {
      var f = el.form;
      f.noValidate = true;
      f.action = f.dataset.refresh;
      f.submit();
    });
  });
})();
