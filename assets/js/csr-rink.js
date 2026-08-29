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

  // Přednásobená alfa: shader záře násobí barvu alfou už sám. Bez toho
  // by ji prohlížeč při skládání do stránky vynásobil podruhé a ze stop
  // by místo záře byly tmavé pruhy.
  var gl = canvas.getContext('webgl', { alpha: true, antialias: true, premultipliedAlpha: true, depth: true })
        || canvas.getContext('experimental-webgl', { alpha: true, antialias: true, premultipliedAlpha: true, depth: true });
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
    // Hlavní světlo shora, dosvit z protisměru — bez něj byla záda
    // bruslaře při pohledu zezadu jen tmavá deska.
    'void main(){ vec3 n = normalize(vNor);' +
    ' float d = max(dot(n, normalize(vec3(0.42, 0.86, 0.30))), 0.0);' +
    ' float f = max(dot(n, normalize(vec3(-0.55, 0.30, -0.60))), 0.0);' +
    ' float obrys = pow(1.0 - abs(dot(n, normalize(vSmer))), 2.2);' +
    ' vec3 c = vCol * (0.34 + 0.70 * d + 0.30 * f) + vec3(0.36, 0.68, 0.95) * obrys * 0.6;' +
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

  /* ══════════════ Bruslař ══════════════
     Postava stojí v počátku, dívá se na +X, vzhůru je +Y, vpravo +Z.
     Skládá se ze zúžených osmibokých trubek — kvádry vypadaly jako pár
     krabic v sobě. Končetiny se nepočítají z úhlů, ale z bodů: řekne se,
     kde má být chodidlo, a koleno se dopočítá. */

  function hladce(t) { t = Math.min(Math.max(t, 0), 1); return t * t * (3 - 2 * t); }
  function mix(a, b, t) { return a + (b - a) * t; }

  var BARVA_KUZE  = [0.86, 0.70, 0.58];
  var BARVA_OCEL  = [0.80, 0.88, 0.96];
  var BARVA_NUZ   = [0.55, 0.78, 0.96];
  var BARVA_BOTA  = [0.05, 0.06, 0.09];
  var BARVA_BRYLE = [0.08, 0.10, 0.14];

  var STRAN = 8, KRUH_C = [], KRUH_S = [];
  (function () {
    for (var j = 0; j < STRAN; j++) {
      KRUH_C.push(Math.cos(j / STRAN * Math.PI * 2));
      KRUH_S.push(Math.sin(j / STRAN * Math.PI * 2));
    }
  }());

  /**
   * Zúžená osmiboká trubka mezi dvěma body.
   *
   * Normály míří paprskovitě od osy, takže se díl stínuje oble. Poloměr
   * se po délce mění (stehno je u kyčle širší než u kolena) a `kz`
   * průřez zplošťuje — hruď je širší než hlubší, nůž brusle skoro
   * plochý. Zapisuje rovnou do světových souřadnic.
   *
   * @param {Array}  a    Začátek v souřadnicích postavy.
   * @param {Array}  b    Konec.
   * @param {number} ra   Poloměr u začátku.
   * @param {number} rb   Poloměr u konce.
   * @param {number} kz   Zploštění průřezu (1 = kruhový).
   * @param {Array}  c    Barva.
   * @param {Float32Array} m Umístění postavy (jen otočení a posun).
   * @param {Object} out  Kam zapisovat.
   * @param {number} i    Zápisová pozice.
   * @return {number} Nová zápisová pozice.
   */
  function trubka(a, b, ra, rb, kz, c, m, out, i) {
    var ux = b[0] - a[0], uy = b[1] - a[1], uz = b[2] - a[2];
    var d = Math.hypot(ux, uy, uz) || 1e-4;
    ux /= d; uy /= d; uz /= d;

    var hx = 0, hz = 1;
    if (Math.abs(uz) > 0.9) { hz = 0; hx = 1; }
    var px = -hz * uy, py = hz * ux - hx * uz, pz = hx * uy;
    var e = Math.hypot(px, py, pz) || 1;
    px /= e; py /= e; pz /= e;
    var qx = uy * pz - uz * py, qy = uz * px - ux * pz, qz = ux * py - uy * px;

    // Paprsky průřezu a jejich normály.
    var DX = [], DY = [], DZ = [], NX = [], NY = [], NZ = [];
    for (var j = 0; j < STRAN; j++) {
      var dx = px * KRUH_C[j] + qx * KRUH_S[j] * kz;
      var dy = py * KRUH_C[j] + qy * KRUH_S[j] * kz;
      var dz = pz * KRUH_C[j] + qz * KRUH_S[j] * kz;
      DX.push(dx); DY.push(dy); DZ.push(dz);
      var n = Math.hypot(dx, dy, dz) || 1;
      NX.push(dx / n); NY.push(dy / n); NZ.push(dz / n);
    }

    function w(x, y, z, nx, ny, nz) {
      out[i++] = m[0] * x + m[4] * y + m[8] * z + m[12];
      out[i++] = m[1] * x + m[5] * y + m[9] * z + m[13];
      out[i++] = m[2] * x + m[6] * y + m[10] * z + m[14];
      out[i++] = m[0] * nx + m[4] * ny + m[8] * nz;
      out[i++] = m[1] * nx + m[5] * ny + m[9] * nz;
      out[i++] = m[2] * nx + m[6] * ny + m[10] * nz;
      out[i++] = c[0]; out[i++] = c[1]; out[i++] = c[2];
    }

    for (j = 0; j < STRAN; j++) {
      var k = (j + 1) % STRAN;
      var a1x = a[0] + DX[j] * ra, a1y = a[1] + DY[j] * ra, a1z = a[2] + DZ[j] * ra;
      var b1x = b[0] + DX[j] * rb, b1y = b[1] + DY[j] * rb, b1z = b[2] + DZ[j] * rb;
      var a2x = a[0] + DX[k] * ra, a2y = a[1] + DY[k] * ra, a2z = a[2] + DZ[k] * ra;
      var b2x = b[0] + DX[k] * rb, b2y = b[1] + DY[k] * rb, b2z = b[2] + DZ[k] * rb;

      w(a1x, a1y, a1z, NX[j], NY[j], NZ[j]);
      w(b1x, b1y, b1z, NX[j], NY[j], NZ[j]);
      w(b2x, b2y, b2z, NX[k], NY[k], NZ[k]);
      w(a1x, a1y, a1z, NX[j], NY[j], NZ[j]);
      w(b2x, b2y, b2z, NX[k], NY[k], NZ[k]);
      w(a2x, a2y, a2z, NX[k], NY[k], NZ[k]);

      // víčka na koncích
      w(a[0], a[1], a[2], -ux, -uy, -uz);
      w(a2x, a2y, a2z, -ux, -uy, -uz);
      w(a1x, a1y, a1z, -ux, -uy, -uz);
      w(b[0], b[1], b[2], ux, uy, uz);
      w(b1x, b1y, b1z, ux, uy, uz);
      w(b2x, b2y, b2z, ux, uy, uz);
    }
    return i;
  }

  /**
   * Mezikloub dvoukostní končetiny — loket nebo koleno.
   *
   * Konce a délky obou kostí jsou dané, poloha mezikloubu se dopočítá;
   * náznak (hx, hy, hz) říká, na kterou stranu se končetina ohýbá.
   * Cíl se drží v dosahu, takže se končetina nikdy nenatáhne přes
   * svou délku — přesně to dřív v zatáčkách lámalo lokty.
   */
  function mezikloub(a, b, l1, l2, hx, hy, hz) {
    var ux = b[0] - a[0], uy = b[1] - a[1], uz = b[2] - a[2];
    var dd = Math.hypot(ux, uy, uz) || 1e-4;
    ux /= dd; uy /= dd; uz /= dd;
    var d = Math.min(dd, (l1 + l2) * 0.99);

    var cos = (l1 * l1 + d * d - l2 * l2) / (2 * l1 * d);
    var uhel = Math.acos(Math.min(Math.max(cos, -1), 1));

    var s = hx * ux + hy * uy + hz * uz;
    hx -= ux * s; hy -= uy * s; hz -= uz * s;
    var e = Math.hypot(hx, hy, hz) || 1;
    hx /= e; hy /= e; hz /= e;

    var c = Math.cos(uhel) * l1, t = Math.sin(uhel) * l1;
    return [a[0] + ux * c + hx * t, a[1] + uy * c + hy * t, a[2] + uz * c + hz * t];
  }

  /** Koleno mezi kyčlí a chodidlem — ohýbá se dopředu a mírně ven. */
  function koleno(kycel, chodidlo, stehno, lytko, ven) {
    return mezikloub(kycel, chodidlo, stehno, lytko, 1, 0.35, ven);
  }

  var KYCEL_Y = 0.75, STEHNO = 0.44, LYTKO = 0.44, LED = 0.10;
  var NADLOKTI = 0.29, PREDLOKTI = 0.29;

  /**
   * Zapíše postavu v dané fázi dvojkroku.
   *
   * Krok má čtyři fáze skutečného bruslení: skluz (brusle nese váhu
   * pod tělem a tělo ji přejíždí), zrychlující odraz dozadu a ven,
   * dokončení se zdvihem špičky a nízký návrat pod tělo. Boky se
   * přenášejí nad skluzovou nohu — bez toho je to chůze, ne bruslení.
   *
   * @param {number} faze 0..1, jeden dvojkrok.
   * @param {Array}  dres Barva dresu.
   * @param {number} zat  0..1, jak hluboko je bruslař v zatáčce.
   * @param {Float32Array} m Umístění ve světě.
   * @param {Object} out  Kam zapisovat.
   * @param {number} i    Zápisová pozice.
   * @return {number} Nová zápisová pozice.
   */
  function postava(faze, dres, zat, m, out, i) {
    var cyk = faze * Math.PI * 2;

    // Váha nad skluzovou nohou; nejníž je tělo při dopadu, nejvýš
    // v plném propnutí odrazu. Obojí dvakrát za dvojkrok.
    var houp = 0.11 * Math.cos((faze - 0.15) * Math.PI * 2);
    var kycleY = KYCEL_Y - 0.05 + 0.035 * Math.abs(Math.sin((faze - 0.31) * Math.PI * 2));

    var kycle  = [-0.02, kycleY, houp];
    var hrud   = [0.24, kycleY + 0.20, houp * 0.6];
    var ramena = [0.40, kycleY + 0.31, houp * 0.4];

    /* ── Trup: pánev → hruď → ramena, oblý a v ramenou širší ── */
    i = trubka([kycle[0] - 0.06, kycle[1] - 0.04, kycle[2]], hrud, 0.15, 0.16, 1.45, dres, m, out, i);
    i = trubka(hrud, ramena, 0.16, 0.12, 1.6, dres, m, out, i);

    /* ── Krk, hlava, přilba, brýle ── */
    var krk    = [ramena[0] + 0.06, ramena[1] + 0.04, ramena[2]];
    var hlavaA = [ramena[0] + 0.12, ramena[1] + 0.12, ramena[2]];
    var hlavaB = [ramena[0] + 0.23, ramena[1] + 0.19, ramena[2]];
    i = trubka(krk, hlavaA, 0.05, 0.055, 1, BARVA_KUZE, m, out, i);
    i = trubka(hlavaA, hlavaB, 0.09, 0.07, 1.05, BARVA_KUZE, m, out, i);
    i = trubka([hlavaA[0] - 0.03, hlavaA[1] + 0.04, hlavaA[2]],
               [hlavaB[0] - 0.02, hlavaB[1] + 0.045, hlavaB[2]], 0.10, 0.08, 1.08, dres, m, out, i);
    i = trubka([hlavaB[0] - 0.01, hlavaB[1] - 0.005, hlavaB[2] - 0.07],
               [hlavaB[0] - 0.01, hlavaB[1] - 0.005, hlavaB[2] + 0.07], 0.032, 0.032, 1.6, BARVA_BRYLE, m, out, i);

    /* ── Paže ──
       Levá je pořád složená na kříži. Pravá v zatáčce švihá kyvadlem
       počítaným dopředně v kloubech: rameno je čep, kterým se otáčí
       celá paže, loket je pant ve stejné rovině — dole při průchodu
       ohnutý, na krajích švihu skoro propnutý. Dlaň nikam netáhneme,
       vyjde tam, kam ji klouby donesou, takže se paže hýbe jako paže.
    */
    var svihF = Math.sin(cyk + 2.1);
    [-1, 1].forEach(function (strana) {
      var rameno = [ramena[0] - 0.02, ramena[1] - 0.04, ramena[2] + strana * 0.16];

      // Klid: dlaň na kříži, loket dopočítá mezikloub.
      var ruka  = [kycle[0] - 0.10, kycle[1] + 0.12 + (strana > 0 ? 0.03 : 0), kycle[2] * 0.7 + strana * 0.05];
      var loket = mezikloub(rameno, ruka, NADLOKTI, PREDLOKTI, -0.1, 0.4, strana);

      if (strana > 0 && zat > 0.02) {
        // Rameno: úhel kyvadla vpřed (+) a vzad (−) od svislice.
        var theta = -0.25 + 1.15 * svihF;
        // Loket: ohnutý při průchodu dole, propnutý na krajích švihu.
        var beta = 0.95 - 0.55 * Math.abs(svihF);
        // Rovina švihu je od těla odkloněná, aby dlaň míjela bok i koleno.
        var vybok = 0.32;

        var ux = Math.sin(theta), uy = -Math.cos(theta), uz = strana * vybok;
        var un = Math.hypot(ux, uy, uz); ux /= un; uy /= un; uz /= un;
        var loketS = [rameno[0] + ux * NADLOKTI, rameno[1] + uy * NADLOKTI, rameno[2] + uz * NADLOKTI];

        var fi = theta + beta;
        var fx = Math.sin(fi), fy = -Math.cos(fi), fz = strana * vybok;
        var fn = Math.hypot(fx, fy, fz); fx /= fn; fy /= fn; fz /= fn;
        var rukaS = [loketS[0] + fx * PREDLOKTI, loketS[1] + fy * PREDLOKTI, loketS[2] + fz * PREDLOKTI];

        // Přejezd mezi křížem a švihem vede obloukem kolem boku —
        // přímé prolnutí by dlaň táhlo skrz hrudník.
        var oblouk = Math.sin(zat * Math.PI) * 0.14;
        loket = [mix(loket[0], loketS[0], zat), mix(loket[1], loketS[1], zat), mix(loket[2], loketS[2], zat) + oblouk * 0.6];
        ruka  = [mix(ruka[0], rukaS[0], zat), mix(ruka[1], rukaS[1], zat), mix(ruka[2], rukaS[2], zat) + oblouk];
      }

      i = trubka(rameno, loket, 0.052, 0.045, 1, dres, m, out, i);
      i = trubka(loket, ruka, 0.042, 0.036, 1, dres, m, out, i);
      var dl = Math.hypot(ruka[0] - loket[0], ruka[1] - loket[1], ruka[2] - loket[2]) || 1;
      i = trubka(ruka,
                 [ruka[0] + (ruka[0] - loket[0]) / dl * 0.07,
                  ruka[1] + (ruka[1] - loket[1]) / dl * 0.07,
                  ruka[2] + (ruka[2] - loket[2]) / dl * 0.07],
                 0.035, 0.026, 1.15, BARVA_KUZE, m, out, i);
    });

    /* ── Nohy ──
       Každá fáze začíná i končí nulovou rychlostí chodidla, takže na
       přechodech nic neškubne — dřív odraz končil v plné rychlosti
       a dokončení na ni nenavazovalo. */
    [1, -1].forEach(function (strana, idx) {
      var q = (faze + (idx ? 0.5 : 0)) % 1;
      var chodidlo, naLedu = false, vytoc = 0;

      if (q < 0.30) {
        // Skluz: brusle stojí na ledě a tělo ji přejíždí.
        var g = hladce(q / 0.30);
        chodidlo = [mix(0.18, 0.08, g), LED, strana * 0.12];
        naLedu = true; vytoc = 0.05;
      } else if (q < 0.62) {
        // Odraz dozadu a ven do propnutí; ke konci se zvedá špička.
        var t = hladce((q - 0.30) / 0.32);
        var zdvih = Math.max(0, (t - 0.85) / 0.15);
        chodidlo = [mix(0.08, -0.40, t), LED + zdvih * zdvih * 0.08, strana * mix(0.12, 0.48, t)];
        naLedu = t < 0.85; vytoc = 0.05 + t * 0.30;
      } else {
        // Návrat nízkým obloukem zpátky pod tělo.
        var u = hladce((q - 0.62) / 0.38);
        chodidlo = [mix(-0.40, 0.18, u), LED + (1 - u) * 0.08 + Math.sin(u * Math.PI) * 0.10, strana * mix(0.48, 0.12, u)];
        vytoc = 0.35 * (1 - u);
      }

      var kycelB = [kycle[0], kycle[1] - 0.05, kycle[2] + strana * 0.10];

      // Cíl se drží v dosahu nohy — jinak koleno narazí na doraz
      // a bota s nožem se utrhnou od lýtka.
      var vx = chodidlo[0] - kycelB[0], vy = chodidlo[1] - kycelB[1], vz = chodidlo[2] - kycelB[2];
      var dosah = (STEHNO + LYTKO) * 0.98 - 0.06;
      var vd = Math.hypot(vx, vy, vz);
      if (vd > dosah) {
        var zk = dosah / vd;
        chodidlo = [kycelB[0] + vx * zk, kycelB[1] + vy * zk, kycelB[2] + vz * zk];
      }

      var kolenoB = koleno(kycelB, chodidlo, STEHNO, LYTKO, strana * 0.18);
      var kotnik  = [chodidlo[0], chodidlo[1] + 0.05, chodidlo[2]];

      i = trubka(kycelB, kolenoB, 0.085, 0.062, 1.1, dres, m, out, i);
      i = trubka(kolenoB, kotnik, 0.058, 0.042, 1, dres, m, out, i);

      var cf = Math.cos(vytoc), sf = Math.sin(vytoc) * strana;
      i = trubka([chodidlo[0] - 0.07 * cf, chodidlo[1] + 0.03, chodidlo[2] - 0.07 * sf],
                 [chodidlo[0] + 0.15 * cf, chodidlo[1] + 0.005, chodidlo[2] + 0.15 * sf],
                 0.05, 0.038, 0.85, BARVA_BOTA, m, out, i);
      i = trubka([chodidlo[0] - 0.13 * cf, chodidlo[1] - 0.05, chodidlo[2] - 0.13 * sf],
                 [chodidlo[0] + 0.23 * cf, chodidlo[1] - 0.05, chodidlo[2] + 0.23 * sf],
                 0.021, 0.021, 0.22, naLedu ? BARVA_NUZ : BARVA_OCEL, m, out, i);
    });

    return i;
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
      rychlost: 10.6 + i * 0.55,
      krok: Math.random(),
      zat: 0,
      /*
       * Stopa musí být světlá: plátno se do stránky skládá normálně,
       * takže tmavý pás by pozadí ztmavil a vypadal jako stín, ne záře.
       */
      stopa: [dres[0] * 0.35 + 0.42, dres[1] * 0.35 + 0.55, dres[2] * 0.35 + 0.62],
      historie: []
    };
  });

  var pasyBuf = gl.createBuffer();
  var pasyPole = new Float32Array(bruslari.length * STOPA_DELKA * 2 * 7);
  var teloBuf = gl.createBuffer();
  // Kolik čísel zabere jedna postava, se změří na zkušebním zápisu —
  // topologie je stálá, mění se jen souřadnice.
  var DELKA_FIGURY = postava(0, DRESY[0], 0, jednotka(), [], 0);
  var teloPole = new Float32Array(bruslari.length * DELKA_FIGURY);

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
      // Frekvence kroků: celý dvojkrok trvá bezmála dvě sekundy.
      b.krok = (b.krok + dt * (0.42 + b.rychlost * 0.012)) % 1;
      // Do zatáčky se najíždí plynule — náklon ani švih paže nesmí skočit.
      b.zat += (bod.k - b.zat) * Math.min(1, dt * 2.2);
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
    gl.disable(gl.CULL_FACE);

    i = 0;
    bruslari.forEach(function (b) {
      var smer = Math.atan2(-b.bod.tz, b.bod.tx);
      var naklon = b.zat * 0.34;
      var svet = nasob(nasob(posun(b.bod.x, Math.sin(naklon) * 0.12, b.bod.z), otocY(smer)), otocX(naklon));
      i = postava(b.krok, b.dres, b.zat, svet, teloPole, i);
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
    /*
     * Skládání s přednásobenou barvou. Čisté sčítání tu nefunguje:
     * plátno leží na stránce a prohlížeč o zapsanou alfu ztlumí pozadí,
     * takže slabá stopa vrhala stín místo světla — a bez zápisu alfy
     * zase přednásobené plátno barvu ořízne úplně.
     */
    gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA);

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

        /*
         * Pohled podél pásu stopu ztlumí. Při pohledu zezadu se všechny
         * dílky pásu promítnou na sebe, jejich průhlednosti se poskládají
         * skoro na jedničku a slabá barva to nedožene — místo záře byl
         * na obrazovce tmavý kvádr.
         */
        var vx = h.x - k.oko[0], vz = h.z - k.oko[2];
        var vd = Math.hypot(vx, vz) || 1;
        var bokem = Math.abs(vx * h.tz - vz * h.tx) / vd;

        var sila = podil * podil * 0.5 * (0.02 + 0.98 * bokem);
        var w = 0.08 + podil * 0.12;
        var nx = -h.tz * w, nz = h.tx * w;
        pasyPole[t++] = h.x - nx; pasyPole[t++] = 0.02; pasyPole[t++] = h.z - nz;
        pasyPole[t++] = b.stopa[0]; pasyPole[t++] = b.stopa[1]; pasyPole[t++] = b.stopa[2]; pasyPole[t++] = sila;
        pasyPole[t++] = h.x + nx; pasyPole[t++] = 0.02; pasyPole[t++] = h.z + nz;
        pasyPole[t++] = b.stopa[0]; pasyPole[t++] = b.stopa[1]; pasyPole[t++] = b.stopa[2]; pasyPole[t++] = sila;
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
