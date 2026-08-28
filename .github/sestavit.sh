#!/usr/bin/env bash
# Poskládá z repozitáře hotovou potomkovskou šablonu do build/csr-child/.
#
# V repozitáři jsou soubory rozdělené podle toho, k čemu slouží (wordpress/,
# assets/, theme/). Ve WordPressu ale musí PHP soubory ležet v kořeni šablony
# vedle sebe — odkazují na sebe přes __DIR__. Tenhle skript to složí.

set -euo pipefail

CIL="build/csr-child"

rm -rf build
mkdir -p "$CIL"

# 1. Hlavička šablony a functions.php
cp theme/style.css theme/functions.php "$CIL/"

# 2. Moduly a šablony stránek do kořene, template-parts/ se strukturou
cp wordpress/*.php "$CIL/"
cp -R wordpress/template-parts "$CIL/"

# 3. Styly, skripty, obrázky
cp -R assets "$CIL/"

# Kontrola, že složené to dává smysl
test -f "$CIL/style.css"              || { echo "chybí style.css"; exit 1; }
test -f "$CIL/functions.php"          || { echo "chybí functions.php"; exit 1; }
test -f "$CIL/csr-home-functions.php" || { echo "chybí csr-home-functions.php"; exit 1; }
test -f "$CIL/assets/css/csr-home.css" || { echo "chybí assets/css/csr-home.css"; exit 1; }
test -d "$CIL/template-parts"         || { echo "chybí template-parts/"; exit 1; }

grep -q "^Template:  *generatepress" "$CIL/style.css" \
  || { echo "style.css neurčuje rodičovskou šablonu generatepress"; exit 1; }

# Každý require_once __DIR__ musí najít svůj soubor
chybi=0
while read -r soubor; do
  [ -f "$CIL/$soubor" ] || { echo "require_once míří na neexistující $soubor"; chybi=1; }
done < <(grep -ho "require_once __DIR__ \. '/[a-z-]*\.php'" "$CIL"/*.php \
         | sed "s|.*/'*/||; s|'||g" | sed "s|.*/||")
[ "$chybi" -eq 0 ] || exit 1

echo "Šablona složena: $(find "$CIL" -type f | wc -l | tr -d ' ') souborů, $(du -sh "$CIL" | cut -f1)"
