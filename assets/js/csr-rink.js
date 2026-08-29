/**
 * Ovál v hero sekci úvodní stránky — dlouhá expozice.
 *
 * Po čtyřistametrové dráze jedou komety světla: protáhlé zářící jádro,
 * dlouhá stopa a za každou z nich zůstávají v ledu šikmé zářezy nožů,
 * střídavě vlevo a vpravo, jak je nechává skutečný bruslařský krok.
 * Vypadá to jako noční fotografie závodu na dlouhý čas — žádné figury,
 * jen světlo a led. Kamera střídá záběry s plynulými přejezdy.
 *
 * Kreslí se přímo přes WebGL, bez knihovny. Všechno je plochá záře,
 * takže stačí jediný program a žádná hloubka.
 *
 * Nejede na úzkém okně, při „prefers-reduced-motion", bez WebGL,
 * mimo obraz ani na skryté záložce.
 */
(function () {
  'use strict';

  var canvas = document.querySelector('[data-csr-rink]');
  if (!canvas) { return; }
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }

  var siroko = window.matchMedia ? window.matchMedia('(min-width: 1100px)') : { matches: true };

  // Přednásobená alfa: záře násobí barvu alfou už ve shaderu. Jinak by ji
  // prohlížeč při skládání do stránky vynásobil podruhé a ztmavla by.
  var gl = canvas.getContext('webgl', { alpha: true, antialias: true, premultipliedAlpha: true })
        || canvas.getContext('experimental-webgl', { alpha: true, antialias: true, premultipliedAlpha: true });
  if (!gl) { return; }

  /* ══════════════ Matice ══════════════ */
  function perspektiva(zorne, pomer, blizko, daleko) {
    var f = 1 / Math.tan(zorne / 2), n = 1 / (blizko - daleko);
    return new Float32Array([f / pomer,0,0,0, 0,f,0,0,
                             0,0,(daleko + blizko) * n,-1, 0,0,2 * daleko * blizko * n,0]);
  }
  function pohled(oko, cil) {
    var zx = oko[0] - cil[0], zy = oko[1] - cil[1], zz = oko[2] - cil[2];
    var d = Math.hypot(zx, zy, zz) || 1; zx /= d; zy /= d; zz /= d;
    var xx = zz, xy = 0, xz = -zx;
    var e = Math.hypot(xx, xy, xz) || 1; xx /= e; xy /= e; xz /= e;
    var yx = zy * xz - zz * xy, yy = zz * xx - zx * xz, yz = zx * xy - zy * xx;
    return new Float32Array([xx,yx,zx,0, xy,yy,zy,0, xz,yz,zz,0,
      -(xx*oko[0]+xy*oko[1]+xz*oko[2]), -(yx*oko[0]+yy*oko[1]+yz*oko[2]), -(zx*oko[0]+zy*oko[1]+zz*oko[2]), 1]);
  }
  function nasob(a, b) {
    var v = new Float32Array(16);
    for (var i = 0; i < 4; i++) {
      for (var j = 0; j < 4; j++) {
        v[i * 4 + j] = a[j] * b[i * 4] + a[4 + j] * b[i * 4 + 1] +
                       a[8 + j] * b[i * 4 + 2] + a[12 + j] * b[i * 4 + 3];
      }
    }
    return v;
  }

  /* ══════════════ Dráha ══════════════ */
  var R = 25.5, L = 111.94;
  var OKRUH = 2 * L + 2 * Math.PI * R;

  /** Bod na oválu: poloha, směr jízdy a znaménko zatáčky. */
  function naOvalu(s, odsazeni) {
    s = ((s % OKRUH) + OKRUH) % OKRUH;
    var r = R + odsazeni, zatacka = Math.PI * R;
    if (s < L) { return { x: -L / 2 + s, z: -r, tx: 1, tz: 0, k: 0 }; }
    s -= L;
    if (s < zatacka) {
      var a = s / R;
      return { x: L / 2 + r * Math.sin(a), z: -r * Math.cos(a), tx: Math.cos(a), tz: Math.sin(a), k: 1 };
    }
    s -= zatacka;
    if (s < L) { return { x: L / 2 - s, z: r, tx: -1, tz: 0, k: 0 }; }
    s -= L;
    var b = s / R;
    return { x: -L / 2 - r * Math.sin(b), z: r * Math.cos(b), tx: -Math.cos(b), tz: -Math.sin(b), k: 1 };
  }

  /* ══════════════ Program ══════════════ */
  var VS =
    'attribute vec3 aPos; attribute vec4 aCol; uniform mat4 uMVP; varying vec4 vCol;' +
    'void main(){ vCol = aCol; gl_Position = uMVP * vec4(aPos, 1.0); }';
  var FS =
    'precision mediump float; varying vec4 vCol;' +
    'void main(){ gl_FragColor = vec4(vCol.rgb * vCol.a, vCol.a); }';

  function shader(typ, zdroj) {
    var s = gl.createShader(typ);
    gl.shaderSource(s, zdroj); gl.compileShader(s);
    return gl.getShaderParameter(s, gl.COMPILE_STATUS) ? s : null;
  }
  var vs = shader(gl.VERTEX_SHADER, VS), fs = shader(gl.FRAGMENT_SHADER, FS);
  if (!vs || !fs) { return; }
  var prog = gl.createProgram();
  gl.attachShader(prog, vs); gl.attachShader(prog, fs); gl.linkProgram(prog);
  if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) { return; }
  gl.useProgram(prog);
  var aPos = gl.getAttribLocation(prog, 'aPos');
  var aCol = gl.getAttribLocation(prog, 'aCol');
  var uMVP = gl.getUniformLocation(prog, 'uMVP');

  /* ══════════════ Statická dráha ══════════════ */
  var caryPole = [];
  function vrchol(pole, x, y, z, r, g, b, a) { pole.push(x, y, z, r, g, b, a); }
  function pridejPas(pole, x1, z1, x2, z2, pol, r, g, b, a) {
    var dx = x2 - x1, dz = z2 - z1, d = Math.hypot(dx, dz);
    if (!d) { return; }
    var nx = -dz / d * pol, nz = dx / d * pol;
    vrchol(pole, x1-nx, 0.01, z1-nz, r,g,b,a); vrchol(pole, x1+nx, 0.01, z1+nz, r,g,b,a); vrchol(pole, x2-nx, 0.01, z2-nz, r,g,b,a);
    vrchol(pole, x2-nx, 0.01, z2-nz, r,g,b,a); vrchol(pole, x1+nx, 0.01, z1+nz, r,g,b,a); vrchol(pole, x2+nx, 0.01, z2+nz, r,g,b,a);
  }
  [[0, .34, .5], [4, .26, .36], [-3.4, .18, .24]].forEach(function (par) {
    var krok = OKRUH / 220, predchozi = null;
    for (var s = 0; s <= OKRUH + krok; s += krok) {
      var bod = naOvalu(s, par[0]);
      if (predchozi) { pridejPas(caryPole, predchozi.x, predchozi.z, bod.x, bod.z, par[1], .42, .74, .95, par[2]); }
      predchozi = bod;
    }
  });
  for (var m0 = 0; m0 < 40; m0++) {
    var a1 = naOvalu(OKRUH * m0 / 40, -3.4), a2 = naOvalu(OKRUH * m0 / 40, 4);
    var vyrazna = m0 % 10 === 0;
    pridejPas(caryPole, a1.x, a1.z, a2.x, a2.z, vyrazna ? .30 : .16, .42, .74, .95, vyrazna ? .45 : .12);
  }
  var caryBuf = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, caryBuf);
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(caryPole), gl.STATIC_DRAW);
  var caryPocet = caryPole.length / 7;

  /* ══════════════ Bruslaři — komety ══════════════ */
  var DRESY = [
    [0.34, 0.62, 0.98],   // ledová modř
    [0.92, 0.94, 0.99],   // bílá
    [0.95, 0.72, 0.25],   // zlatá
    [0.95, 0.24, 0.27],   // červená
    [0.52, 0.80, 0.98]    // světlý led
  ];
  var STOPA_DELKA = 44;   // bodů dlouhé stopy
  var ZAREZU = 26;        // kolik zářezů nožů si každý pamatuje

  var bruslari = DRESY.map(function (dres, i) {
    return {
      dres: dres,
      jadro: [dres[0] * 0.35 + 0.62, dres[1] * 0.35 + 0.63, dres[2] * 0.35 + 0.64],
      s: OKRUH * (i / DRESY.length) + i * 3,
      odsazeni: -1.8 + i * 1.6,
      rychlost: 10.6 + i * 0.55,
      ujeto: 0,            // vzdálenost od posledního zářezu
      strana: i % 2 ? 1 : -1,
      historie: [],
      zarezy: []
    };
  });

  var dynBuf = gl.createBuffer();
  var dynPole = new Float32Array(40000);

  /* ══════════════ Kamera ══════════════ */
  var ZABERY = [
    function (t, v) {                                   // za zády vedoucího
      var z = naOvalu(v.s - 7, v.odsazeni), c = naOvalu(v.s + 6, v.odsazeni);
      return { oko: [z.x, 1.5, z.z], cil: [c.x, 0.35, c.z], fov: 1.0 };
    },
    function (t, v) {                                   // od mantinelu
      var c = naOvalu(v.s, v.odsazeni);
      var b = naOvalu(v.s + 15, 9.5);
      return { oko: [b.x, 1.0, b.z], cil: [c.x, 0.3, c.z], fov: 0.95 };
    },
    function (t, v) {                                   // zepředu, kometa najíždí
      var p = naOvalu(v.s + 11, v.odsazeni - 1.2);
      var c = naOvalu(v.s, v.odsazeni);
      return { oko: [p.x, 1.1, p.z], cil: [c.x, 0.3, c.z], fov: 0.9 };
    },
    function (t, v) {                                   // zevnitř zatáčky
      var c = naOvalu(v.s, v.odsazeni);
      var vnitr = naOvalu(v.s + 4, -11);
      return { oko: [vnitr.x, 0.9, vnitr.z], cil: [c.x, 0.3, c.z], fov: 1.05 };
    },
    function (t, v) {                                   // šikmo shora, letí s ní
      var b = naOvalu(v.s - 4, v.odsazeni + 8);
      var c = naOvalu(v.s + 3, v.odsazeni);
      return { oko: [b.x, 5.5, b.z], cil: [c.x, 0.2, c.z], fov: 0.95 };
    },
    function (t, v) {                                   // odjezd: celý ovál
      var u = Math.PI / 2 + Math.sin(t * 0.09) * 0.22;
      return { oko: [Math.sin(u) * 141, 34, Math.cos(u) * 141], cil: [0, 2, 0], fov: 0.85 };
    }
  ];
  var ZABER_DELKA = 6.5, PREJEZD = 1.3;
  function hladce(t) { t = Math.min(Math.max(t, 0), 1); return t * t * (3 - 2 * t); }
  function kamera(t, vedouci) {
    var poradi = Math.floor(t / ZABER_DELKA);
    var uvnitr = t - poradi * ZABER_DELKA;
    var ted = ZABERY[poradi % ZABERY.length](t, vedouci);
    if (uvnitr > ZABER_DELKA - PREJEZD) {
      var pristi = ZABERY[(poradi + 1) % ZABERY.length](t, vedouci);
      var p = hladce((uvnitr - (ZABER_DELKA - PREJEZD)) / PREJEZD);
      return {
        oko: ted.oko.map(function (v, i) { return v + (pristi.oko[i] - v) * p; }),
        cil: ted.cil.map(function (v, i) { return v + (pristi.cil[i] - v) * p; }),
        fov: ted.fov + (pristi.fov - ted.fov) * p
      };
    }
    return ted;
  }

  /* ══════════════ Vykreslení ══════════════ */
  var sirka = 0, vyska = 0;
  function zmerZmen() {
    var pomer = Math.min(window.devicePixelRatio || 1, 2);
    var w = Math.round(canvas.clientWidth * pomer), h = Math.round(canvas.clientHeight * pomer);
    if (w !== sirka || h !== vyska) {
      sirka = canvas.width = w; vyska = canvas.height = h;
      gl.viewport(0, 0, sirka, vyska);
    }
  }

  var bezi = false, vidime = true, naObrazovce = true, cas = 0, posledni = 0;

  function snimek(ted) {
    if (!bezi) { return; }
    var dt = posledni ? Math.min((ted - posledni) / 1000, 0.05) : 0.016;
    posledni = ted; cas += dt;

    zmerZmen();
    if (!sirka || !vyska) { requestAnimationFrame(snimek); return; }

    /* — pohyb — */
    bruslari.forEach(function (b) {
      var bod = naOvalu(b.s, b.odsazeni);
      var tempo = bod.k ? 0.9 : 1.1;
      var ds = b.rychlost * tempo * dt;
      b.s += ds; b.ujeto += ds;
      b.bod = bod;

      b.historie.unshift({ x: bod.x, z: bod.z, tx: bod.tx, tz: bod.tz });
      if (b.historie.length > STOPA_DELKA) { b.historie.length = STOPA_DELKA; }

      // Zářez nože: šikmý řez střídavě vlevo a vpravo, jak vede krok.
      if (b.ujeto > 1.15) {
        b.ujeto = 0;
        b.strana = -b.strana;
        var uh = Math.atan2(bod.tz, bod.tx) + b.strana * 0.33;
        // Zářez leží vedle osy jízdy — kroky jdou střídavě vlevo a vpravo.
        var ox = -bod.tz * b.strana * 0.16, oz = bod.tx * b.strana * 0.16;
        b.zarezy.unshift({ x: bod.x + ox, z: bod.z + oz, dx: Math.cos(uh), dz: Math.sin(uh), zrozen: cas });
        if (b.zarezy.length > ZAREZU) { b.zarezy.length = ZAREZU; }
      }
    });

    var vedouci = bruslari[0];
    var k = kamera(cas, vedouci);
    var mvp = nasob(perspektiva(k.fov, sirka / vyska, 0.4, 900), pohled(k.oko, k.cil));

    gl.clearColor(0, 0, 0, 0);
    gl.clear(gl.COLOR_BUFFER_BIT);
    gl.enable(gl.BLEND);
    gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA);
    gl.uniformMatrix4fv(uMVP, false, mvp);

    function kresli(buf, pole, pocet, dynamicke) {
      gl.bindBuffer(gl.ARRAY_BUFFER, buf);
      if (dynamicke) { gl.bufferData(gl.ARRAY_BUFFER, pole, gl.DYNAMIC_DRAW); }
      gl.vertexAttribPointer(aPos, 3, gl.FLOAT, false, 28, 0);
      gl.vertexAttribPointer(aCol, 4, gl.FLOAT, false, 28, 12);
      gl.enableVertexAttribArray(aPos);
      gl.enableVertexAttribArray(aCol);
      gl.drawArrays(gl.TRIANGLES, 0, pocet);
    }

    kresli(caryBuf, null, caryPocet, false);

    /* — dynamická záře: zářezy, stopy, komety — */
    var d = [];

    /*
     * Měkký pás světla: uprostřed plná barva, k bočním okrajům do
     * ztracena. Pás s tvrdými hranami vypadal jako papírový proužek,
     * ne jako záře.
     */
    function quad(x1, z1, x2, z2, pol, y, c, a1q, a2q) {
      var dx = x2 - x1, dz = z2 - z1, dd = Math.hypot(dx, dz);
      if (!dd) { return; }
      var nx = -dz / dd * pol, nz = dx / dd * pol;
      var r = c[0], g = c[1], bb = c[2];
      // levá polovina: okraj (alfa 0) → střed
      vrchol(d, x1-nx, y, z1-nz, r, g, bb, 0);   vrchol(d, x1, y, z1, r, g, bb, a1q);      vrchol(d, x2-nx, y, z2-nz, r, g, bb, 0);
      vrchol(d, x2-nx, y, z2-nz, r, g, bb, 0);   vrchol(d, x1, y, z1, r, g, bb, a1q);      vrchol(d, x2, y, z2, r, g, bb, a2q);
      // pravá polovina: střed → okraj (alfa 0)
      vrchol(d, x1, y, z1, r, g, bb, a1q);       vrchol(d, x1+nx, y, z1+nz, r, g, bb, 0);  vrchol(d, x2, y, z2, r, g, bb, a2q);
      vrchol(d, x2, y, z2, r, g, bb, a2q);       vrchol(d, x1+nx, y, z1+nz, r, g, bb, 0);  vrchol(d, x2+nx, y, z2+nz, r, g, bb, 0);
    }

    bruslari.forEach(function (b) {
      // Zářezy nožů: krátké šikmé řezy, blednou několik sekund.
      b.zarezy.forEach(function (zr) {
        var stari = cas - zr.zrozen;
        var sila = Math.max(0, 1 - stari / 7) * 0.65;
        if (sila <= 0.01) { return; }
        quad(zr.x - zr.dx * 0.42, zr.z - zr.dz * 0.42,
             zr.x + zr.dx * 0.42, zr.z + zr.dz * 0.42, 0.08, 0.015, b.dres, sila * 0.4, sila);
      });

      // Dlouhá stopa: pás blednoucí dozadu; při pohledu podél délky
      // pohasíná, jinak by se dílky naskládaly na sebe.
      for (var n = 0; n < STOPA_DELKA - 1 && n < b.historie.length - 1; n++) {
        var h1 = b.historie[n], h2 = b.historie[n + 1];
        var p1 = 1 - n / STOPA_DELKA, p2 = 1 - (n + 1) / STOPA_DELKA;
        var vx = h1.x - k.oko[0], vz = h1.z - k.oko[2];
        var vd = Math.hypot(vx, vz) || 1;
        var bokem = Math.abs(vx * h1.tz - vz * h1.tx) / vd;
        var f = 0.02 + 0.98 * bokem;
        quad(h1.x, h1.z, h2.x, h2.z, 0.09 + p1 * 0.14, 0.02, b.dres,
             p1 * p1 * 0.7 * f, p2 * p2 * 0.7 * f);
      }

      // Světlo bez těla: protáhlé žhavé jádro těsně nad ledem…
      var bd = b.bod;
      for (var q2 = 0; q2 < 8; q2++) {
        var t1 = q2 / 8, t2 = (q2 + 1) / 8;
        quad(bd.x - bd.tx * t1 * 2.4, bd.z - bd.tz * t1 * 2.4,
             bd.x - bd.tx * t2 * 2.4, bd.z - bd.tz * t2 * 2.4,
             0.26 * (1 - t1 * 0.75), 0.04, b.jadro,
             (1 - t1) * (1 - t1), (1 - t2) * (1 - t2));
      }
      // …bíle horká skvrna přímo pod čelem…
      quad(bd.x - bd.tx * 0.35, bd.z - bd.tz * 0.35,
           bd.x + bd.tx * 0.35, bd.z + bd.tz * 0.35, 0.24, 0.03, b.jadro, 0.55, 1.0);
      // …a měkká kaluž světla kolem, ať je vidět z každého úhlu.
      quad(bd.x - bd.tx * 1.0, bd.z - bd.tz * 1.0, bd.x, bd.z, 0.8, 0.005, b.dres, 0.0, 0.45);
      quad(bd.x, bd.z, bd.x + bd.tx * 0.8, bd.z + bd.tz * 0.8, 0.75, 0.005, b.dres, 0.45, 0.0);
    });

    var dyn = new Float32Array(d);
    kresli(dynBuf, dyn, d.length / 7, true);

    requestAnimationFrame(snimek);
  }

  /* ══════════════ Běží jen když je na co koukat ══════════════ */
  function prepocitej() {
    var ma = siroko.matches && vidime && naObrazovce;
    if (ma && !bezi) {
      bezi = true; posledni = 0;
      canvas.classList.add('is-live');
      requestAnimationFrame(snimek);
    } else if (!ma) {
      bezi = false;
    }
  }
  document.addEventListener('visibilitychange', function () { vidime = !document.hidden; prepocitej(); });
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (z) {
      z.forEach(function (v) { naObrazovce = v.isIntersecting; });
      prepocitej();
    }, { threshold: 0 }).observe(canvas);
  }
  if (siroko.addEventListener) { siroko.addEventListener('change', prepocitej); }
  else if (siroko.addListener) { siroko.addListener(prepocitej); }
  prepocitej();
}());
