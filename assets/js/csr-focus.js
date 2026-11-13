/**
 * Výřez náhledu v editoru příspěvku.
 *
 * Fotky svazu mají všemožné formáty — na výšku z mobilu, panoramata ze
 * stadionu, čtverce ze sociálních sítí. Karty je ořezávají do pevného
 * poměru a bez nastavení se vždycky bere střed, takže z fotky často
 * zbyla hlava bez obličeje nebo prázdný led. Tady se klepnutím určí bod,
 * který musí zůstat vidět, a náhledy hned ukážou, jak dopadne ořez.
 *
 * Funguje v blokovém editoru i v klasickém. Hodnota je „x y" v procentech.
 */
(function () {
  'use strict';

  function init(box) {
    var stage = box.querySelector('.csr-focus__stage');
    var img = box.querySelector('.csr-focus__img');
    var dot = box.querySelector('.csr-focus__dot');
    var input = box.querySelector('input[name="csr_focus"]');
    var empty = box.querySelector('.csr-focus__empty');
    var hint = box.querySelector('.csr-focus__hint');
    var previews = box.querySelector('.csr-focus__previews');
    var reset = box.querySelector('.csr-focus__reset');
    var nahledy = box.querySelectorAll('.csr-focus__preview img');
    if (!stage || !img || !dot || !input) return;

    function hodnota() {
      var m = /^(\d+(?:\.\d+)?)\s+(\d+(?:\.\d+)?)$/.exec(input.value || '');
      return m ? [parseFloat(m[1]), parseFloat(m[2])] : [50, 50];
    }

    function vykresli() {
      var v = hodnota();
      dot.style.left = v[0] + '%';
      dot.style.top = v[1] + '%';
      dot.setAttribute('aria-valuetext', 'vodorovně ' + Math.round(v[0]) + ' %, svisle ' + Math.round(v[1]) + ' %');
      for (var i = 0; i < nahledy.length; i++) {
        nahledy[i].style.objectPosition = v[0] + '% ' + v[1] + '%';
      }
      if (reset) reset.hidden = !input.value;
    }

    // Blokový editor změnu v metaboxu sám nepozná a tlačítko Aktualizovat
    // by zůstalo neaktivní. Zápis do metadat příspěvek označí jako změněný.
    function oznacZmenu() {
      try {
        if (window.wp && wp.data && wp.data.select('core/editor')) {
          wp.data.dispatch('core/editor').editPost({ meta: { _csr_focus: input.value } });
        }
      } catch (e) { /* klasický editor — uloží se s formulářem */ }
    }

    function nastav(x, y) {
      x = Math.max(0, Math.min(100, Math.round(x * 10) / 10));
      y = Math.max(0, Math.min(100, Math.round(y * 10) / 10));
      input.value = x + ' ' + y;
      vykresli();
      oznacZmenu();
    }

    function podleUkazatele(e) {
      var r = img.getBoundingClientRect();
      if (!r.width || !r.height) return;
      nastav((e.clientX - r.left) / r.width * 100, (e.clientY - r.top) / r.height * 100);
    }

    stage.addEventListener('pointerdown', function (e) {
      e.preventDefault();
      stage.setPointerCapture(e.pointerId);
      podleUkazatele(e);
      dot.focus({ preventScroll: true });
    });
    stage.addEventListener('pointermove', function (e) {
      if (stage.hasPointerCapture(e.pointerId)) podleUkazatele(e);
    });

    // Šipkami po 2 %, se Shiftem po 10 %.
    dot.addEventListener('keydown', function (e) {
      var krok = e.shiftKey ? 10 : 2;
      var v = hodnota();
      var posun = { ArrowLeft: [-krok, 0], ArrowRight: [krok, 0], ArrowUp: [0, -krok], ArrowDown: [0, krok] }[e.key];
      if (!posun) return;
      e.preventDefault();
      nastav(v[0] + posun[0], v[1] + posun[1]);
    });

    if (reset) {
      reset.addEventListener('click', function () {
        input.value = '';
        vykresli();
        oznacZmenu();
      });
    }

    function ukazObrazek(src) {
      var ma = !!src;
      stage.hidden = !ma;
      if (previews) previews.hidden = !ma;
      if (hint) hint.hidden = !ma;
      if (empty) empty.hidden = ma;
      if (!ma || img.getAttribute('src') === src) return;
      img.setAttribute('src', src);
      for (var i = 0; i < nahledy.length; i++) nahledy[i].setAttribute('src', src);
    }

    // Blokový editor: sledovat, jaký náhledový obrázek je právě vybraný.
    if (window.wp && wp.data && wp.data.subscribe && wp.data.select('core/editor')) {
      var posledni;
      wp.data.subscribe(function () {
        var id = wp.data.select('core/editor').getEditedPostAttribute('featured_media');
        if (!id) {
          if (posledni !== 0) { posledni = 0; ukazObrazek(''); }
          return;
        }
        var core = wp.data.select('core');
        var media = core.getEntityRecord ? core.getEntityRecord('postType', 'attachment', id) : core.getMedia(id);
        if (!media) return; // ještě se načítá
        var velikosti = media.media_details && media.media_details.sizes;
        var src = (velikosti && velikosti.large && velikosti.large.source_url) || media.source_url;
        if (src !== posledni) { posledni = src; ukazObrazek(src); }
      });
    } else {
      // Klasický editor: obrázek v boxu „Náhledový obrázek" se mění bez obnovení stránky.
      var box2 = document.getElementById('postimagediv');
      if (box2 && window.MutationObserver) {
        new MutationObserver(function () {
          var nahled = box2.querySelector('img');
          ukazObrazek(nahled ? nahled.getAttribute('src') : '');
        }).observe(box2, { childList: true, subtree: true });
      }
    }

    vykresli();
  }

  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-csr-focus]'), init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
