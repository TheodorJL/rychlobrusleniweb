#!/usr/bin/env python3
"""Sestaví samostatnou HTML stránku pro publikaci (vše vložené v jednom souboru).

Dvě věci, na kterých to jinak padá:
  * data URI loga — kódujeme base64, aby uvozovky nerozbily atribut src
  * čeština — text převádíme na číselné entity a JS na \\uXXXX, takže stránka
    vypadá správně i tam, kde hostitel nedeklaruje UTF-8
"""
import base64, pathlib, re

import sys

ROOT = pathlib.Path(__file__).parent
SOURCE = sys.argv[1] if len(sys.argv) > 1 else 'preview/index.html'
OUTPUT = sys.argv[2] if len(sys.argv) > 2 else 'preview/artefakt.html'
TITLE  = sys.argv[3] if len(sys.argv) > 3 else 'Redesign speedskating.cz'

html = (ROOT / SOURCE).read_text(encoding='utf-8')
css  = (ROOT / 'assets/css/csr-home.css').read_text(encoding='utf-8')
js   = (ROOT / 'assets/js/csr-home.js').read_text(encoding='utf-8')
svg  = (ROOT / 'assets/img/logo-mark.svg').read_text(encoding='utf-8')


def ascii_entities(text: str) -> str:
    """Nahradí znaky mimo ASCII číselnými entitami (nezávislé na kódování)."""
    return ''.join(c if ord(c) < 128 else f'&#{ord(c)};' for c in text)


def ascii_body(html: str) -> str:
    """Převede tělo stránky na ASCII.

    Uvnitř <script> se HTML entity nedekódují — tam musí být \\uXXXX,
    jinak by se v JS objevily doslova („Prob&#283;hl&#233;").
    """
    out, pos = [], 0
    for m in re.finditer(r'<script\b[^>]*>.*?</script>', html, re.S | re.I):
        out.append(ascii_entities(html[pos:m.start()]))
        out.append(ascii_js(m.group(0)))
        pos = m.end()
    out.append(ascii_entities(html[pos:]))
    return ''.join(out)


def ascii_js(text: str) -> str:
    """Totéž pro JavaScript — escape sekvencí \\uXXXX."""
    return ''.join(c if ord(c) < 128 else '\\u%04x' % ord(c) for c in text)


# --- Logo jako base64 data URI -------------------------------------------
svg_min = re.sub(r'<!--.*?-->', '', svg, flags=re.S)
svg_min = re.sub(r'>\s+<', '><', svg_min.strip())
logo_uri = 'data:image/svg+xml;base64,' + base64.b64encode(svg_min.encode('utf-8')).decode()

# --- Tělo stránky ---------------------------------------------------------
body = html[html.index('<body>') + len('<body>'):html.index('</body>')]
body = body.replace('<script src="../assets/js/csr-home.js"></script>', '')
body = body.replace('../assets/img/logo-mark.svg', logo_uri)
body = body.replace(
    '<span>© 2026 Český svaz rychlobruslení</span>',
    '<span>© 2026 Český svaz rychlobruslení · <em style="font-style:normal;opacity:.75">'
    'Návrh vzhledu — fotky jsou zástupné</em></span>'
)

# Komentáře zahodíme; znaky mimo ASCII, které v CSS zůstanou (např. content: "·")
# přepíšeme na CSS escape \0000b7, aby soubor byl celý ASCII.
css_clean = re.sub(r'/\*.*?\*/', '', css, flags=re.S)
css_clean = re.sub(r'\n{3,}', '\n\n', css_clean)
css_clean = ''.join(c if ord(c) < 128 else '\\%06x' % ord(c) for c in css_clean)

out = (
    f'<title>{ascii_entities(TITLE)}</title>\n'
    '<link rel="preconnect" href="https://fonts.googleapis.com">\n'
    '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>\n'
    '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?'
    'family=Barlow+Condensed:ital,wght@0,600;0,700;1,600;1,700&'
    'family=IBM+Plex+Sans:wght@400;500;600;700&display=swap">\n'
    f'<style>\n{css_clean}\n</style>\n'
    f'{ascii_body(body)}\n'
    f'<script>\n{ascii_js(js)}\n</script>\n'
)

target = ROOT / OUTPUT
target.write_text(out, encoding='ascii')

print(f'✓ {target}  ({len(out.encode()) / 1024:.1f} kB, čisté ASCII)')
