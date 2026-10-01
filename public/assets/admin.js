(function () {
  'use strict';
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.dataset.confirm)) e.preventDefault(); });
  });

  $$('[data-toggle-all]').forEach(function (m) {
    m.addEventListener('change', function () { $$('input[name="ids[]"]').forEach(function (c) { c.checked = m.checked; }); });
  });

  var type = document.getElementById('f_type');
  function syncType() {
    var variable = type && type.value === 'variable';
    $$('[data-only-simple]').forEach(function (el) { el.style.display = variable ? 'none' : ''; });
    $$('[data-only-variable]').forEach(function (el) { el.style.display = variable ? '' : 'none'; });
  }
  if (type) { type.addEventListener('change', syncType); syncType(); }

  var addAttr = document.getElementById('add-attr');
  if (addAttr) {
    addAttr.addEventListener('click', function () {
      var wrap = document.getElementById('attrs');
      var i = wrap.children.length;
      var div = document.createElement('div');
      div.className = 'attr-box';
      div.innerHTML = document.getElementById('attr-tpl').innerHTML.replace(/__I__/g, i);
      wrap.appendChild(div);
    });
    document.addEventListener('click', function (e) {
      if (e.target.matches('[data-remove-attr]')) e.target.closest('.attr-box').remove();
    });
  }

  $$('[data-slug-from]').forEach(function (slug) {
    var src = document.getElementById(slug.dataset.slugFrom);
    if (!src || slug.value) return;
    src.addEventListener('input', function () {
      if (slug.dataset.touched) return;
      slug.value = src.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    });
    slug.addEventListener('input', function () { slug.dataset.touched = '1'; });
  });

  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () { navigator.clipboard && navigator.clipboard.writeText(b.dataset.copy); b.textContent = '✓'; });
  });
})();
