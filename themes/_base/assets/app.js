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
        if (j.ok) { setBadge(j.count); toast(j.message); } else { toast(j.message); }
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
