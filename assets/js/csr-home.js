/*!
 * Český svaz rychlobruslení — interakce úvodní stránky
 * Bez závislostí (žádná jQuery). ~7 kB. Vše je progresivní vylepšení:
 * bez JS zůstane stránka plně čitelná a použitelná.
 */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $  = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

  /**
   * Sundá diakritiku, ať "sablikova" najde "Sáblíková".
   * Starší prohlížeče bez String.normalize dostanou aspoň malá písmena.
   */
  function fold(text) {
    text = (text || '').toLowerCase();
    return text.normalize ? text.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : text;
  }

  /* ----------------------------------------------------------------------
     1. Odkrývání obsahu při scrollu
     ---------------------------------------------------------------------- */
  function initReveal() {
    var items = $$('.csr-reveal');
    if (!items.length) return;

    // Bez podpory pozorovatele nebo s vypnutými animacemi necháme obsah být —
    // ve výchozím stavu je viditelný, takže není co dohánět.
    if (reduceMotion || !('IntersectionObserver' in window)) return;

    // Teprve teď smíme obsah skrýt: víme, že ho umíme zase odkrýt.
    document.documentElement.classList.add('csr-anim');

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-in');
        io.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });

    items.forEach(function (el) { io.observe(el); });
  }

  /* Automatické stupňování prodlevy u sourozenců ve mřížce */
  function initStagger() {
    $$('[data-csr-stagger]').forEach(function (group) {
      var step = parseInt(group.getAttribute('data-csr-stagger'), 10) || 80;
      $$(':scope > *', group).forEach(function (child, i) {
        var target = child.classList.contains('csr-reveal') ? child : $('.csr-reveal', child);
        if (target) target.style.setProperty('--csr-delay', Math.min(i, 8) * step + 'ms');
      });
    });
  }

  /* ----------------------------------------------------------------------
     2. Chování hlavičky + ukazatel průběhu
     ---------------------------------------------------------------------- */
  function initHeader() {
    var header = $('.csr-header');
    var progress = $('.csr-progress');
    if (!header && !progress) return;

    var lastY = window.scrollY;
    var ticking = false;

    function update() {
      var y = window.scrollY;
      var docH = document.documentElement.scrollHeight - window.innerHeight;

      if (header) {
        header.classList.toggle('is-stuck', y > 24);
        // Skrýt jen při scrollu dolů a dostatečně nízko (nikdy s otevřeným menu)
        var menuOpen = document.body.classList.contains('csr-locked');
        var goingDown = y > lastY && y > 460;
        header.classList.toggle('is-hidden', goingDown && !menuOpen);
      }

      if (progress && docH > 0) {
        progress.style.setProperty('--csr-progress', Math.min(y / docH, 1).toFixed(4));
      }

      lastY = y;
      ticking = false;
    }

    window.addEventListener('scroll', function () {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(update);
    }, { passive: true });

    update();
  }

  /* ----------------------------------------------------------------------
     3. Mobilní menu
     ---------------------------------------------------------------------- */
  function initDrawer() {
    var drawer = $('.csr-drawer');
    var openBtn = $('[data-csr-open="drawer"]');
    var closeBtn = $('[data-csr-close="drawer"]');
    if (!drawer || !openBtn) return;

    function open() {
      drawer.classList.add('is-open');
      document.body.classList.add('csr-locked');
      openBtn.setAttribute('aria-expanded', 'true');
      var first = $('a, button', drawer);
      if (first) first.focus({ preventScroll: true });
    }
    function close() {
      drawer.classList.remove('is-open');
      document.body.classList.remove('csr-locked');
      openBtn.setAttribute('aria-expanded', 'false');
      openBtn.focus({ preventScroll: true });
    }

    openBtn.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);

    // Rozbalování podnabídek — kliknutí na rodiče otevře, druhé kliknutí projde na odkaz
    $$('.menu-item-has-children > a', drawer).forEach(function (link) {
      link.addEventListener('click', function (e) {
        var li = link.parentElement;
        var href = link.getAttribute('href');
        var isPlaceholder = !href || href === '#';
        if (!li.classList.contains('is-open') || isPlaceholder) {
          e.preventDefault();
          li.classList.toggle('is-open');
          link.setAttribute('aria-expanded', li.classList.contains('is-open') ? 'true' : 'false');
        }
      });
    });

    // Zavřít po kliknutí na koncový odkaz
    $$('a', drawer).forEach(function (a) {
      if (a.parentElement.classList.contains('menu-item-has-children')) return;
      a.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && drawer.classList.contains('is-open')) close();
    });
  }

  /* ----------------------------------------------------------------------
     4. Vyhledávání
     ---------------------------------------------------------------------- */
  function initSearch() {
    var box = $('.csr-search');
    var openBtn = $('[data-csr-open="search"]');
    if (!box || !openBtn) return;
    var input = $('.csr-search__input', box);

    function open() {
      box.classList.add('is-open');
      document.body.classList.add('csr-locked');
      openBtn.setAttribute('aria-expanded', 'true');
      window.setTimeout(function () { if (input) input.focus(); }, 60);
    }
    function close() {
      box.classList.remove('is-open');
      document.body.classList.remove('csr-locked');
      openBtn.setAttribute('aria-expanded', 'false');
    }

    openBtn.addEventListener('click', open);
    box.addEventListener('click', function (e) { if (e.target === box) close(); });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && box.classList.contains('is-open')) { close(); return; }
      // "/" otevře hledání, pokud uživatel zrovna nepíše do pole
      var tag = (document.activeElement && document.activeElement.tagName) || '';
      var typing = tag === 'INPUT' || tag === 'TEXTAREA' || document.activeElement.isContentEditable;
      if (e.key === '/' && !typing && !box.classList.contains('is-open')) {
        e.preventDefault();
        open();
      }
    });
  }

  /* ----------------------------------------------------------------------
     5. Přepínač světlý / tmavý režim
     ---------------------------------------------------------------------- */
  function initTheme() {
    var btn = $('[data-csr-toggle="theme"]');
    var root = document.documentElement;
    var KEY = 'csr-theme';

    var stored = null;
    try { stored = window.localStorage.getItem(KEY); } catch (err) { /* privátní režim */ }
    if (stored === 'dark' || stored === 'light') root.setAttribute('data-csr-theme', stored);

    if (!btn) return;
    btn.addEventListener('click', function () {
      var isDark = root.getAttribute('data-csr-theme') === 'dark' ||
        (!root.hasAttribute('data-csr-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
      var next = isDark ? 'light' : 'dark';
      root.setAttribute('data-csr-theme', next);
      btn.setAttribute('aria-label', next === 'dark' ? 'Přepnout na světlý režim' : 'Přepnout na tmavý režim');
      try { window.localStorage.setItem(KEY, next); } catch (err) { /* ignorovat */ }
    });
  }

  /* ----------------------------------------------------------------------
     6. Filtrování článků + načítání dalších
     Obojí sdílí jeden stav, aby se filtr a stránkování nepraly:
     článek je vidět, jen když sedí kategorie A zároveň už byl "načtený".
     ---------------------------------------------------------------------- */

  var currentCat = 'all';

  function applyFilter() {
    var grid = $('[data-csr-news]');
    if (!grid) return 0;
    var empty = $('.csr-news__empty', grid);
    var shown = 0;

    $$('.csr-news__item', grid).forEach(function (item) {
      // Dosud nenačtené články zůstávají skryté bez ohledu na filtr
      if (item.hasAttribute('data-csr-more')) { item.hidden = true; return; }
      var cats = (item.getAttribute('data-csr-cat') || '').split(/\s+/);
      var match = currentCat === 'all' || cats.indexOf(currentCat) !== -1;
      item.hidden = !match;
      if (match) shown++;
    });

    if (empty) empty.hidden = shown > 0;
    return shown;
  }

  function animateVisible() {
    if (reduceMotion) return;
    $$('.csr-news__item:not([hidden])').forEach(function (item) {
      item.classList.remove('is-filtering');
      void item.offsetWidth; // vynutí restart animace
      item.classList.add('is-filtering');
    });
  }

  function initFilters() {
    var bar = $('[data-csr-filters]');
    var grid = $('[data-csr-news]');
    if (!bar || !grid) return;

    var buttons = $$('.csr-filter', bar);
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        buttons.forEach(function (b) { b.setAttribute('aria-pressed', String(b === btn)); });
        currentCat = btn.getAttribute('data-csr-filter') || 'all';
        applyFilter();
        animateVisible();
      });
    });

    applyFilter();
  }

  /* ----------------------------------------------------------------------
     7. Načítání dalších článků
     ---------------------------------------------------------------------- */
  function initLoadMore() {
    var btn = $('[data-csr-loadmore]');
    var grid = $('[data-csr-news]');
    if (!btn || !grid) return;

    var endpoint = btn.getAttribute('data-csr-loadmore');
    var page = parseInt(btn.getAttribute('data-csr-page'), 10) || 1;
    var maxPages = parseInt(btn.getAttribute('data-csr-max'), 10) || 1;
    var label = btn.textContent;

    // Bez endpointu (statická ukázka) jen odkryjeme předpřipravené karty
    if (!endpoint) {
      btn.addEventListener('click', function () {
        $$('.csr-news__item[data-csr-more]', grid).slice(0, 4).forEach(function (item) {
          item.removeAttribute('data-csr-more');
        });
        applyFilter();
        animateVisible();
        if (!$('.csr-news__item[data-csr-more]', grid)) btn.hidden = true;
      });
      return;
    }

    btn.addEventListener('click', function () {
      if (btn.disabled) return;
      btn.disabled = true;
      btn.textContent = 'Načítám…';

      fetch(endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + 'csr_page=' + (page + 1), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      })
        .then(function (r) {
          if (!r.ok) throw new Error('HTTP ' + r.status);
          return r.text();
        })
        .then(function (html) {
          var tmp = document.createElement('div');
          tmp.innerHTML = html.trim();
          var added = $$('.csr-news__item', tmp);
          added.forEach(function (item, i) {
            item.style.setProperty('--csr-delay', i * 70 + 'ms');
            grid.appendChild(item);
          });
          page += 1;
          btn.setAttribute('data-csr-page', String(page));
          btn.disabled = false;
          btn.textContent = label;
          if (page >= maxPages || !added.length) btn.hidden = true;
          applyFilter();
          animateVisible();
          initReveal();
        })
        .catch(function () {
          btn.disabled = false;
          btn.textContent = 'Zkusit znovu';
        });
    });
  }

  /* ----------------------------------------------------------------------
     8. Animovaná počítadla
     ---------------------------------------------------------------------- */
  function initCounters() {
    var nodes = $$('[data-csr-count]');
    if (!nodes.length) return;

    function run(el) {
      var target = parseFloat(el.getAttribute('data-csr-count')) || 0;
      var decimals = (el.getAttribute('data-csr-decimals') | 0);
      var duration = parseInt(el.getAttribute('data-csr-duration'), 10) || 1500;

      // Roky apod. se nesmí formátovat s oddělovačem tisíců (1 993)
      var grouping = !el.hasAttribute('data-csr-nogroup');
      var fmt = { minimumFractionDigits: decimals, maximumFractionDigits: decimals, useGrouping: grouping };

      if (reduceMotion) {
        el.textContent = target.toLocaleString('cs-CZ', fmt);
        return;
      }

      var start = null;
      function step(ts) {
        if (start === null) start = ts;
        var p = Math.min((ts - start) / duration, 1);
        var eased = 1 - Math.pow(1 - p, 3); // easeOutCubic
        el.textContent = (target * eased).toLocaleString('cs-CZ', fmt);
        if (p < 1) window.requestAnimationFrame(step);
      }
      window.requestAnimationFrame(step);
    }

    if (!('IntersectionObserver' in window)) { nodes.forEach(run); return; }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        run(entry.target);
        io.unobserve(entry.target);
      });
    }, { threshold: 0.5 });

    nodes.forEach(function (el) { el.textContent = '0'; io.observe(el); });
  }

  /* ----------------------------------------------------------------------
     9. Medailové pruhy
     ---------------------------------------------------------------------- */
  function initBars() {
    var bars = $$('.csr-medal__fill');
    if (!bars.length) return;
    if (reduceMotion || !('IntersectionObserver' in window)) {
      bars.forEach(function (b) { b.classList.add('is-in'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-in');
        io.unobserve(entry.target);
      });
    }, { threshold: 0.4 });
    bars.forEach(function (b, i) {
      b.style.setProperty('--csr-delay', i * 140 + 'ms');
      io.observe(b);
    });
  }

  /* ----------------------------------------------------------------------
     10. Nekonečné pásy (info lišta, partneři)
     ---------------------------------------------------------------------- */
  function initMarquees() {
    if (reduceMotion) return;
    $$('[data-csr-marquee]').forEach(function (track) {
      // Zdvojíme obsah, aby posun o -50 % navazoval bez skoku
      if (track.getAttribute('data-csr-cloned') === '1') return;
      track.innerHTML += track.innerHTML;
      track.setAttribute('data-csr-cloned', '1');
      track.setAttribute('aria-hidden', 'false');
    });
  }

  /* ----------------------------------------------------------------------
     11. Zpět nahoru
     ---------------------------------------------------------------------- */
  function initBackToTop() {
    var btn = $('.csr-top');
    if (!btn) return;
    var ticking = false;
    window.addEventListener('scroll', function () {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(function () {
        btn.classList.toggle('is-visible', window.scrollY > window.innerHeight * 0.8);
        ticking = false;
      });
    }, { passive: true });
    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    });
  }

  /* ----------------------------------------------------------------------
     12. Jemný parallax v hero sekci
     ---------------------------------------------------------------------- */
  function initParallax() {
    var media = $('.csr-hero__media, .csr-hero__fallback');
    var hero = $('.csr-hero');
    if (!media || !hero || reduceMotion) return;
    if (window.matchMedia('(max-width: 860px)').matches) return;

    var ticking = false;
    window.addEventListener('scroll', function () {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(function () {
        var y = window.scrollY;
        if (y < hero.offsetHeight) {
          media.style.transform = 'translate3d(0,' + (y * 0.28).toFixed(1) + 'px,0)';
        }
        ticking = false;
      });
    }, { passive: true });
  }


  /* ----------------------------------------------------------------------
     13. InfoFeed — hledání a filtrování podle zdroje
     Obojí ovlivňuje stejný seznam, proto se vyhodnocuje najednou.
     ---------------------------------------------------------------------- */
  function initFeed() {
    var grid = $('[data-csr-feed]');
    if (!grid) return;

    var items = $$('.csr-feeditem', grid);
    var empty = $('.csr-feed__empty', grid);
    var box = $('[data-csr-feedsearch]');
    var input = box ? $('input', box) : null;
    var clear = box ? $('.csr-feedsearch__clear', box) : null;
    var buttons = $$('[data-csr-feedfilters] .csr-filter');

    var source = 'all';
    var query = '';

    // Text položek přeložíme jednou dopředu, ne při každém stisku klávesy.
    var haystacks = items.map(function (item) {
      return fold(item.getAttribute('data-csr-text'));
    });

    function apply() {
      var shown = 0;

      items.forEach(function (item, i) {
        var srcs = (item.getAttribute('data-csr-source') || '').split(/\s+/);
        var okSource = source === 'all' || srcs.indexOf(source) !== -1;
        var okText = !query || haystacks[i].indexOf(query) !== -1;
        var show = okSource && okText;
        item.hidden = !show;
        if (show) shown++;
      });

      if (empty) empty.hidden = shown > 0;
      if (box) box.classList.toggle('has-value', !!query);
    }

    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        buttons.forEach(function (b) { b.setAttribute('aria-pressed', String(b === btn)); });
        source = btn.getAttribute('data-csr-filter') || 'all';
        apply();
      });
    });

    if (input) {
      input.addEventListener('input', function () {
        query = fold(input.value.trim());
        apply();
      });
    }
    if (clear) {
      clear.addEventListener('click', function () {
        input.value = '';
        query = '';
        apply();
        input.focus();
      });
    }

    apply();
  }

  /* ---------------------------------------------------------------------- */

  /* ══════════════════════════════════════════════════════════════════
     LIGHTBOX
     Na původním webu byly navázané dva najednou — Simple Lightbox
     i ten Elementorův. Tenhle je jediný a nepotřebuje jQuery.
     ═══════════════════════════════════════════════════════════════ */
  function initLightbox() {
    var gallery = document.querySelector('[data-csr-gallery]');
    if (!gallery) { return; }

    var shots = $$('[data-csr-shot]', gallery);
    if (!shots.length) { return; }

    var box, img, caption, counter, prevBtn, nextBtn, closeBtn;
    var index = 0;
    var lastFocus = null;

    function build() {
      box = document.createElement('div');
      box.className = 'csr-lb';
      box.setAttribute('role', 'dialog');
      box.setAttribute('aria-modal', 'true');
      box.setAttribute('aria-label', 'Prohlížeč fotek');
      box.hidden = true;
      box.innerHTML =
        '<button type="button" class="csr-lb__close" aria-label="Zavřít">&times;</button>' +
        '<button type="button" class="csr-lb__nav csr-lb__nav--prev" aria-label="Předchozí fotka">&#8249;</button>' +
        '<button type="button" class="csr-lb__nav csr-lb__nav--next" aria-label="Další fotka">&#8250;</button>' +
        '<figure class="csr-lb__figure">' +
          '<img class="csr-lb__img" alt="">' +
          '<figcaption class="csr-lb__caption"></figcaption>' +
        '</figure>' +
        '<p class="csr-lb__counter" aria-live="polite"></p>';
      document.body.appendChild(box);

      img      = box.querySelector('.csr-lb__img');
      caption  = box.querySelector('.csr-lb__caption');
      counter  = box.querySelector('.csr-lb__counter');
      prevBtn  = box.querySelector('.csr-lb__nav--prev');
      nextBtn  = box.querySelector('.csr-lb__nav--next');
      closeBtn = box.querySelector('.csr-lb__close');

      closeBtn.addEventListener('click', close);
      prevBtn.addEventListener('click', function () { go(-1); });
      nextBtn.addEventListener('click', function () { go(1); });
      box.addEventListener('click', function (e) {
        // Kliknutí mimo fotku zavírá, kliknutí na fotku ne.
        if (e.target === box || e.target.classList.contains('csr-lb__figure')) { close(); }
      });
    }

    function show(i) {
      index = (i + shots.length) % shots.length;
      var btn = shots[index];
      var text = btn.getAttribute('data-csr-caption') || '';

      img.src = btn.getAttribute('data-csr-full');
      // Popis je zároveň alt i titulek — jiný text pro obojí nemáme a vymýšlet si ho nebudeme.
      img.alt = text;
      caption.textContent = text;
      caption.hidden = !text;
      counter.textContent = (index + 1) + ' / ' + shots.length;
    }

    function open(i) {
      if (!box) { build(); }
      lastFocus = document.activeElement;
      show(i);
      box.hidden = false;
      document.body.classList.add('csr-lb-open');
      closeBtn.focus();
    }

    function close() {
      box.hidden = true;
      document.body.classList.remove('csr-lb-open');
      if (lastFocus) { lastFocus.focus(); }
    }

    function go(step) { show(index + step); }

    shots.forEach(function (btn, i) {
      btn.addEventListener('click', function () { open(i); });
    });

    document.addEventListener('keydown', function (e) {
      if (!box || box.hidden) { return; }
      if (e.key === 'Escape') { close(); }
      else if (e.key === 'ArrowLeft') { go(-1); }
      else if (e.key === 'ArrowRight') { go(1); }
      else if (e.key === 'Tab') {
        // Ohlídáme, aby fokus neutekl za dialog.
        var focusable = [closeBtn, prevBtn, nextBtn];
        var pos = focusable.indexOf(document.activeElement);
        e.preventDefault();
        focusable[(pos + (e.shiftKey ? -1 : 1) + focusable.length) % focusable.length].focus();
      }
    });
  }

  /* ══════════════════════════════════════════════════════════════════
     FILTR ALB
     ═══════════════════════════════════════════════════════════════ */
  function initAlbums() {
    initListFilter({
      grid: '[data-csr-albums]',
      filters: '[data-csr-albumfilters]',
      empty: '[data-csr-albumempty]'
    });
  }

  /* ---- Kopírování odkazu na článek ---- */
  function initCopyLink() {
    var buttons = $$('[data-csr-copy]');
    if (!buttons.length || !navigator.clipboard) return;

    buttons.forEach(function (btn) {
      var label = btn.querySelector('span');
      var original = label ? label.textContent : '';

      btn.addEventListener('click', function () {
        navigator.clipboard.writeText(btn.getAttribute('data-csr-copy')).then(function () {
          if (label) label.textContent = 'Zkopírováno';
          btn.classList.add('is-done');
          setTimeout(function () {
            if (label) label.textContent = original;
            btn.classList.remove('is-done');
          }, 2000);
        });
      });
    });
  }
  /* ---- Hledání a filtr nad seznamem (kluby, dokumenty) ---- */
  function initListFilter(opt) {
    var grid = $(opt.grid);
    if (!grid) return;

    var items = $$('[data-csr-item]', grid);
    var empty = $(opt.empty);
    var box = $(opt.search);
    var input = box ? $('input', box) : null;
    var buttons = $$(opt.filters + ' .csr-filter');

    var cat = 'all';
    var query = '';

    // Text přeložíme jednou dopředu, ne při každém stisku klávesy.
    var haystacks = items.map(function (item) {
      return fold(item.getAttribute('data-csr-text'));
    });

    function apply() {
      var shown = 0;
      items.forEach(function (item, i) {
        // data-csr-cat může nést víc rubrik oddělených mezerou (album bývá
        // ve dvou). Porovnání na přesnou shodu by takovou položku nikdy nenašlo.
        var cats = (item.getAttribute('data-csr-cat') || '').split(/\s+/);
        var okCat = cat === 'all' || cats.indexOf(cat) !== -1;
        var okText = !query || haystacks[i].indexOf(query) !== -1;
        var show = okCat && okText;
        item.hidden = !show;
        if (show) shown++;
      });
      if (empty) empty.hidden = shown > 0;
      if (box) box.classList.toggle('has-value', !!query);
    }

    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        buttons.forEach(function (b) { b.setAttribute('aria-pressed', String(b === btn)); });
        cat = btn.getAttribute('data-csr-filter') || 'all';
        apply();
      });
    });

    if (input) {
      input.addEventListener('input', function () {
        query = fold(input.value.trim());
        apply();
      });
      // Escape vyprázdní pole, ať se člověk nemusí trefovat do křížku.
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && input.value) {
          input.value = '';
          query = '';
          apply();
        }
      });
    }
  }

  /* ---- Řazení výsledkových tabulek ----
     Bez JS zůstane tabulka v pořadí, v jakém ji zadal správce — to je
     u výsledků to správné výchozí pořadí, takže se nic neztrácí. */

  /**
   * Český čas nebo číslo na porovnatelnou hodnotu.
   * "36,55" -> 36.55   "1.11,20" -> 71.2   "12.29,63" -> 749.63   "500 m" -> 500
   */
  function parseTime(text) {
    var raw = (text || '').replace(/\s/g, '');
    if (!raw) return null;

    var decimals = 0;
    var whole = raw;

    // Čárka je v češtině desetinná. Až za ní je zlomek sekundy, před ní
    // můžou být minuty oddělené tečkou nebo dvojtečkou.
    var comma = raw.lastIndexOf(',');
    if (comma !== -1) {
      whole = raw.slice(0, comma);
      decimals = parseFloat('0.' + raw.slice(comma + 1).replace(/\D/g, '')) || 0;
    }

    // Bez čárky bereme tečku jako desetinnou — "1.5" je půldruhá, ne 1:05.
    if (comma === -1) {
      var plain = parseFloat(whole.replace(/[^\d.-]/g, ''));
      return isNaN(plain) ? null : plain;
    }

    var parts = whole.split(/[.:]/).map(function (p) {
      return parseInt(p.replace(/\D/g, ''), 10) || 0;
    });
    var seconds = 0;
    parts.forEach(function (p) { seconds = seconds * 60 + p; });
    return seconds + decimals;
  }

  function initTables() {
    $$('[data-csr-table]').forEach(function (table) {
      var headers = $$('thead th', table);
      var body = $('tbody', table);
      if (!body || !headers.length) return;

      // Původní pořadí si pamatujeme, ať se k němu jde vrátit třetím kliknutím.
      var original = $$('tr', body);

      headers.forEach(function (th, index) {
        var type = th.getAttribute('data-csr-sort');
        if (!type) return;

        var label = th.textContent.trim();
        if (!label) return;

        // Tlačítko místo holého kliknutí na buňku — jinak se k řazení
        // nedostane nikdo, kdo ovládá web klávesnicí.
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'csr-table__sort';
        btn.textContent = label;
        th.textContent = '';
        th.appendChild(btn);
        th.setAttribute('aria-sort', 'none');

        btn.addEventListener('click', function () {
          var current = th.getAttribute('aria-sort');
          var dir = current === 'ascending' ? 'descending' : (current === 'descending' ? 'none' : 'ascending');

          headers.forEach(function (h) {
            if (h.hasAttribute('aria-sort')) h.setAttribute('aria-sort', 'none');
          });
          th.setAttribute('aria-sort', dir);

          if (dir === 'none') {
            original.forEach(function (tr) { body.appendChild(tr); });
            return;
          }

          var rows = $$('tr', body);
          var sign = dir === 'ascending' ? 1 : -1;

          rows.sort(function (a, b) {
            var ca = a.children[index], cb = b.children[index];
            var ta = ca ? ca.textContent.trim() : '';
            var tb = cb ? cb.textContent.trim() : '';

            if (type === 'num') {
              var na = parseTime(ta), nb = parseTime(tb);
              // Prázdné buňky a "DNF" patří vždy na konec, ať se řadí jakkoli.
              if (na === null && nb === null) return 0;
              if (na === null) return 1;
              if (nb === null) return -1;
              return (na - nb) * sign;
            }
            return ta.localeCompare(tb, 'cs') * sign;
          });

          rows.forEach(function (tr) { body.appendChild(tr); });
        });
      });
    });
  }

  function boot() {


    initStagger();
    initReveal();
    initHeader();
    initDrawer();
    initSearch();
    initTheme();
    initFilters();
    initFeed();
    initLoadMore();
    initCounters();
    initBars();
    initMarquees();
    initBackToTop();
  initCopyLink();
  initListFilter({
    grid: '[data-csr-clubs]',
    search: '[data-csr-clubsearch]',
    filters: '[data-csr-clubfilters]',
    empty: '[data-csr-empty]'
  });
    initListFilter({
      grid: '[data-csr-docs]',
      search: '[data-csr-docsearch]',
      filters: '[data-csr-docfilters]',
      empty: '[data-csr-docempty]'
    });
    initListFilter({
      grid: '[data-csr-records]',
      search: '[data-csr-recsearch]',
      filters: '[data-csr-recfilters]',
      empty: '[data-csr-recempty]'
    });
    initTables();
    initLightbox();
    initAlbums();
    initParallax();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
