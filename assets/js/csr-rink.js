/**
 * Ovál v hero sekci úvodní stránky.
 *
 * Čtyřistametrová dráha ve třech rozměrech a na ní bruslaři — kloubové
 * postavy s vlastním skluzovým cyklem, které se v zatáčkách naklánějí.
 * Kamera střídá několik záběrů: podél dráhy, za zády vedoucího, od
 * mantinelu, shora nad zatáčkou a zevnitř oválu.
 *
 * Kreslí se přímo přes WebGL, bez knihovny.
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

  var gl = canvas.getContext('webgl', { alpha: true, antialias: true, premultipliedAlpha: false, depth: true })
        || canvas.getContext('experimental-webgl', { alpha: true, antialias: true, depth: true });
  if (!gl) { return; }

  /* ══════════════ Matice ══════════════ */
  function jednotka() {
    return new Float32Array([1,0,0,0, 0,1,0,0, 0,0,1,0, 0,0,0,1]);
  }
  function nasob(a, b) {                      // vrací a·b (sloupcové pořadí)
    var v = new Float32Array(16);
    for (var i = 0; i < 4; i++) {
      for (var j = 0; j < 4; j++) {
        v[i * 4 + j] = a[j] * b[i * 4] + a[4 + j] * b[i * 4 + 1] +
                       a[8 + j] * b[i * 4 + 2] + a[12 + j] * b[i * 4 + 3];
      }
    }
    return v;
  }
  function posun(x, y, z) {
    var m = jednotka(); m[12] = x; m[13] = y; m[14] = z; return m;
  }
  function meritko(x, y, z) {
    var m = jednotka(); m[0] = x; m[5] = y; m[10] = z; return m;
  }
  function otocX(a) {
    var m = jednotka(), c = Math.cos(a), s = Math.sin(a);
    m[5] = c; m[6] = s; m[9] = -s; m[10] = c; return m;
  }
  function otocY(a) {
    var m = jednotka(), c = Math.cos(a), s = Math.sin(a);
    m[0] = c; m[2] = -s; m[8] = s; m[10] = c; return m;
  }
  function otocZ(a) {
    var m = jednotka(), c = Math.cos(a), s = Math.sin(a);
    m[0] = c; m[1] = s; m[4] = -s; m[5] = c; return m;
  }

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

  /* ══════════════ Shadery ══════════════ */

  // Postavy: plné těleso se světlem a obrysovým dosvitem.
  var VS_TELO =
    'attribute vec3 aPos; attribute vec3 aNor; attribute vec3 aCol;' +
    'uniform mat4 uMVP; varying vec3 vNor; varying vec3 vCol; varying vec3 vSmer;' +
    'void main(){ vNor = aNor; vCol = aCol; vec4 p = uMVP * vec4(aPos, 1.0);' +
    ' vSmer = normalize(vec3(p.xy, 1.2)); gl_Position = p; }';

  var FS_TELO =
    'precision mediump float; varying vec3 vNor; varying vec3 vCol; varying vec3 vSmer;' +
    'void main(){ vec3 n = normalize(vNor);' +
    ' float d = max(dot(n, normalize(vec3(0.42, 0.86, 0.30))), 0.0);' +
    ' float obrys = pow(1.0 - abs(dot(n, normalize(vSmer))), 2.2);' +
    ' vec3 c = vCol * (0.30 + 0.85 * d) + vec3(0.36, 0.68, 0.95) * obrys * 0.75;' +
    ' gl_FragColor = vec4(c, 1.0); }';

  // Dráha a stopy: ploché, sčítají se.
  var VS_SVIT =
    'attribute vec3 aPos; attribute vec4 aCol; uniform mat4 uMVP; varying vec4 vCol;' +
    'void main(){ vCol = aCol; gl_Position = uMVP * vec4(aPos, 1.0); }';

  var FS_SVIT =
    'precision mediump float; varying vec4 vCol;' +
    'void main(){ gl_FragColor = vec4(vCol.rgb * vCol.a, vCol.a); }';

  function shader(typ, zdroj) {
    var s = gl.createShader(typ);
    gl.shaderSource(s, zdroj); gl.compileShader(s);
    return gl.getShaderParameter(s, gl.COMPILE_STATUS) ? s : null;
  }
  function program(vsZdroj, fsZdroj) {
    var v = shader(gl.VERTEX_SHADER, vsZdroj), f = shader(gl.FRAGMENT_SHADER, fsZdroj);
    if (!v || !f) { return null; }
    var p = gl.createProgram();
    gl.attachShader(p, v); gl.attachShader(p, f); gl.linkProgram(p);
    return gl.getProgramParameter(p, gl.LINK_STATUS) ? p : null;
  }

  var progTelo = program(VS_TELO, FS_TELO);
  var progSvit = program(VS_SVIT, FS_SVIT);
  if (!progTelo || !progSvit) { return; }

  var teloLoc = {
    pos: gl.getAttribLocation(progTelo, 'aPos'),
    nor: gl.getAttribLocation(progTelo, 'aNor'),
    col: gl.getAttribLocation(progTelo, 'aCol'),
    mvp: gl.getUniformLocation(progTelo, 'uMVP')
  };
  var svitLoc = {
    pos: gl.getAttribLocation(progSvit, 'aPos'),
    col: gl.getAttribLocation(progSvit, 'aCol'),
    mvp: gl.getUniformLocation(progSvit, 'uMVP')
  };

  /* ══════════════ Kvádr ══════════════
     Postava se skládá z kvádrů. Jednotkový se vyrobí jednou a každý díl
     si ho natáhne a otočí vlastní maticí. */
  var KVADR_POS = [], KVADR_NOR = [];
  (function () {
    var steny = [
      [[ .5,-.5,-.5],[ .5, .5,-.5],[ .5, .5, .5],[ .5,-.5, .5], [ 1, 0, 0]],
      [[-.5,-.5, .5],[-.5, .5, .5],[-.5, .5,-.5],[-.5,-.5,-.5], [-1, 0, 0]],
      [[-.5, .5,-.5],[-.5, .5, .5],[ .5, .5, .5],[ .5, .5,-.5], [ 0, 1, 0]],
      [[-.5,-.5, .5],[-.5,-.5,-.5],[ .5,-.5,-.5],[ .5,-.5, .5], [ 0,-1, 0]],
      [[-.5,-.5, .5],[ .5,-.5, .5],[ .5, .5, .5],[-.5, .5, .5], [ 0, 0, 1]],
      [[ .5,-.5,-.5],[-.5,-.5,-.5],[-.5, .5,-.5],[ .5, .5,-.5], [ 0, 0,-1]]
    ];
    steny.forEach(function (st) {
      var n = st[4], p = [st[0], st[1], st[2], st[0], st[2], st[3]];
      p.forEach(function (v) {
        KVADR_POS.push(v[0], v[1], v[2]);
        KVADR_NOR.push(n[0], n[1], n[2]);
      });
    });
  }());
  var KVADR_VRCHOLU = KVADR_POS.length / 3;

  /* ══════════════ Bruslař ══════════════
     Postava stojí v počátku, dívá se na +X, vzhůru je +Y, vpravo +Z.
     Končetiny se nepočítají z úhlů, ale z bodů: řekne se, kde má být
     noha na ledě, a koleno se dopočítá. Nůž tak nikdy neplave ve vzduchu. */

  function hladce(t) { t = Math.min(Math.max(t, 0), 1); return t * t * (3 - 2 * t); }
  function mix(a, b, t) { return a + (b - a) * t; }

  var BARVA_KUZE = [0.86, 0.70, 0.58];
  var BARVA_OCEL = [0.80, 0.88, 0.96];
  var BARVA_BOTA = [0.05, 0.06, 0.09];

  /**
   * Matice kvádru nataženého mezi dvěma body (místní osa Y je podél kosti).
   */
  function kost(a, b, sirka, tloustka) {
    var dx = b[0]-a[0], dy = b[1]-a[1], dz = b[2]-a[2];
    var d = Math.hypot(dx, dy, dz) || 0.0001;
    var yx = dx/d, yy = dy/d, yz = dz/d;
    // Libovolná kolmice — vyhneme se té, která by byla rovnoběžná.
    var px = 0, py = 0, pz = 1;
    if (Math.abs(yz) > 0.9) { pz = 0; px = 1; }
    var xx = py*yz - pz*yy, xy = pz*yx - px*yz, xz = px*yy - py*yx;
    var e = Math.hypot(xx, xy, xz) || 1; xx/=e; xy/=e; xz/=e;
    var zx = xy*yz - xz*yy, zy = xz*yx - xx*yz, zz = xx*yy - xy*yx;
    return new Float32Array([
      xx*sirka, xy*sirka, xz*sirka, 0,
      yx*d,     yy*d,     yz*d,     0,
      zx*tloustka, zy*tloustka, zz*tloustka, 0,
      (a[0]+b[0])/2, (a[1]+b[1])/2, (a[2]+b[2])/2, 1
    ]);
  }

  /** Koleno mezi kyčlí a chodidlem — dopředu a mírně ven. */
  function koleno(kycel, chodidlo, stehno, lytko, ven) {
    var ux = chodidlo[0]-kycel[0], uy = chodidlo[1]-kycel[1], uz = chodidlo[2]-kycel[2];
    var d = Math.hypot(ux, uy, uz);
    var max = (stehno + lytko) * 0.985;
    if (d > max) { var k = max/d; ux*=k; uy*=k; uz*=k; d = max; }
    ux/=d; uy/=d; uz/=d;
    var cos = (stehno*stehno + d*d - lytko*lytko) / (2*stehno*d);
    var uhel = Math.acos(Math.min(Math.max(cos, -1), 1));
    // Směr, kterým koleno „vybočí": dopředu (+X) a trochu do strany.
    var fx = 1, fy = 0.35, fz = ven;
    var s = fx*ux + fy*uy + fz*uz;
    fx -= ux*s; fy -= uy*s; fz -= uz*s;
    var e = Math.hypot(fx, fy, fz) || 1; fx/=e; fy/=e; fz/=e;
    var c = Math.cos(uhel) * stehno, t = Math.sin(uhel) * stehno;
    return [kycel[0] + ux*c + fx*t, kycel[1] + uy*c + fy*t, kycel[2] + uz*c + fz*t];
  }

  var KYCEL_Y = 0.86, STEHNO = 0.45, LYTKO = 0.45, LED = 0.10;

  /**
   * Díly postavy pro danou fázi dvojkroku.
   *
   * @param {number} faze 0..1
   * @param {Array}  dres barva dresu
   */
  function postava(faze, dres) {
    var casti = [];
    function dil(m, c) { casti.push({ m: m, c: c }); }
    function kvadr(stred, sx, sy, sz, c) { dil(nasob(posun(stred[0], stred[1], stred[2]), meritko(sx, sy, sz)), c); }

    // Tělo se v rytmu odrazu mírně přenáší ze strany na stranu a klesá.
    var houpani = Math.sin(faze * Math.PI * 2) * 0.085;
    var pokles = Math.abs(Math.cos(faze * Math.PI * 2)) * 0.05;
    var kycleY = KYCEL_Y - pokles;
    var kycle = [0, kycleY, houpani];
    // Podle záznamu pohybu: záda pod zhruba pětatřiceti stupni, ramena
    // nad boky a vepředu, hlava zvednutá.
    var ramena = [0.50, kycleY + 0.28, houpani * 0.5];

    /* ── Trup ── */
    dil(kost(kycle, ramena, 0.34, 0.40), dres);
    dil(kost([kycle[0]+0.06, kycle[1]+0.14, kycle[2]], [ramena[0]-0.04, ramena[1]+0.14, ramena[2]], 0.30, 0.30), [0.85, 0.13, 0.16]);
    kvadr([kycle[0]-0.03, kycle[1]-0.02, kycle[2]], 0.28, 0.28, 0.40, dres);   // pánev

    /* ── Krk, hlava, přilba, brýle ── */
    var krk = [ramena[0] + 0.11, ramena[1] + 0.11, ramena[2]];
    var hlava = [ramena[0] + 0.24, ramena[1] + 0.24, ramena[2]];
    dil(kost(ramena, krk, 0.15, 0.15), BARVA_KUZE);
    kvadr(hlava, 0.23, 0.21, 0.20, BARVA_KUZE);
    kvadr([hlava[0] - 0.02, hlava[1] + 0.07, hlava[2]], 0.26, 0.13, 0.23, dres);
    kvadr([hlava[0] + 0.10, hlava[1] + 0.02, hlava[2]], 0.05, 0.06, 0.17, BARVA_OCEL);

    /* ── Paže: obě pokrčené vepředu, kmitají proti sobě ── */
    var svih = Math.sin(faze * Math.PI * 2);
    [[-1, svih], [1, -svih]].forEach(function (par) {
      var strana = par[0], f = par[1];
      var rameno = [ramena[0] - 0.04, ramena[1] + 0.02, ramena[2] + strana * 0.19];
      var loket = [ramena[0] + 0.20 + f * 0.10, ramena[1] - 0.16 + f * 0.05, ramena[2] + strana * 0.26];
      var ruka = [ramena[0] + 0.40 + f * 0.16, ramena[1] + 0.10 + f * 0.12, ramena[2] + strana * 0.10 - f * 0.14];
      dil(kost(rameno, loket, 0.115, 0.115), dres);
      dil(kost(loket, ruka, 0.095, 0.095), dres);
      kvadr(ruka, 0.10, 0.085, 0.085, BARVA_KUZE);
    });

    /* ── Nohy ── */
    [1, -1].forEach(function (strana, i) {
      var q = (faze + (i ? 0.5 : 0)) % 1;
      var chodidlo, naLedu;

      if (q < 0.58) {                     // skluz a odraz: dozadu a mírně ven
        var t = hladce(q / 0.58);
        chodidlo = [mix(0.10, -0.78, t), LED, strana * mix(0.12, 0.52, t)];
        naLedu = true;
      } else {                            // přenos: složí se pod tělo
        var u = hladce((q - 0.58) / 0.42);
        chodidlo = [mix(-0.78, 0.10, u), LED + Math.sin(u * Math.PI) * 0.26, strana * mix(0.52, 0.12, u)];
        naLedu = false;
      }

      var kycelB = [kycle[0] - 0.02, kycle[1] - 0.04, kycle[2] + strana * 0.15];
      var kolenoB = koleno(kycelB, chodidlo, STEHNO, LYTKO, strana * 0.5);

      dil(kost(kycelB, kolenoB, 0.20, 0.21), dres);
      dil(kost(kolenoB, chodidlo, 0.16, 0.17), dres);
      kvadr([chodidlo[0] + 0.02, chodidlo[1] + 0.01, chodidlo[2]], 0.28, 0.16, 0.15, BARVA_BOTA);
      kvadr([chodidlo[0] + 0.05, chodidlo[1] - 0.07, chodidlo[2]], 0.50, 0.05, 0.02, BARVA_OCEL);
      kvadr([chodidlo[0] + 0.26, chodidlo[1] - 0.03, chodidlo[2]], 0.05, 0.10, 0.03, BARVA_OCEL);
      if (naLedu) {
        kvadr([chodidlo[0] + 0.05, chodidlo[1] - 0.09, chodidlo[2]], 0.52, 0.02, 0.06, [0.30, 0.55, 0.75]);
      }
    });

    return casti;
  }

  /* ══════════════ Dráha a stopy ══════════════ */
  var caryPole = [];
  function vrchol(x, z, r, g, b, a) { caryPole.push(x, 0.01, z, r, g, b, a); }
  function pridejPas(x1, z1, x2, z2, polovina, r, g, b, a) {
    var dx = x2 - x1, dz = z2 - z1, d = Math.hypot(dx, dz);
    if (!d) { return; }
    var nx = -dz / d * polovina, nz = dx / d * polovina;
    vrchol(x1-nx, z1-nz, r,g,b,a); vrchol(x1+nx, z1+nz, r,g,b,a); vrchol(x2-nx, z2-nz, r,g,b,a);
    vrchol(x2-nx, z2-nz, r,g,b,a); vrchol(x1+nx, z1+nz, r,g,b,a); vrchol(x2+nx, z2+nz, r,g,b,a);
  }
  [[0, .34, .55], [4, .26, .40], [-3.4, .18, .26]].forEach(function (par) {
    var krok = OKRUH / 220, predchozi = null;
    for (var s = 0; s <= OKRUH + krok; s += krok) {
      var bod = naOvalu(s, par[0]);
      if (predchozi) { pridejPas(predchozi.x, predchozi.z, bod.x, bod.z, par[1], .42, .74, .95, par[2]); }
      predchozi = bod;
    }
  });
  for (var m = 0; m < 40; m++) {
    var a1 = naOvalu(OKRUH * m / 40, -3.4), a2 = naOvalu(OKRUH * m / 40, 4);
    var vyrazna = m % 10 === 0;
    pridejPas(a1.x, a1.z, a2.x, a2.z, vyrazna ? .30 : .16, .42, .74, .95, vyrazna ? .5 : .13);
  }
  var caryBuf = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, caryBuf);
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(caryPole), gl.STATIC_DRAW);
  var caryPocet = caryPole.length / 7;

  var DRESY = [
    [0.09, 0.22, 0.42], [0.80, 0.12, 0.15], [0.86, 0.62, 0.16],
    [0.14, 0.42, 0.58], [0.72, 0.74, 0.80]
  ];
  var STOPA_DELKA = 44;

  var bruslari = DRESY.map(function (dres, i) {
    return {
      dres: dres,
      s: OKRUH * (i / DRESY.length) + i * 3,
      odsazeni: -1.8 + i * 1.6,
      rychlost: 13.0 + i * 0.9,
      krok: Math.random(),
      historie: []
    };
  });

  var pasyBuf = gl.createBuffer();
  var pasyPole = new Float32Array(bruslari.length * STOPA_DELKA * 2 * 7);
  var teloBuf = gl.createBuffer();
  var teloPole = new Float32Array(bruslari.length * 26 * KVADR_VRCHOLU * 9);

  /* ══════════════ Kamera ══════════════
     Záběry se střídají, mezi nimi se plynule přejíždí. */
  var ZABERY = [
    function (t, v) {                                   // za zády vedoucího
      var z = naOvalu(v.s - 6.2, v.odsazeni), c = naOvalu(v.s + 7, v.odsazeni);
      return { oko: [z.x, 1.9, z.z], cil: [c.x, 0.9, c.z], fov: 1.0 };
    },
    function (t, v) {                                   // od mantinelu, těsně u dráhy
      var c = naOvalu(v.s, v.odsazeni);
      var b = naOvalu(v.s + 15, 9.5);
      return { oko: [b.x, 1.25, b.z], cil: [c.x, 0.85, c.z], fov: 0.95 };
    },
    function (t, v) {                                   // zepředu, bruslař najíždí
      var p = naOvalu(v.s + 11, v.odsazeni - 1.2);
      var c = naOvalu(v.s, v.odsazeni);
      return { oko: [p.x, 1.5, p.z], cil: [c.x, 0.95, c.z], fov: 0.9 };
    },
    function (t, v) {                                   // zevnitř zatáčky, nízko
      var c = naOvalu(v.s, v.odsazeni);
      var vnitr = naOvalu(v.s + 4, -11);
      return { oko: [vnitr.x, 1.1, vnitr.z], cil: [c.x, 0.85, c.z], fov: 1.05 };
    },
    function (t, v) {                                   // šikmo shora, letí s ním
      var b = naOvalu(v.s - 3, v.odsazeni + 7);
      var c = naOvalu(v.s + 3, v.odsazeni);
      return { oko: [b.x, 5.2, b.z], cil: [c.x, 0.8, c.z], fov: 0.95 };
    },
    function (t, v) {                                   // odjezd: dráha ubíhá do dálky
      var u = Math.PI / 2 + Math.sin(t * 0.09) * 0.22;
      return { oko: [Math.sin(u) * 141, 34, Math.cos(u) * 141], cil: [0, 2, 0], fov: 0.85 };
    }
  ];
  var ZABER_DELKA = 6.5, PREJEZD = 1.3;

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

  /** Přepíše vrcholy jednoho dílu do velkého pole (poloha, normála, barva). */
  function zapisDil(pole, i, m, c) {
    for (var v = 0; v < KVADR_VRCHOLU; v++) {
      var x = KVADR_POS[v*3], y = KVADR_POS[v*3+1], z = KVADR_POS[v*3+2];
      pole[i++] = m[0]*x + m[4]*y + m[8]*z + m[12];
      pole[i++] = m[1]*x + m[5]*y + m[9]*z + m[13];
      pole[i++] = m[2]*x + m[6]*y + m[10]*z + m[14];
      var nx = KVADR_NOR[v*3], ny = KVADR_NOR[v*3+1], nz = KVADR_NOR[v*3+2];
      // Měřítko je vždy kladné, takže na směr normály stačí otočení.
      var ox = m[0]*nx + m[4]*ny + m[8]*nz;
      var oy = m[1]*nx + m[5]*ny + m[9]*nz;
      var oz = m[2]*nx + m[6]*ny + m[10]*nz;
      var d = Math.hypot(ox, oy, oz) || 1;
      pole[i++] = ox/d; pole[i++] = oy/d; pole[i++] = oz/d;
      pole[i++] = c[0]; pole[i++] = c[1]; pole[i++] = c[2];
    }
    return i;
  }

  var bezi = false, vidime = true, naObrazovce = true, cas = 0, posledni = 0;

  function snimek(ted) {
    if (!bezi) { return; }
    var dt = posledni ? Math.min((ted - posledni) / 1000, 0.05) : 0.016;
    posledni = ted; cas += dt;

    zmerZmen();
    if (!sirka || !vyska) { requestAnimationFrame(snimek); return; }

    /* — pohyb bruslařů — */
    var i = 0, t = 0;
    bruslari.forEach(function (b) {
      var bod = naOvalu(b.s, b.odsazeni);
      var tempo = bod.k ? 0.9 : 1.1;
      b.s += b.rychlost * tempo * dt;
      b.krok = (b.krok + b.rychlost * tempo * dt * 0.30) % 1;
      b.bod = bod;
      b.historie.unshift({ x: bod.x, z: bod.z, tx: bod.tx, tz: bod.tz, k: bod.k });
      if (b.historie.length > STOPA_DELKA) { b.historie.length = STOPA_DELKA; }
    });

    var vedouci = bruslari[0];
    var k = kamera(cas, vedouci);
    var mvp = nasob(perspektiva(k.fov, sirka / vyska, 0.4, 900), pohled(k.oko, k.cil));

    gl.clearColor(0, 0, 0, 0);
    gl.clearDepth(1);
    gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);

    /* — postavy: plné, se světlem — */
    gl.useProgram(progTelo);
    gl.uniformMatrix4fv(teloLoc.mvp, false, mvp);
    gl.enable(gl.DEPTH_TEST);
    gl.depthMask(true);
    gl.disable(gl.BLEND);
    gl.enable(gl.CULL_FACE);

    i = 0;
    bruslari.forEach(function (b) {
      var smer = Math.atan2(-b.bod.tz, b.bod.tx);
      var naklon = b.bod.k * 0.42;
      var svet = nasob(nasob(posun(b.bod.x, 0, b.bod.z), otocY(smer)), otocX(naklon));
      postava(b.krok, b.dres).forEach(function (c) {
        i = zapisDil(teloPole, i, nasob(svet, c.m), c.c);
      });
    });
    gl.bindBuffer(gl.ARRAY_BUFFER, teloBuf);
    gl.bufferData(gl.ARRAY_BUFFER, teloPole, gl.DYNAMIC_DRAW);
    gl.vertexAttribPointer(teloLoc.pos, 3, gl.FLOAT, false, 36, 0);
    gl.vertexAttribPointer(teloLoc.nor, 3, gl.FLOAT, false, 36, 12);
    gl.vertexAttribPointer(teloLoc.col, 3, gl.FLOAT, false, 36, 24);
    gl.enableVertexAttribArray(teloLoc.pos);
    gl.enableVertexAttribArray(teloLoc.nor);
    gl.enableVertexAttribArray(teloLoc.col);
    gl.drawArrays(gl.TRIANGLES, 0, i / 9);

    /* — dráha a stopy: sčítají se, hloubku jen čtou — */
    gl.useProgram(progSvit);
    gl.uniformMatrix4fv(svitLoc.mvp, false, mvp);
    gl.disable(gl.CULL_FACE);
    gl.depthMask(false);
    gl.enable(gl.BLEND);
    gl.blendFunc(gl.ONE, gl.ONE);

    gl.bindBuffer(gl.ARRAY_BUFFER, caryBuf);
    gl.vertexAttribPointer(svitLoc.pos, 3, gl.FLOAT, false, 28, 0);
    gl.vertexAttribPointer(svitLoc.col, 4, gl.FLOAT, false, 28, 12);
    gl.enableVertexAttribArray(svitLoc.pos);
    gl.enableVertexAttribArray(svitLoc.col);
    gl.drawArrays(gl.TRIANGLES, 0, caryPocet);

    t = 0;
    bruslari.forEach(function (b) {
      for (var n = 0; n < STOPA_DELKA; n++) {
        var h = b.historie[Math.min(n, b.historie.length - 1)];
        var podil = 1 - n / STOPA_DELKA;
        var sila = podil * podil * 0.8;
        var w = 0.10 + podil * 0.16;
        var nx = -h.tz * w, nz = h.tx * w;
        pasyPole[t++] = h.x - nx; pasyPole[t++] = 0.02; pasyPole[t++] = h.z - nz;
        pasyPole[t++] = b.dres[0]; pasyPole[t++] = b.dres[1]; pasyPole[t++] = b.dres[2]; pasyPole[t++] = sila;
        pasyPole[t++] = h.x + nx; pasyPole[t++] = 0.02; pasyPole[t++] = h.z + nz;
        pasyPole[t++] = b.dres[0]; pasyPole[t++] = b.dres[1]; pasyPole[t++] = b.dres[2]; pasyPole[t++] = sila;
      }
    });
    gl.bindBuffer(gl.ARRAY_BUFFER, pasyBuf);
    gl.bufferData(gl.ARRAY_BUFFER, pasyPole, gl.DYNAMIC_DRAW);
    gl.vertexAttribPointer(svitLoc.pos, 3, gl.FLOAT, false, 28, 0);
    gl.vertexAttribPointer(svitLoc.col, 4, gl.FLOAT, false, 28, 12);
    for (var j = 0; j < bruslari.length; j++) {
      gl.drawArrays(gl.TRIANGLE_STRIP, j * STOPA_DELKA * 2, STOPA_DELKA * 2);
    }

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
