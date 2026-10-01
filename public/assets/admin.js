(function () {
  'use strict';
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  $$('[data-toggle-side]').forEach(function (b) {
    b.addEventListener('click', function () { var s = document.getElementById('side'); if (s) s.classList.toggle('open'); });
  });
  $$('[data-confirm-click]').forEach(function (b) {
    b.addEventListener('click', function (e) { if (!window.confirm(b.dataset.confirmClick)) e.preventDefault(); });
  });
  $$('[data-stop]').forEach(function (b) {
    b.addEventListener('click', function (e) { e.stopPropagation(); });
  });
  $$('form[data-busy]').forEach(function (f) {
    f.addEventListener('submit', function () {
      var b = f.querySelector('button');
      if (b) { b.disabled = true; b.textContent = f.dataset.busy; }
    });
  });
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.dataset.confirm)) e.preventDefault(); });
  });

  $$('[data-toggle-all]').forEach(function (m) {
    m.addEventListener('change', function () { $$('input[name="ids[]"]').forEach(function (c) { c.checked = m.checked; }); });
  });

  var typeRadios = $$('input[name="type"][value]');
  var type = typeRadios.length ? typeRadios[0] : null;
  function syncType() {
    var checked = typeRadios.filter(function (r) { return r.checked; })[0];
    var variable = checked && checked.value === 'variable';
    $$('[data-only-simple]').forEach(function (el) { el.style.display = variable ? 'none' : ''; });
    $$('[data-only-variable]').forEach(function (el) { el.style.display = variable ? '' : 'none'; });
  }
  if (type) { typeRadios.forEach(function (r) { r.addEventListener('change', syncType); }); syncType(); }

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

  $$('[data-bulk]').forEach(function (b) {
    b.addEventListener('click', function () {
      var mode = b.dataset.bulk;
      $$('input[name$="[stock]"]').forEach(function (i) { if (mode === 'stock' && document.getElementById('bulk-stock').value !== '') i.value = document.getElementById('bulk-stock').value; });
      $$('input[name$="[price]"][name^="variants"]').forEach(function (i) {
        if (mode === 'price') i.value = document.getElementById('bulk-price').value;
        if (mode === 'clear') i.value = '';
      });
    });
  });

  var gp = document.getElementById('gpreview');
  if (gp) {
    var f = function (n) { return document.getElementById('f_' + n); };
    var render = function () {
      var title = f('seo_title').value || f('name').value || '…';
      var desc = f('seo_description').value || (f('short_description').value || f('description').value).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
      gp.querySelector('.u').textContent = gp.dataset.base + (f('slug').value || '…');
      gp.querySelector('.t').textContent = title.length > 60 ? title.slice(0, 57) + '…' : title;
      gp.querySelector('.d').textContent = desc.length > 160 ? desc.slice(0, 157) + '…' : desc;
      [['seo_title', 60], ['seo_description', 160]].forEach(function (c) {
        var label = f(c[0]).parentNode.querySelector('label'), span = label.querySelector('.counter');
        if (!span) { span = document.createElement('span'); span.className = 'counter'; label.appendChild(span); }
        var n = f(c[0]).value.length; span.textContent = n + '/' + c[1]; span.className = 'counter' + (n > c[1] ? ' bad' : '');
      });
    };
    ['name', 'slug', 'seo_title', 'seo_description', 'short_description', 'description'].forEach(function (n) { f(n) && f(n).addEventListener('input', render); });
    render();
  }

  var dirty = false;
  $$('form.guard, .content form[enctype]').forEach(function (form) {
    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('submit', function () { dirty = false; });
  });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  $$('.alert.success').forEach(function (a) { setTimeout(function () { a.style.transition = 'opacity .5s'; a.style.opacity = '0'; setTimeout(function () { a.remove(); }, 600); }, 5000); });

  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () { navigator.clipboard && navigator.clipboard.writeText(b.dataset.copy); b.textContent = '✓'; });
  });
})();
