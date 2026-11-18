/* Jaibaba website dashboard – small, dependency-free helpers (Bootstrap only). */
(function () {
  'use strict';

  var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

  /* ---------------------------------------------------------- sidebar */
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-open-sidebar]')) document.body.classList.add('sidebar-open');
    if (e.target.closest('[data-close-sidebar]')) document.body.classList.remove('sidebar-open');
  });

  /* ------------------------------------------------- confirm buttons */
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (el && !window.confirm(el.getAttribute('data-confirm'))) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  }, true);

  /* ------------------------------------------------- show password */
  $$('.js-show-password').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = btn.parentElement.querySelector('input');
      input.type = input.type === 'password' ? 'text' : 'password';
      btn.innerHTML = input.type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    });
  });

  /* ------------------------------------------------- page list filter */
  var list = $('#pageList');
  if (list) {
    var state = { q: '', type: '', status: new URLSearchParams(location.search).get('status') || '' };
    var apply = function () {
      var shown = 0;
      $$('.page-row', list).forEach(function (row) {
        var ok = (!state.q || row.dataset.search.indexOf(state.q) !== -1)
          && (!state.type || row.dataset.type === state.type)
          && (!state.status || row.dataset.status === state.status);
        row.hidden = !ok;
        if (ok) shown++;
      });
      $('#noResults').hidden = shown > 0;
    };
    $('#pageSearch').addEventListener('input', function () { state.q = this.value.trim().toLowerCase(); apply(); });
    $$('[data-filter-type]').forEach(function (b) {
      b.addEventListener('click', function () {
        $$('[data-filter-type]').forEach(function (x) { x.classList.toggle('active', x === b); });
        state.type = b.dataset.filterType; apply();
      });
    });
    $$('[data-filter-status]').forEach(function (b) {
      b.addEventListener('click', function () {
        $$('[data-filter-status]').forEach(function (x) { x.classList.toggle('active', x === b); });
        state.status = b.dataset.filterStatus; apply();
      });
    });
    apply();
  }

  /* ---------------------------------------------------- media screen */
  var grid = $('#mediaGrid');
  if (grid) {
    var m = { q: '', folder: '', unused: false };
    var applyMedia = function () {
      var shown = 0;
      $$('.media-item', grid).forEach(function (it) {
        var ok = (!m.q || it.dataset.name.indexOf(m.q) !== -1) && (!m.folder || it.dataset.folder === m.folder) && (!m.unused || it.dataset.used === '0');
        it.hidden = !ok;
        if (ok) shown++;
      });
      $('#mediaNone').hidden = shown > 0;
    };
    $('#mediaSearch').addEventListener('input', function () { m.q = this.value.trim().toLowerCase(); applyMedia(); });
    $('#mediaFolder').addEventListener('change', function () { m.folder = this.value; applyMedia(); });
    var unusedBtn = $('[data-media-unused]');
    unusedBtn.addEventListener('click', function () { m.unused = !m.unused; unusedBtn.classList.toggle('active', m.unused); applyMedia(); });
    grid.addEventListener('click', function (e) {
      var copy = e.target.closest('.js-copy');
      var choose = e.target.closest('.js-choose');
      if (copy) {
        var url = copy.dataset.url;
        (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () {
          copy.classList.add('copied'); setTimeout(function () { copy.classList.remove('copied'); }, 1200);
        }).catch(function () { window.prompt('Copy this address:', url); });
      }
      if (choose && window.parent !== window) {
        window.parent.postMessage({ pickImage: choose.dataset.url }, location.origin);
      }
    });
  }

  /* ---------------------------------------------- redirect form helper */
  var codeSel = $('[data-toggle-to]');
  if (codeSel) {
    var toggleTo = function () { $('[data-to-field]').hidden = codeSel.value === '410'; };
    codeSel.addEventListener('change', toggleTo); toggleTo();
  }
  var copySel = $('#copySelect');
  if (copySel) copySel.addEventListener('change', function () { if (copySel.value) $('#t-copy').checked = true; });

  /* ================================================================ editor */
  var forms = $$('[data-editor]');
  if (!forms.length) return;

  /* names: every [data-part] / [data-item] wrapper adds one step */
  function computeName(input, form) {
    var parts = [];
    if (input.dataset.partLeaf) parts.unshift(input.dataset.partLeaf);
    var el = input.parentElement;
    while (el && el !== form) {
      if (el.hasAttribute('data-item')) {
        var siblings = Array.prototype.filter.call(el.parentElement.children, function (c) { return c.hasAttribute('data-item'); });
        parts.unshift(String(siblings.indexOf(el)));
      }
      if (el.hasAttribute('data-part') && el.getAttribute('data-part') !== '') parts.unshift(el.getAttribute('data-part'));
      el = el.parentElement;
    }
    return parts.length ? parts[0] + parts.slice(1).map(function (p) { return '[' + p + ']'; }).join('') : input.name;
  }
  function renameAll(form) {
    $$('.js-name', form).forEach(function (input) { input.name = computeName(input, form); });
  }

  /* rich text */
  function initRich(ctx) {
    $$('.rich', ctx).forEach(function (box) {
      if (box.dataset.ready) return;
      box.dataset.ready = '1';
      var area = $('.rich-area', box);
      var src = $('.rich-source', box);
      area.innerHTML = src.value;
      area.addEventListener('input', function () { src.value = area.innerHTML; markDirty(box); });
      src.addEventListener('input', function () { area.innerHTML = src.value; });
      area.addEventListener('paste', function (e) {
        // Paste as plain text so Word/Google Docs styling doesn't come along.
        e.preventDefault();
        var text = (e.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, text);
      });
      $$('[data-cmd]', box).forEach(function (btn) {
        btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
        btn.addEventListener('click', function () {
          area.focus();
          var cmd = btn.dataset.cmd;
          if (cmd === 'link') {
            var url = window.prompt('Link address (for example /contact.html or https://…):', '/');
            if (!url) return;
            if (!/^(https?:|mailto:|tel:|\/|#)/i.test(url)) { window.alert('Links must start with /, https://, mailto: or tel:'); return; }
            document.execCommand('createLink', false, url);
          } else if (cmd === 'formatBlock') {
            document.execCommand('formatBlock', false, btn.dataset.arg);
          } else {
            document.execCommand(cmd, false, null);
          }
          src.value = area.innerHTML;
          markDirty(box);
        });
      });
      $('.js-html-toggle', box).addEventListener('click', function () {
        var showCode = src.hidden;
        src.hidden = !showCode;
        area.hidden = showCode;
        if (!showCode) area.innerHTML = src.value;
        this.classList.toggle('active', showCode);
      });
    });
  }

  /* dirty marker */
  var dirty = false;
  var submitting = false;
  function markDirty() {
    dirty = true;
    $$('[data-unsaved]').forEach(function (u) { u.hidden = false; });
  }
  window.addEventListener('beforeunload', function (e) {
    if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; }
  });

  /* counters */
  function updateCounter(input) {
    var max = parseInt(input.dataset.max, 10);
    var c = input.parentElement.querySelector('.counter');
    if (!c || !max) return;
    var n = input.value.length;
    c.textContent = n + ' / ' + max + ' characters';
    c.className = 'counter ' + (n === 0 ? 'warn' : n > max ? 'bad' : n < max * 0.5 ? 'warn' : 'good');
  }

  /* quick SEO check + Google snippet */
  function seoCheck(form) {
    var get = function (n) { var el = form.querySelector('[name="page[seo][' + n + ']"]'); return el ? el.value.trim() : ''; };
    var title = get('title'), desc = get('description'), kw = get('focusKeyword').toLowerCase();
    var t = $('[data-snippet-title]'), d = $('[data-snippet-desc]');
    if (t) t.textContent = title || (form.querySelector('[name="page[title]"]') || {}).value || '';
    if (d) d.textContent = desc || 'No description yet — Google will pick some text from the page.';
    var out = [];
    var add = function (lvl, msg) { out.push('<li class="' + lvl + '"><i class="fas ' + (lvl === 'good' ? 'fa-check-circle' : lvl === 'bad' ? 'fa-times-circle' : 'fa-exclamation-circle') + '"></i>' + msg + '</li>'); };
    if (!title) add('bad', 'Add a title for Google.');
    else if (title.length > 60) add('warn', 'Title is ' + title.length + ' characters; Google shows about 60.');
    else if (title.length < 30) add('warn', 'Title is short (' + title.length + '). 30–60 characters works best.');
    else add('good', 'Title length is good.');
    if (!desc) add('bad', 'Add a description for Google.');
    else if (desc.length > 160) add('warn', 'Description is ' + desc.length + ' characters; Google shows about 160.');
    else if (desc.length < 120) add('warn', 'Description is short (' + desc.length + '). Aim for 120–160.');
    else add('good', 'Description length is good.');
    var h1s = $$('.block-card:not(.is-hidden)[data-type="page-hero"]', form).length + $$('.block-card:not(.is-hidden)[data-type="html"] textarea', form).filter(function (ta) { return /<h1[\s>]/i.test(ta.value); }).length;
    if (h1s === 0) add('bad', 'The page has no main heading (a Top banner).');
    else if (h1s > 1) add('warn', 'The page has ' + h1s + ' main headings; use one.');
    else add('good', 'One main heading.');
    if (kw) {
      if (title.toLowerCase().indexOf(kw) === -1) add('warn', 'Main search phrase is not in the Google title.');
      else add('good', 'Main search phrase is in the title.');
    } else add('info', 'Tip: set the main search phrase on the Google tab.');
    var missingAlt = $$('[data-part="imageAlt"] input', form).filter(function (i) {
      var img = i.closest('[data-item], .block-card');
      var pic = img ? img.querySelector('[data-part="image"] input') : null;
      return pic && pic.value && !i.value.trim();
    }).length;
    if (missingAlt) add('warn', missingAlt + ' picture' + (missingAlt > 1 ? 's have' : ' has') + ' no description.');
    if (form.querySelector('[name="page[seo][noindex]"][type="checkbox"]:checked')) add('warn', 'This page is hidden from Google.');
    var list = $('[data-seo-list]');
    if (list) list.innerHTML = out.join('');
  }

  forms.forEach(function (form) {
    initRich(form);
    $$('[data-max]', form).forEach(function (i) { updateCounter(i); i.addEventListener('input', function () { updateCounter(i); }); });
    seoCheck(form);

    form.addEventListener('input', function (e) {
      markDirty();
      var t = e.target;
      if (t.classList.contains('js-icon')) {
        var prev = t.parentElement.querySelector('.icon-preview i');
        if (prev) prev.className = t.value;
      }
      if (t.classList.contains('js-image')) {
        var thumb = t.parentElement.querySelector('.thumb');
        thumb.src = t.value; thumb.hidden = !t.value;
      }
      if (t.classList.contains('js-item-label')) {
        var item = t.closest('[data-item]');
        var title = item && item.querySelector('.rep-title');
        if (title) title.textContent = t.tagName === 'SELECT' ? t.options[t.selectedIndex].text : t.value;
      }
      if (t.closest('[data-part="heading"]') && t.closest('.block-card')) {
        var bt = t.closest('.block-card').querySelector('.block-title');
        if (bt) bt.textContent = t.value;
      }
      seoCheck(form);
    });
    form.addEventListener('change', function (e) { markDirty(); seoCheck(form); if (e.target.matches('.js-item-label')) e.target.dispatchEvent(new Event('input', { bubbles: true })); });

    form.addEventListener('click', function (e) {
      var btn = e.target.closest('button');
      if (!btn || !form.contains(btn)) return;
      var item = btn.closest('[data-item]');
      if (btn.classList.contains('js-toggle')) {
        var body = btn.closest('.rep-head, .block-head').nextElementSibling;
        body.hidden = !body.hidden;
        btn.classList.toggle('open', !body.hidden);
        if (!body.hidden) initRich(body);
      } else if (btn.classList.contains('js-up') && item) {
        var prev = item.previousElementSibling;
        while (prev && !prev.hasAttribute('data-item')) prev = prev.previousElementSibling;
        if (prev) { item.parentElement.insertBefore(item, prev); markDirty(); }
      } else if (btn.classList.contains('js-down') && item) {
        var next = item.nextElementSibling;
        while (next && !next.hasAttribute('data-item')) next = next.nextElementSibling;
        if (next) { item.parentElement.insertBefore(next, item); markDirty(); }
      } else if (btn.classList.contains('js-remove') && item) {
        var isBlock = item.classList.contains('block-card');
        if (window.confirm(isBlock ? 'Remove this whole section from the page?' : 'Remove this item?')) { item.remove(); markDirty(); seoCheck(form); }
      } else if (btn.classList.contains('js-add')) {
        var rep = btn.closest('[data-rep]');
        var tpl = Array.prototype.find.call(rep.children, function (c) { return c.tagName === 'TEMPLATE'; });
        var items = Array.prototype.find.call(rep.children, function (c) { return c.classList.contains('rep-items'); });
        var node = tpl.content.firstElementChild.cloneNode(true);
        items.appendChild(node);
        var body2 = node.querySelector('.rep-body');
        if (body2) { body2.hidden = false; var tg = node.querySelector('.js-toggle'); if (tg) tg.classList.add('open'); }
        initRich(node);
        var first = node.querySelector('input:not([type=hidden]), textarea:not([hidden]), select, .rich-area');
        if (first) first.focus();
        markDirty();
      } else if (btn.classList.contains('js-add-block')) {
        var blocks = $('[data-blocks]', form);
        var t = blocks.querySelector('template[data-block-template="' + btn.dataset.type + '"]');
        var card = t.content.firstElementChild.cloneNode(true);
        $('.rep-items', blocks).appendChild(card);
        card.querySelector('.block-body').hidden = false;
        card.querySelector('.js-toggle').classList.add('open');
        initRich(card);
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        markDirty(); seoCheck(form);
      } else if (btn.classList.contains('js-pick-image')) {
        openPicker(btn.parentElement.querySelector('.js-image'));
      }
    });

    form.addEventListener('submit', function (e) {
      // Rich-text boxes keep their hidden textarea in sync while typing; only names need computing.
      renameAll(form);
      var sub = e.submitter;
      if (!sub || sub.getAttribute('formtarget') !== '_blank') submitting = true;
      var tab = $('[data-current-tab]', form);
      var active = $('.nav-link.active[data-tab]', form);
      if (tab && active) tab.value = active.dataset.tab;
    });
  });

  /* remember the open tab after saving */
  var hash = location.hash.replace('#', '');
  if (hash) {
    var tabBtn = $('.nav-link[data-tab="' + hash + '"]');
    if (tabBtn && window.bootstrap) new bootstrap.Tab(tabBtn).show();
  }

  /* picture picker */
  var pickerTarget = null;
  function openPicker(input) {
    var modalEl = $('#pickerModal');
    if (!modalEl || !window.bootstrap) { window.open('/admin/media.php', '_blank'); return; }
    pickerTarget = input;
    var frame = $('[data-picker-frame]', modalEl);
    if (!frame.src) frame.src = '/admin/media.php?pick=1';
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }
  window.addEventListener('message', function (e) {
    if (e.origin !== location.origin || !e.data || !e.data.pickImage || !pickerTarget) return;
    pickerTarget.value = e.data.pickImage;
    pickerTarget.dispatchEvent(new Event('input', { bubbles: true }));
    bootstrap.Modal.getOrCreateInstance($('#pickerModal')).hide();
  });
})();
