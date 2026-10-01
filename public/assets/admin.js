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

  $$('textarea[data-editor]').forEach(function (ta) {
    var bar = document.createElement('div');
    bar.style.cssText = 'display:flex;gap:4px;flex-wrap:wrap;margin-bottom:6px';
    [['B', 'strong', 'strong'], ['I', 'em', 'em'], ['H2', 'h2', 'h2'], ['H3', 'h3', 'h3'], ['¶', 'p', 'p'], ['• Lista', 'ul', 'ul'], ['Link', 'a', 'a']].forEach(function (b) {
      var btn = document.createElement('button');
      btn.type = 'button'; btn.textContent = b[0]; btn.className = 'btn sec sm';
      btn.addEventListener('click', function () {
        var s = ta.selectionStart, e = ta.selectionEnd, sel = ta.value.substring(s, e), out;
        if (b[1] === 'ul') out = '<ul>\n' + (sel || 'Voce').split('\n').map(function (l) { return '  <li>' + l + '</li>'; }).join('\n') + '\n</ul>';
        else if (b[1] === 'a') { var u = window.prompt('URL', 'https://'); if (!u) return; out = '<a href="' + u.replace(/"/g, '') + '">' + (sel || u) + '</a>'; }
        else out = '<' + b[1] + '>' + sel + '</' + b[1] + '>';
        ta.value = ta.value.substring(0, s) + out + ta.value.substring(e);
        ta.focus();
      });
      bar.appendChild(btn);
    });
    ta.parentNode.insertBefore(bar, ta);
  });

  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () { navigator.clipboard && navigator.clipboard.writeText(b.dataset.copy); b.textContent = '✓'; });
  });
})();
