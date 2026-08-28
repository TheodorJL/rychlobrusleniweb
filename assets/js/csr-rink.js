/**
 * Ovál v hero sekci úvodní stránky.
 *
 * Čtyřistametrová dráha ve třech rozměrech, po ní jezdí světelné stopy —
 * v zatáčkách se naklánějí dovnitř jako bruslař. Kreslí se přímo přes
 * WebGL, bez knihovny: tahle animace je ozdoba a nemá cenu, aby si kvůli
 * ní úvodní stránka stáhla další půlmegabajt.
 *
 * Nejede na mobilu, při „prefers-reduced-motion", bez WebGL, mimo obraz
 * ani na skryté záložce.
 */
(function () {
  'use strict';

  var canvas = document.querySelector('[data-csr-rink]');
  if (!canvas) { return; }

  var tichyRezim = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (tichyRezim) { return; }

  // Šířku hlídáme průběžně, ne jen při načtení — okno se dá roztáhnout
  // a tablet otočit. Pod tuhle mez plátno stejně schovává CSS.
  var siroko = window.matchMedia ? window.matchMedia('(min-width: 1100px)') : { matches: true };

  var gl = canvas.getContext('webgl', { alpha: true, antialias: true, premultipliedAlpha: false })
        || canvas.getContext('experimental-webgl', { alpha: true, antialias: true });
  if (!gl) { return; }

  /* ── Rozměry dráhy (metry, jako doopravdy) ───────────────────────── */
  var R = 25.5;                       // poloměr vnitřní zatáčky
  var L = 111.94;                     // délka rovinky
  var OKRUH = 2 * L + 2 * Math.PI * R;

  /**
   * Bod na oválu podle ujeté vzdálenosti.
   * Vrací polohu, směr a znaménko zatáčky (0 na rovince).
   */
  function naOvalu(s, odsazeni) {
    s = ((s % OKRUH) + OKRUH) % OKRUH;
    var r = R + odsazeni;
    var zatacka = Math.PI * R;

    if (s < L) {                                   // rovinka dole
      return { x: -L / 2 + s, z: -r, tx: 1, tz: 0, k: 0 };
    }
    s -= L;
    if (s < zatacka) {                             // zatáčka vpravo
      var a = s / R;
      return { x: L / 2 + r * Math.sin(a), z: -r * Math.cos(a),
               tx: Math.cos(a), tz: Math.sin(a), k: 1 };
    }
    s -= zatacka;
    if (s < L) {                                   // rovinka nahoře
      return { x: L / 2 - s, z: r, tx: -1, tz: 0, k: 0 };
    }
    s -= L;
    var b = s / R;                                 // zatáčka vlevo
    return { x: -L / 2 - r * Math.sin(b), z: r * Math.cos(b),
             tx: -Math.cos(b), tz: -Math.sin(b), k: 1 };
  }

  /* ── Matice ──────────────────────────────────────────────────────── */
  function perspektiva(zorne, pomer, blizko, daleko) {
    var f = 1 / Math.tan(zorne / 2), n = 1 / (blizko - daleko);
    return [f / pomer, 0, 0, 0,
            0, f, 0, 0,
            0, 0, (daleko + blizko) * n, -1,
            0, 0, 2 * daleko * blizko * n, 0];
  }

  function pohled(oko, cil) {
    var zx = oko[0] - cil[0], zy = oko[1] - cil[1], zz = oko[2] - cil[2];
    var d = Math.hypot(zx, zy, zz); zx /= d; zy /= d; zz /= d;
    // vzhůru = (0,1,0)
    var xx = zz * 1 - zy * 0, xy = zx * 0 - zz * 0, xz = zy * 0 - zx * 1;
    var e = Math.hypot(xx, xy, xz) || 1; xx /= e; xy /= e; xz /= e;
    var yx = zy * xz - zz * xy, yy = zz * xx - zx * xz, yz = zx * xy - zy * xx;
    return [xx, yx, zx, 0,
            xy, yy, zy, 0,
            xz, yz, zz, 0,
            -(xx * oko[0] + xy * oko[1] + xz * oko[2]),
            -(yx * oko[0] + yy * oko[1] + yz * oko[2]),
            -(zx * oko[0] + zy * oko[1] + zz * oko[2]), 1];
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

  /* ── Shadery ─────────────────────────────────────────────────────── */
  var VS =
    'attribute vec3 aPos; attribute vec4 aCol; attribute float aSize;' +
    'uniform mat4 uMVP; varying vec4 vCol;' +
    'void main(){ vCol = aCol; gl_Position = uMVP * vec4(aPos, 1.0);' +
    ' gl_PointSize = aSize / max(gl_Position.w, 1.0) * 260.0; }';

  var FS =
    'precision mediump float; varying vec4 vCol; uniform float uKulate;' +
    'void main(){ float a = vCol.a;' +
    ' if (uKulate > 0.5) { float d = length(gl_PointCoord - 0.5);' +
    '   if (d > 0.5) discard; a *= smoothstep(0.5, 0.06, d); }' +
    ' gl_FragColor = vec4(vCol.rgb * a, a); }';

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
  var aSize = gl.getAttribLocation(prog, 'aSize');
  var uMVP = gl.getUniformLocation(prog, 'uMVP');
  var uKulate = gl.getUniformLocation(prog, 'uKulate');

  /* ── Statická dráha ──────────────────────────────────────────────
     Kreslí se jako tenké pásy, ne jako čáry: čára z WebGL je vždycky
     jeden pixel a při vysokém rozlišení z ní zbude nitka, která zanikne.
     Pás má šířku v metrech, takže se chová jako všechno ostatní.        */
  var caryPole = [];

  function vrchol(x, z, r, g, b, a) {
    caryPole.push(x, 0, z, r, g, b, a, 0);
  }

  /** Obdélník mezi dvěma body o zadané polovině šířky. */
  function pridejPas(x1, z1, x2, z2, polovina, r, g, b, a) {
    var dx = x2 - x1, dz = z2 - z1;
    var d = Math.hypot(dx, dz);
    if (!d) { return; }
    var nx = -dz / d * polovina, nz = dx / d * polovina;
    vrchol(x1 - nx, z1 - nz, r, g, b, a); vrchol(x1 + nx, z1 + nz, r, g, b, a); vrchol(x2 - nx, z2 - nz, r, g, b, a);
    vrchol(x2 - nx, z2 - nz, r, g, b, a); vrchol(x1 + nx, z1 + nz, r, g, b, a); vrchol(x2 + nx, z2 + nz, r, g, b, a);
  }

  // Vnitřní a vnější dráha plus obrys ledu.
  [[0, .34, .55], [4, .26, .40], [-3.4, .18, .26]].forEach(function (par) {
    var odsazeni = par[0], polovina = par[1], sila = par[2];
    var krok = OKRUH / 220, predchozi = null;
    for (var s = 0; s <= OKRUH + krok; s += krok) {
      var bod = naOvalu(s, odsazeni);
      if (predchozi) { pridejPas(predchozi.x, predchozi.z, bod.x, bod.z, polovina, .42, .74, .95, sila); }
      predchozi = bod;
    }
  });

  // Příčky po dráze — ať je vidět, jak rychle se stopy pohybují.
  for (var m = 0; m < 40; m++) {
    var a1 = naOvalu(OKRUH * m / 40, -3.4), a2 = naOvalu(OKRUH * m / 40, 4);
    var vyrazna = m % 10 === 0;
    pridejPas(a1.x, a1.z, a2.x, a2.z, vyrazna ? .30 : .16, .42, .74, .95, vyrazna ? .5 : .13);
  }

  var caryBuf = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, caryBuf);
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(caryPole), gl.STATIC_DRAW);
  var caryPocet = caryPole.length / 8;

  /* ── Stopy ───────────────────────────────────────────────────────── */
  var BARVY = [
    [0.29, 0.76, 0.98],   // ledová
    [1.00, 1.00, 1.00],   // bílá
    [0.95, 0.69, 0.20],   // zlatá
    [0.88, 0.12, 0.15],   // červená
    [0.29, 0.76, 0.98]
  ];
  var DELKA = 58;         // kolik bodů si stopa pamatuje

  var stopy = BARVY.map(function (barva, i) {
    return {
      barva: barva,
      s: OKRUH * (i / BARVY.length),
      odsazeni: -1.6 + i * 1.5,
      rychlost: 13.5 + i * 1.15,
      historie: []
    };
  });

  var pasyBuf = gl.createBuffer();
  var pasyPole = new Float32Array(stopy.length * DELKA * 2 * 8);
  var hlavyBuf = gl.createBuffer();
  var hlavyPole = new Float32Array(stopy.length * 8);

  /* ── Vykreslení ──────────────────────────────────────────────────── */
  function nastavAtributy() {
    gl.vertexAttribPointer(aPos, 3, gl.FLOAT, false, 32, 0);
    gl.vertexAttribPointer(aCol, 4, gl.FLOAT, false, 32, 12);
    gl.vertexAttribPointer(aSize, 1, gl.FLOAT, false, 32, 28);
    gl.enableVertexAttribArray(aPos);
    gl.enableVertexAttribArray(aCol);
    gl.enableVertexAttribArray(aSize);
  }

  var sirka = 0, vyska = 0;

  function zmerZmen() {
    var pomer = Math.min(window.devicePixelRatio || 1, 2);
    var w = Math.round(canvas.clientWidth * pomer);
    var h = Math.round(canvas.clientHeight * pomer);
    if (w !== sirka || h !== vyska) {
      sirka = canvas.width = w;
      vyska = canvas.height = h;
      gl.viewport(0, 0, sirka, vyska);
    }
  }

  var bezi = false, cas = 0, posledni = 0;
  var vidime = true, naObrazovce = true;

  function snimek(ted) {
    if (!bezi) { return; }
    var dt = posledni ? Math.min((ted - posledni) / 1000, 0.05) : 0.016;
    posledni = ted;
    cas += dt;

    zmerZmen();
    if (!sirka || !vyska) { requestAnimationFrame(snimek); return; }

    // Kamera zvolna obchází ovál a mírně se houpe.
    /*
     * Díváme se od kraje oválu podél jeho délky — dráha pak ubíhá do dálky
     * a vyplní i vysoké plátno. Kamera se kolem toho směru zvolna houpe.
     */
    var uhel = Math.PI / 2 + Math.sin(cas * 0.08) * 0.20;
    var polomer = 141;
    var oko = [Math.sin(uhel) * polomer, 34 + Math.sin(cas * 0.21) * 4, Math.cos(uhel) * polomer];
    var mvp = nasob(perspektiva(0.85, sirka / vyska, 1, 900), pohled(oko, [0, 0, 0]));

    gl.clearColor(0, 0, 0, 0);
    gl.clear(gl.COLOR_BUFFER_BIT);
    gl.enable(gl.BLEND);
    // Barva ze shaderu je předem vynásobená alfou, takže se jen přičítá.
    gl.blendFunc(gl.ONE, gl.ONE);
    gl.uniformMatrix4fv(uMVP, false, mvp);

    // Dráha
    gl.uniform1f(uKulate, 0);
    gl.bindBuffer(gl.ARRAY_BUFFER, caryBuf);
    nastavAtributy();
    gl.drawArrays(gl.TRIANGLES, 0, caryPocet);

    // Stopy
    var i = 0, h = 0;
    stopy.forEach(function (stopa) {
      // Na rovince se zrychluje, v zatáčce ubírá — jako doopravdy.
      var bod = naOvalu(stopa.s, stopa.odsazeni);
      stopa.s += stopa.rychlost * (bod.k ? 0.88 : 1.12) * dt;
      stopa.historie.unshift({ x: bod.x, z: bod.z, tx: bod.tx, tz: bod.tz, k: bod.k });
      if (stopa.historie.length > DELKA) { stopa.historie.length = DELKA; }

      for (var n = 0; n < DELKA; n++) {
        var b = stopa.historie[Math.min(n, stopa.historie.length - 1)];
        var podil = 1 - n / DELKA;
        var sila = podil * podil * 0.92;
        var sirkaPasu = 0.7 + podil * 2.1;
        // Naklonění do zatáčky: vnější hrana pásu se zvedne.
        var naklon = b.k * podil * 4.4;
        var nx = -b.tz, nz = b.tx;
        pasyPole[i++] = b.x - nx * sirkaPasu; pasyPole[i++] = 0;      pasyPole[i++] = b.z - nz * sirkaPasu;
        pasyPole[i++] = stopa.barva[0]; pasyPole[i++] = stopa.barva[1]; pasyPole[i++] = stopa.barva[2]; pasyPole[i++] = sila * 0.35; pasyPole[i++] = 0;
        pasyPole[i++] = b.x + nx * sirkaPasu; pasyPole[i++] = naklon; pasyPole[i++] = b.z + nz * sirkaPasu;
        pasyPole[i++] = stopa.barva[0]; pasyPole[i++] = stopa.barva[1]; pasyPole[i++] = stopa.barva[2]; pasyPole[i++] = sila; pasyPole[i++] = 0;
      }

      var celo = stopa.historie[0];
      hlavyPole[h++] = celo.x; hlavyPole[h++] = 0.6; hlavyPole[h++] = celo.z;
      hlavyPole[h++] = stopa.barva[0]; hlavyPole[h++] = stopa.barva[1]; hlavyPole[h++] = stopa.barva[2];
      hlavyPole[h++] = 0.95; hlavyPole[h++] = 34;
    });

    gl.bindBuffer(gl.ARRAY_BUFFER, pasyBuf);
    gl.bufferData(gl.ARRAY_BUFFER, pasyPole, gl.DYNAMIC_DRAW);
    nastavAtributy();
    for (var j = 0; j < stopy.length; j++) {
      gl.drawArrays(gl.TRIANGLE_STRIP, j * DELKA * 2, DELKA * 2);
    }

    gl.uniform1f(uKulate, 1);
    gl.bindBuffer(gl.ARRAY_BUFFER, hlavyBuf);
    gl.bufferData(gl.ARRAY_BUFFER, hlavyPole, gl.DYNAMIC_DRAW);
    nastavAtributy();
    gl.drawArrays(gl.POINTS, 0, stopy.length);

    requestAnimationFrame(snimek);
  }

  /* ── Běží jen když je na co koukat ───────────────────────────────── */
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

  document.addEventListener('visibilitychange', function () {
    vidime = !document.hidden;
    prepocitej();
  });

  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (zaznamy) {
      zaznamy.forEach(function (z) { naObrazovce = z.isIntersecting; });
      prepocitej();
    }, { threshold: 0 }).observe(canvas);
  }

  if (siroko.addEventListener) {
    siroko.addEventListener('change', prepocitej);
  } else if (siroko.addListener) {
    siroko.addListener(prepocitej);
  }

  prepocitej();
}());
