#!/usr/bin/env python3
"""Statická kontrola balíčku ČSR.

Hlídá chyby, které PHP samo o sobě nenajde — volání neexistující funkce,
dvakrát definovanou funkci, nezaregistrovaný klíč v Customizeru. Tyhle
věci se jinak projeví až na webu, a to zpravidla bílou stránkou.
"""
import glob
import os
import re
import sys

CHYBY = []


def chyba(text):
    CHYBY.append(text)
    print(f"::error::{text}")


def main():
    php = sorted(glob.glob('wordpress/**/*.php', recursive=True)) + \
          sorted(glob.glob('theme/*.php'))
    if not php:
        chyba("Nenalezeny žádné soubory PHP.")
        return

    # --- funkce: definované vs. volané ---------------------------------
    definovane, duplicity = {}, []
    for f in php:
        s = open(f, encoding='utf-8').read()
        for m in re.finditer(r'^function\s+(csr_[a-z0-9_]+)\s*\(', s, re.M):
            n = m.group(1)
            if n in definovane:
                duplicity.append(f"{n} ({definovane[n]} a {f})")
            definovane[n] = f

    volane = set()
    konstanty_def, konstanty_pouzite = set(), set()
    for f in php:
        s = open(f, encoding='utf-8').read()
        volane |= set(re.findall(r'\b(csr_[a-z0-9_]+)\s*\(', s))
        konstanty_def |= set(re.findall(r'^const\s+(CSR_[A-Z_]+)', s, re.M))
        konstanty_pouzite |= set(re.findall(r'\b(CSR_[A-Z_]+)\b', s))

        if s.count('{') != s.count('}'):
            chyba(f"{f}: nevyvážené složené závorky")
        if not s.startswith('<?php'):
            chyba(f"{f}: nezačíná <?php")
        if s.rstrip().endswith('?>'):
            chyba(f"{f}: končí ?> — koncová značka může poslat na výstup mezeru")

        # Nonce musí mít stejný počet vystavení i ověření
        nf = len(re.findall(r'wp_nonce_field', s))
        nv = len(re.findall(r'wp_verify_nonce', s))
        if nf != nv:
            chyba(f"{f}: {nf} wp_nonce_field, ale {nv} wp_verify_nonce")

    for n in sorted(volane - set(definovane)):
        chyba(f"volá se csr funkce, která nikde není: {n}()")
    for d in duplicity:
        chyba(f"funkce definovaná dvakrát: {d}")
    for k in sorted(konstanty_pouzite - konstanty_def):
        chyba(f"nedeklarovaná konstanta: {k}")

    # --- registrace typů obsahu a taxonomií nesmí být dvojí ------------
    # Název bývá zapsaný jednou jako 'csr_season', jindy jako konstanta
    # CSR_TAX_SEASON. Bez rozpuštění konstant by dvojí registrace prošla —
    # a právě ta tiše přepíše tu první a rozbije, co na ní stálo.
    hodnoty = {}
    for f in php:
        for m in re.finditer(r"^const\s+(CSR_[A-Z_]+)\s*=\s*'([^']+)'", 
                             open(f, encoding='utf-8').read(), re.M):
            hodnoty[m.group(1)] = m.group(2)

    for volani, popis in (('register_post_type', 'typ obsahu'),
                          ('register_taxonomy', 'taxonomie')):
        videno = {}
        for f in php:
            s = open(f, encoding='utf-8').read()
            for m in re.findall(volani + r"\(\s*\n?\s*'?([A-Za-z_]+)'?", s):
                nazev = hodnoty.get(m, m)
                if nazev in videno:
                    chyba(f"{popis} {nazev} se registruje dvakrát "
                          f"({videno[nazev]} a {f}) — druhá registrace tu první přepíše")
                videno[nazev] = f

    # --- Customizer: čte se klíč, který se nikde neregistruje? ---------
    cust = 'wordpress/csr-customizer.php'
    if os.path.exists(cust):
        c = open(cust, encoding='utf-8').read()
        registrovane = set(re.findall(r"'(csr_[a-z0-9_]+)'\s*=>\s*array\(\s*'type'", c))
        opakovane = set(re.findall(r"csr_repeat_fields\(\s*'(csr_[a-z0-9_]+)'", c))
        ctene = set()
        for f in php:
            ctene |= set(re.findall(r"csr_opt\(\s*'(csr_[a-z0-9_]+)'",
                                    open(f, encoding='utf-8').read()))
        for k in sorted(ctene - registrovane):
            if not any(k.startswith(p) for p in opakovane):
                chyba(f"csr_opt('{k}') čte klíč, který se v Customizeru neregistruje")

    # --- CSS: proměnná, která se nikde nedefinuje ----------------------
    css = 'assets/css/csr-home.css'
    if os.path.exists(css):
        s = open(css, encoding='utf-8').read()
        if s.count('{') != s.count('}'):
            chyba(f"{css}: nevyvážené složené závorky")
        # Proměnné nastavované v atributu style= se v souboru nedefinují.
        z_html = {'--csr-delay', '--csr-ratio', '--csr-w', '--csr-progress',
                  '--csr-logo-h', '--csr-chip-dot'}
        pouzite = set(re.findall(r'var\(\s*(--csr-[a-z0-9-]+)', s))
        definovane_css = set(re.findall(r'^\s*(--csr-[a-z0-9-]+)\s*:', s, re.M))
        for v in sorted(pouzite - definovane_css - z_html):
            chyba(f"{css}: proměnná {v} se používá, ale nikde nedefinuje")

    print(f"Zkontrolováno {len(php)} souborů PHP, {len(definovane)} funkcí csr_*.")
    if CHYBY:
        print(f"\nNalezeno chyb: {len(CHYBY)}")
        sys.exit(1)
    print("Bez nálezu.")


if __name__ == '__main__':
    main()
