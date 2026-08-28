# Nová úvodní stránka — Český svaz rychlobruslení

Kompletní redesign homepage pro speedskating.cz: moderní, animovaný, plně
responzivní, se sekcí článků. **Veškerý obsah se edituje v administraci
WordPressu** — do kódu není potřeba sahat.

---

## Co je v balíčku

```
assets/
  css/csr-home.css          Styly (design systém + layout)
  js/csr-home.js            Interakce, bez jQuery a bez knihoven (~8 kB)
  img/logo-mark.svg         Náhradní značka (jen když v WP žádné logo není)
preview/
  index.html                Náhled úvodní stránky
  soupiska.html             Náhled soupisky reprezentace
  kalendar.html             Náhled kalendáře závodů
  infofeed.html             Náhled InfoFeedu
  novinky.html              Náhled novinek za sezónu
  clanek.html               Náhled detailu článku
  kluby.html                Náhled přehledu klubů
  struktura.html            Náhled struktury svazu
  dokumenty.html            Náhled dokumentů
  pravidla-isu.html         Náhled stránky omezené na jednu rubriku
  antidoping.html           Náhled s odlišením vydavatele od úložiště
  galerie.html              Náhled alb a lightboxu
  kontakty.html             Náhled kontaktů
  artefakt*.html            Tytéž náhledy v jednom souboru (pro sdílení)
wordpress/
  page-csr-home.php         Šablona úvodní stránky
  page-csr-roster.php       Šablona soupisky reprezentace
  page-csr-events.php       Šablona kalendáře závodů
  page-csr-infofeed.php     Šablona InfoFeedu
  page-csr-news.php         Šablona novinek za sezónu
  csr-news.php              Výběr článků podle sezóny nebo kategorie
  single-csr-article.php    Šablona detailu článku
  csr-article.php           Přepnutí šablony a oprava zalomení textu
  page-csr-clubs.php        Šablona přehledu klubů
  csr-clubs.php             Databáze klubů
  page-csr-structure.php    Šablona struktury svazu
  csr-people.php            Databáze lidí
  page-csr-documents.php    Šablona dokumentů
  csr-documents.php         Databáze dokumentů
  csr-infofeed.php          Databáze oznámení (typ obsahu, zdroje, hromadné přidání)
  csr-customizer.php        Nastavení úvodní stránky (142 položek)
  csr-athletes.php          Databáze reprezentantů
  csr-home-functions.php    Načítání stylů, karty článků, donačítání
  template-parts/
    csr-header.php          Sdílená hlavička všech šablon
    csr-footer.php          Sdílená patička všech šablon
```

---

## Nasazení

Web běží na šabloně **GeneratePress**. Doporučený postup je potomkovská
šablona (child theme), aby aktualizace GeneratePress nic nepřepsala.

### 1. Potomkovská šablona

Pokud ji nemáte, vytvořte `wp-content/themes/generatepress-child/`
se soubory `style.css` (s hlavičkou `Template: generatepress`) a `functions.php`,
a aktivujte ji ve **Vzhled → Šablony**.

### 2. Nahrání souborů

Do složky potomkovské šablony zkopírujte:

```
generatepress-child/
  assets/                  ← celá složka z tohoto balíčku
  template-parts/          ← celá složka z tohoto balíčku
  page-csr-home.php
  page-csr-roster.php
  page-csr-events.php
  page-csr-infofeed.php
  page-csr-news.php
  single-csr-article.php
  page-csr-clubs.php
  page-csr-structure.php
  page-csr-documents.php
  csr-infofeed.php
  csr-news.php
  csr-article.php
  csr-clubs.php
  csr-people.php
  csr-documents.php
  csr-customizer.php
  csr-athletes.php
  csr-home-functions.php
```

### 3. Napojení

Na konec `functions.php` potomkovské šablony přidejte jediný řádek:

```php
require_once get_stylesheet_directory() . '/csr-home-functions.php';
```

### 4. Přiřazení šablony

**Stránky** → *Úvodní Stránka* → v pravém panelu
**Atributy stránky → Šablona** zvolte **„ČSR — Úvodní stránka"** → Uložit.

> Elementorový obsah se přestane vykreslovat, ale **zůstane uložený**.
> Návrat na starou podobu = přepnout šablonu zpět na „Výchozí".

### 5. Menu

**Vzhled → Menu** → přiřaďte hlavní menu k pozici **„ČSR — hlavní navigace"**.
Když to neuděláte, použije se automaticky stávající pozice `primary`.

Volitelně i **„ČSR — patička: Svaz"** a **„ČSR — patička: Sport"**;
jinak se použijí přednastavené odkazy.

---

## Editace obsahu

Všechno je na jednom místě:

**Vzhled → Přizpůsobit → „Úvodní stránka ČSR"**

Vlevo formulář, vpravo živý náhled — změny vidíte hned, uloží se až
tlačítkem *Publikovat*.

| Sekce v panelu | Co nastavíte |
|---|---|
| Značka a logo | Logo, jeho výška, převod na bílou, název vedle loga |
| Úvodní blok (hero) | Fotka na pozadí, nadpis po řádcích, odznak, dvě tlačítka |
| Čísla pod úvodním blokem | 4 údaje o svazu — hodnota, přípona, popisek |
| Běžící lišta (InfoFeed) | Zapnout/vypnout, popisek, počet článků |
| Dlaždice rychlých odkazů | 5 dlaždic — nadpis, popisek, odkaz, ikona (10 na výběr) |
| Sekce s články | Nadpisy, počet článků, omezení na kategorii, filtry |
| Nadcházející akce | Nadpisy, počet akcí, odkaz na kalendář |
| Sekce úspěchů | Nadpisy, text, 4 počítadla, medaile, tlačítko |
| Výzva k akci | Nadpis, text, tlačítko |
| Fotogalerie | Zapnout, počet fotek, odkaz |
| Partneři | 6 partnerů — název, logo, odkaz |
| Patička | Text o svazu, adresa, e-mail, sociální sítě |
| Barvy | Hlavní akcent, druhý akcent, tmavé pozadí |

Každou sekci lze vypnout zaškrtávátkem. Prázdný popisek skryje jednotlivou
položku (dlaždici, číslo, partnera).

> Tento panel nastavuje **úvodní stránku**. Soupisky reprezentace se řídí
> boxem u konkrétní stránky — viz kapitola *Soupisky reprezentace* níž.

### Logo

Nemusíte nic nastavovat — bere se **logo webu**, které už máte nahrané
(Vzhled → Přizpůsobit → Identita webu). Chcete-li v nové hlavičce jiné,
nahrajte ho v sekci *Značka a logo*.

Hlavička i patička jsou tmavé, proto je ve výchozím stavu zapnuté
**„Zobrazit logo bíle"** — tmavé logo se převede na bílou siluetu, aby
nezaniklo. Máte-li logo připravené na tmavé pozadí, přepínač vypněte.

### Fotka v hero sekci

Nahrajte ji v sekci *Úvodní blok*, nebo nastavte jako náhledový obrázek
stránky. Bez fotky se zobrazí animované grafické pozadí — vypadá dobře,
není to nouzové řešení. Ideální rozměr **2400 × 1400 px**, s volným místem
vlevo pro text.

> ⚠️ **Čísla jsou předvyplněná zástupnými hodnotami.** Než stránku
> zveřejníte, projděte v panelu sekce *Čísla pod úvodním blokem* a
> *Sekce úspěchů* a doplňte skutečné údaje z archivu svazu.

---

## Soupisky reprezentace

Dřív byl každý závodník ručně vložený Elementor widget — přidání člověka
znamenalo editovat Elementor a fotku nahrávat znovu na každou soupisku.
Teď je to databáze.

### Kalendář závodů

Závody se dál zadávají běžně v **Akce** (plugin The Events Calendar) — na tom
se nic nemění. Nová šablona je jen jiný, hezčí výpis.

### Založení stránky

1. **Stránky → Vytvořit stránku**, název „Kalendář akcí"
2. **Atributy stránky → Šablona** → **„ČSR — Kalendář akcí"**
3. Publikovat
4. Ve **Vzhled → Menu** přepněte položku *Kalendář* z `/akce/` na tuto stránku

Nastavení (popisek, počet závodů na stránku) je v Customizeru v sekci
**Stránka kalendáře**.

### Co stránka umí

- Závody **seskupené po měsících** — „Březen 2026", pod tím seznam
- **Rozsahy dat** — „13–15 Bře" u vícedenního závodu; u závodu přes přelom
  měsíce se v datovém bloku ukáže jen začátek a celý rozsah je v řádku pod
  názvem (jinak by vzniklo nesmyslné „27–1")
- Přepínač **Nadcházející / Proběhlé**, stránkování, odkaz na export do
  vlastního kalendáře (iCal)
- Místo konání a kategorie závodu, pokud jsou vyplněné

> **Původní `/akce/` zůstává funkční.** Detail jednotlivého závodu i export
> iCal dál obsluhuje plugin. Předěláváme jen přehledový výpis.

---

## InfoFeed

Oznámení s odkazy na dokumenty. Dnes je to ~55 ručně vložených Elementor
widgetů; nově je to seznam položek v administraci.

### Založení stránky

1. **Stránky** → otevřete *InfoFeed*
2. **Atributy stránky → Šablona** → **„ČSR — InfoFeed"** → Uložit

Nastavení (popisek, počet položek na stránku) je v Customizeru v sekci
**Stránka InfoFeed**.

### Přidání oznámení

**InfoFeed → Přidat položku.** Vyplníte nadpis, odkaz na dokument, text
odkazu (výchozí „Dokument naleznete zde"), volitelně druhý odkaz, ikonu
a náhledový obrázek. Štítek (ČSR, ISU, ADV, NSA…) se vybírá vpravo v boxu
*Zdroje*; osm nejpoužívanějších se založí samo při první aktivaci.

Pro převod stávajících položek použijte **InfoFeed → Hromadné přidání** —
vložíte řádky ve formátu `Nadpis | odkaz | text odkazu`. Zalomení řádku
uvnitř adresy se odstraní automaticky (v současných datech se pár takových
vyskytuje).

### Co stránka umí

- **Hledání bez ohledu na diakritiku** — „vankove" najde „Vaňkové",
  „sablikova" najde „Sáblíková"
- **Filtr podle zdroje** — chipy ČSR / ISU / ADV / NSA…, kombinovatelné
  s hledáním
- Odkazy mimo web dostanou vlastní ikonu a otevřou se v nové kartě
- Stránkování, prázdný stav s radou pro správce
- V seznamu v administraci je sloupec **Odkaz**; položka bez odkazu je
  červeně označená, aby se nedala přehlédnout

---

## Novinky za sezónu

> ⚠️ **Tohle opravuje reálnou chybu.** Všech šest sezónních stránek
> (2025-2026 až 2020-2021) je dnes **prázdných**. Obsahují jediný prvek —
> widget „Posts" z Elementor Pro — a ten nevykreslí nic, protože Elementor
> Pro na webu není aktivní. Stejný widget je i na úvodní stránce, kde má být
> sekce článků, a je stejně prázdný.

Nová šablona bere články přímo z WordPressu, takže na žádném placeném
pluginu nezávisí.

### Nasazení

U každé sezónní stránky: **Atributy stránky → Šablona** → **„ČSR — Novinky
sezóny"** → Uložit. To je vše — **sezóna se odvodí z názvu stránky**
(„2025-2026" → články od 1. 7. 2025 do 30. 6. 2026).

Chcete-li výběr řídit jinak, je vpravo u stránky box **Novinky**:

| Volba | Co udělá |
|---|---|
| podle sezóny | Vypíše články vydané mezi 1. 7. a 30. 6. |
| podle kategorie | Vypíše články z vybrané kategorie |
| všechny články | Bez omezení |

Počáteční rok jde přebít ručně, hodí se popisek pod nadpisem.
Počet článků na stránku je v Customizeru v sekci **Stránky novinek**.

### Co stránka umí

- První článek jako **velká karta**, zbytek v mřížce (stejně jako na úvodní
  stránce) — velká karta jen na první stránce výpisu
- **Filtry podle kategorií**, které se ve výpisu opravdu objevily; při jediné
  kategorii se lišta filtrů vůbec nezobrazí
- Stránkování a prázdný stav, který napoví, jaké období se vypisuje

---

## Detail článku

Tahle šablona se u příspěvků **nevybírá ručně**. Zapíná ji jeden přepínač
v Customizeru → **ČSR: Články** → *Použít nový vzhled článků*, a platí pak
pro všechny články naráz. Vypnutím se web okamžitě vrátí k původní podobě —
nic se nepřepisuje.

| Volba | Výchozí | Co dělá |
|---|---|---|
| Použít nový vzhled článků | zapnuto | Přepne všechny příspěvky na novou šablonu |
| Opravit zalomení vloženého textu | zapnuto | Viz níže |
| Zobrazit autora | zapnuto | Jméno pod titulkem |
| Zobrazit tlačítka pro sdílení | zapnuto | Facebook, X, e-mail, kopírovat odkaz |

### Oprava zalomení textu

Články na webu vznikaly kopírováním z Wordu nebo PDF. Text se tím uložil jako
**jeden odstavec plný `<br>`** — a to i uprostřed vět. V článku z 20. 6. 2026
je takových zalomení 34, odstavce nula.

Šablona to spraví při vykreslení: řádky slepí zpátky do vět a odstavec ukončí
tam, kde byl řádek zřetelně kratší než ostatní a končil tečkou. Z těch 34 řádků
vznikne **8 odstavců**, žádný předěl uprostřed věty.

> **Obsah v databázi zůstává nedotčený.** Oprava běží až při zobrazení, takže
> vypnutím přepínače se vše vrátí do původního stavu. Když si heuristika není
> jistá, odstavec nechá být.

Odstavce s obrázkem nebo vloženým videem se nikdy neupravují.

### Co šablona dál umí

- Titulní fotka slouží zároveň jako **rozostřené pozadí záhlaví** — funguje
  i u snímků na výšku z mobilu, které by roztažené přes celou šířku vypadaly
  špatně. Samotná fotka se **nikdy nezvětšuje** nad svůj skutečný rozměr.
- Šířka textu je **34 em ≈ 68 znaků na řádek** — míra, na které se čte nejlépe.
- Odkazy na předchozí a následující článek, tři související články z téže rubriky
  (a když v ní nic dalšího není, doplní se nejnovějšími).
- Sdílení bez cizích skriptů — jen obyčejné odkazy a kopírování do schránky.

---

## Kluby

Kluby byly ručně poskládané prvky v Elementoru — přidat klub znamenalo
naklonovat sloupec a přepsat text. Nově je to **databáze**: sekce **Kluby**
v administraci, jeden záznam na klub.

### Co se na staré stránce našlo

| Nález | Počet |
|---|---|
| Klubů s tlačítkem „Web klubu“, které nikam nevede | **5** — KRS Svratka, STC Žďár, RK Chrudim, RK Skuteč, SKR Hlinsko |
| Klubů se stejnou fotkou místo vlastního loga | **3** — RK Chrudim, SKR Hlinsko, ASK Blansko (`crop-1395592-kar.jpg`) |
| Nejtěžší logo v ~350px sloupci | **2560 × 1244 px** (BZK Praha) |
| PSČ mimo formát | BK Náchod: „5407 01“ — šest číslic |

Tlačítka bez odkazu byly `<a>` bez `href`. Vypadají jako odkaz, po kliknutí
se nic nestane. **Nová šablona tlačítko vůbec nevykreslí, dokud web nevyplníte** —
místo mrtvého odkazu prostě nic.

### Nasazení

1. **Kluby → Hromadné vložení**, vložit řádky, uložit.
2. U každého klubu doplnit **logo** jako náhledový obrázek.
3. Stránku Kluby přepnout na šablonu **„ČSR — Kluby"**.

Formát hromadného vkladu:

```
zkratka | celý název | IČO | kontakt | telefon | e-mail | ulice | PSČ | město | web
```

Kraj se odhadne z PSČ a jde u klubu přepsat.

### Co stránka umí

- **Hledání** bez ohledu na diakritiku — „zdar“ najde všechny čtyři žďárské
  kluby. Hledá v názvu, městě, kontaktní osobě i kraji.
- **Filtr podle kraje**, sestavený jen z krajů, kde nějaký klub opravdu je.
- **Telefon a e-mail jsou klikací** (`tel:` a `mailto:`) — na starém webu to byl
  jen text. Na dotykových zařízeních má odkaz cíl 44 px.
- **IČO odkazuje do rejstříku ARES** (jde vypnout v Customizeru).
- Loga se srovnají na jednotnou výšku bez deformace, ať mají jakýkoli poměr stran.
- V administraci je červeně vidět, kterému klubu chybí logo nebo web.

> **K zvážení:** e-maily funkcionářů jsou na stránce v čitelné podobě, stejně
> jako na starém webu. Roboti je sbírají. Vzhledem k tomu, kolik spamu už na web
> chodí, stojí za úvahu nahradit je kontaktním formulářem.

---

## Struktura svazu

> ⚠️ **Celá struktura svazu byla na webu jen jako obrázky.** 22 fotek,
> u všech `alt=""`. Jména předsednictva, kontrolní komise ani předsedů klubů
> nebyla nikde v textu — ani jedno.

Co to znamenalo:

- **Odečítač obrazovky nepřečetl nic.** Nevidomý návštěvník slyšel „Předsednictvo“
  a pak ticho.
- **Google neindexoval jediné jméno.** Popisek stránky, který si Rank Math
  vytáhl, jsou doslova jen nadpisy: „Předsednictvo Kontrolní komise Předsedové
  oddílů/klubů Dlouhá dráha Krátká dráha“. Víc textu na stránce nebylo.
- **Jména nešla vyhledat, zkopírovat ani na ně odkázat.**

Nová šablona bere lidi z databáze a **jméno i funkci vypisuje jako text.**

### Nasazení

1. **Lidé → Hromadné vložení**, vložit řádky, uložit.
2. U každého doplnit **fotku** jako náhledový obrázek.
3. Stránku Struktura přepnout na šablonu **„ČSR — Struktura svazu"**.

```
jméno | orgán | funkce | dráha | klub | e-mail | telefon
```

Orgán je `predsednictvo`, `kontrolni-komise` nebo `predsedove`.
Dráha (`dlouhá` / `krátká`) rozdělí předsedy na dvě skupiny.

### Fotky se jménem v obrázku

Současné grafiky mají jméno vypálené přímo v obrázku. Dokud to tak zůstane,
zapněte v Customizeru → **ČSR: Struktura svazu** → *Jména jsou vypsaná přímo
ve fotkách*. Text se pak schová před oči, ale **zůstane pro odečítače
a vyhledávače** — takže se jméno nezobrazí dvakrát a přístupnost je zachovaná.

Až budou fotky bez textu, přepínač vypněte a jméno se vypíše pod portrétem.

### Další nálezy na staré stránce

| Nález | Detail |
|---|---|
| Prázdné sloupce a widgety | **6** — z toho jeden widget „Slides“ (další prvek Elementor Pro, který nic nevykreslí) |
| Stáří fotek | Vedení z června 2026, **předsedové klubů z března 2021** — pět let staré |
| Zastaralé značky | `<font color="#000000">` — prvek zrušený v HTML 4.01 (1999) |
| Neplatné vnoření | `<div>` uvnitř `<h2>` |
| Zbytky editoru | `class="elementor-inline-editing pen"` a `data-pen-placeholder="Pište zde…"` na živém webu |

---

## Dokumenty

> ⚠️ **Ani jeden z třinácti dokumentů nebyl na webu svazu.** Všechny visely
> na cizích sdílecích službách: `jmp.sh` (6×), `docdro.id` (4×), `tetify.eu` (3×).

Mezi nimi:

- Stanovy schválené na VH 1. 6. 2026
- Registrační řád schválený na VH 1. 6. 2026
- Statut Kontrolní komise ČSR
- Soutěžní řády pro krátkou i dlouhou dráhu
- Směrnice o vzdělávání trenérů

Tohle jsou **zakládající a závazné dokumenty svazu**. Leží na účtech u služeb,
které svaz neprovozuje. Až účet vyprší, služba skončí nebo smaže neaktivní
soubory, stanovy Českého svazu rychlobruslení z internetu zmizí — a nikdo
o tom nemusí vědět měsíce.

### Co s tím dělá nová šablona

Nepřepíše to sama. Ale postaví správné řešení jako **výchozí cestu** a to
špatné udělá **viditelným**:

| | |
|---|---|
| U dokumentu je na prvním místě **výběr souboru z knihovny médií** | Odkaz ven je až druhá možnost |
| U cizího odkazu se návštěvníkovi **ukáže doména** a ikona odchodu z webu | Ví, že opouští web svazu |
| U místního souboru se ukáže **typ a velikost** a odkaz soubor **stáhne** | WordPress obojí zná sám |
| V administraci je u dokumentu **⚠ cizí úložiště** a nahoře upozornění s počtem | Nedá se to přehlédnout |

### Nasazení

1. **Dokumenty → Hromadné vložení** — založí záznamy s dosavadními odkazy.
2. Stránku Dokumenty přepnout na šablonu **„ČSR — Dokumenty"**.
3. **Postupně** stáhnout soubory z jmp.sh, docdro.id a tetify.eu, nahrát je do
   knihovny médií a u každého dokumentu vybrat. Upozornění v administraci
   odpočítává, kolik jich zbývá.

```
název | odkaz | rubrika | platnost od
```

Rubriky: `stanovy`, `registrace`, `souteze`, `treneri`, `reprezentace`, `etika`.

### Co stránka umí

- **Hledání bez diakritiky** — „rad“ najde všech pět dokumentů se slovem „řád“.
- **Filtr podle rubriky**, sestavený jen z rubrik, které jsou opravdu použité.
- Typ a velikost souboru čte i odečítač obrazovky, ne jen oko.

---

### Jedna šablona pro víc stránek s dokumenty

Stránka *Pravidla a předpisy ISU* je obsahově totéž — čtyři PDF, všechna na
`jmp.sh`. (V HTML má dokonce **stejná ID prvků Elementoru** jako Dokumenty,
takže vznikla duplikováním.) Novou šablonu si nezaslouží.

U stránky se šablonou „ČSR — Dokumenty" je proto vpravo box **Dokumenty — co
se má vypsat** s výběrem rubriky:

| Volba | Výsledek |
|---|---|
| Všechny dokumenty | Výpis všeho + lišta filtrů |
| Konkrétní rubrika | Jen ta rubrika, **lišta filtrů se nezobrazí** |

Jedna šablona tak obslouží *Dokumenty*, *Pravidla a předpisy ISU*, a stejně tak
*Smlouvy*, *Archiv* nebo *Základní dokumenty* antidopingu — každá stránka ukáže
svou rubriku. Nabídka bere **všechny rubriky, které v administraci existují**,
takže si můžete přidat vlastní.

> **K ověření:** všechna čtyři pravidla ISU jsou označená rokem **2024** a
> stránka se od září 2025 needitovala. ISU vydává pravidla po kongresu, který
> se koná v sudých letech — zkontrolujte, jestli mezitím nevyšla novější verze.
> Odsud to ověřit nedokážu.

---

### Odkaz na vydavatele není totéž co soubor na úložišti

Antidopingová stránka odhalila, že moje původní varování bylo příliš hrubé.
Ze šesti dokumentů vedou **čtyři na antidoping.cz** — a to je **správně**:

- Světový antidopingový kodex, Seznam zakázaných látek a letáky vydává
  **Antidopingový výbor ČR**, ne svaz.
- Kopie na svazovém webu by zastarala a **rozcházela se se závazným zněním.**
  U předpisů, podle kterých se trestá, je to horší než odkaz ven.

Šablona proto rozlišuje dva druhy odkazů:

| | Zobrazení | Počítá se do upozornění? |
|---|---|---|
| Soubor svazu na sdílecí službě | žlutě, doména (`docdro.id`) | **ano** — patří na web svazu |
| Dokument jiného vydavatele | modře, jméno vydavatele | **ne** — takhle to má být |

U dokumentu je nové pole **Vydavatel dokumentu**. Vyplní se samo u známých
domén (`antidoping.cz`, `wada-ama.org`, `isu.org`, `olympic.cz`, `msmt.cz`),
u ostatních se dá zadat ručně. Seznam domén jde rozšířit filtrem
`csr_official_hosts`.

Ze 17 dokumentů, které jsem dosud napočítal jako „mimo web svazu", tak
**4 patří k jinému vydavateli** a zůstanou odkazem — a přibyly 2 na docdro.id.
Skutečný počet souborů k přesunutí je **15**.

> **K ověření — a spěchá to:** *Seznam zakázaných látek* na stránce je z roku
> **2021**. WADA ho vydává **každý rok** s účinností od 1. ledna. Závodník,
> který si podle téhle stránky ověřuje povolený lék, čte pět let starý seznam.
> Stránka se naposledy upravovala v červenci 2023.

---

## Fotogalerie

Původní stránka měla **15 nadpisů, ale jen 6 z nich mělo pod sebou fotky.**
Zbylých 10 používalo widget „Gallery" z Elementoru Pro — bez licence nevykreslí
nic, takže návštěvník viděl nadpis a pod ním prázdno:

> Mistrovství světa Heerenveen 2023 · Světový pohár Tomaszow (2×) ·
> MS juniorů Inzell 2023 · MČR juniorů Tomaszow 2022 · SP Stavanger 2022 ·
> Olympijský festival Brno 2022 · SP juniorů Innsbruck 2022 ·
> MČR Tomaszow 2021 · Ostatní

### Alba místo jedné dlouhé stránky

Nový typ obsahu **Fotogalerie** — jedno album = jeden záznam s datem, místem,
autorem fotografií a rubrikou. Přehled ukáže obálky, každé album má svou
stránku. Na jednu stránku se tak nenačítá 133 fotek naráz.

| Dřív | Teď |
|---|---|
| Náhledy oříznuté na čtverec 500×500 | Fotka si drží svůj poměr stran |
| Simple Lightbox **i** Elementor lightbox naráz | Jeden vlastní, bez jQuery |
| Titulek v lightboxu = název souboru | Popis fotky, nebo nic |
| Nadpis bez fotek vidí každý | Prázdné album vidí jen správce |

Lightbox ovládá klávesnice (šipky listují, Esc zavírá, Tab neuteče ven)
a tlačítka mají 44–48 px, aby se dala trefit prstem.

### Alternativní texty

**Ani jedna ze 133 fotek nemá vyplněný alternativní text.** Nevidomí
návštěvníci se o nich nedozvědí nic a vyhledávače je nenajdou.

Šablona proto počítá, kolik fotek v albu popis nemá, ukazuje to
u alba, ve sloupci v seznamu i jako upozornění nahoře. Text se zadává
v Médiích u fotky — WordPress si ho pamatuje, takže stačí jednou.

---

## Kontakty

Na původní stránce byly dvě chyby, které nešly poznat od pohledu.

### E-mail nikam nevedl

```html
<a href="http://info@speedskating.cz">info@speedskating.cz</a>
```

Prohlížeč tohle bere jako **adresu webu**, ne jako e-mail — hledá server
jménem `info@speedskating.cz`. Nejdůležitější odkaz na stránce nefungoval.
Správně je `mailto:`.

### Do textu prosákl markdown

Návštěvník čte doslova `[www.speedskating.cz](https://www.speedskating.cz)`.
Někdo vložil do editoru text v markdownu a WordPress ho nezpracoval.
Je to i v popisu pro vyhledávače, takže **takhle to vypadá i v Googlu**.

Šablona proto na stránkách hledá vzor `[text](adresa)` a v seznamu stránek
upozorní na každou, kde zůstal.

### Ostatní

| Dřív | Teď |
|---|---|
| `+420602495327` jako text | odkaz `tel:`, na mobilu jde vytočit |
| bez mezer | `+420 602 495 327` |
| „Adresa" odkazovala na web | poštovní adresa + odkaz na mapu |
| adresa jen tady | jeden zdroj pro patičku i kontakty |
| lidé napsaní ručně | z databáze Lidé ve svazu, stejné jako Struktura |

Přidal jsem taky strukturovaná data s adresou, telefonem a IČO —
Rank Math sám vypisuje jen název a logo, takže se svaz v Googlu
neukazoval s kontaktními údaji.

---

## Jak to funguje

Závodníka zadáte **jednou** v sekci **Reprezentanti**: jméno, fotka
(náhledový obrázek), ročník, klub, role. Pak u něj zaškrtnete **sezóny**
a **týmy**, ve kterých je. Stránka soupisky si vybere kombinaci
sezóna + tým a vypíše se sama.

Když závodník postoupí z juniorů mezi seniory, jen překliknete zaškrtávátko.
Fotku ani údaje nezadáváte znovu.

### Založení stránky soupisky

1. **Stránky → Vytvořit stránku**, název např. „SS – Junioři"
2. **Atributy stránky → Šablona** → **„ČSR — Soupiska reprezentace"**
3. Uložit
4. Vpravo se objeví box **„Soupiska — co se má vypsat"** → vyberte sezónu a tým
5. Publikovat

Volný text napsaný do editoru stránky (kritéria, poznámky) se vypíše
pod soupiskou.

### Trenéři a realizační tým

U člověka nastavte **Role** na *Trenér* nebo *Realizační tým*. Vypíšou se
zvlášť pod závodníky, v menších kartách.

### Pořadí na soupisce

Řídí se polem **Pořadí** v boxu *Atributy stránky* u každého reprezentanta
(nižší číslo = dřív). Při stejném čísle se řadí podle abecedy.

### Fotky

Nahrávají se jako **náhledový obrázek**. Ideálně na výšku v poměru 3 : 4
(např. 900 × 1200 px). Bez fotky se zobrazí zástupná silueta — vypadá
záměrně, ne jako chyba.

---

## Migrace stávajících soupisek

Máte kolem 30 soupisek napříč sezónami. Postup, který to zkrátí:

### 1. Založte sezóny

**Reprezentanti → Sezóny** → přidejte `2026-2027`, `2025-2026`, …

Týmy (SS – Junioři, SS – Senioři, SS – Sledovaní, ST – Junioři, ST – Senioři,
ST – Sledovaní) se založí samy při prvním načtení.

### 2. Vložte lidi hromadně

**Reprezentanti → Hromadné přidání**. Vyberte sezónu a tým, do textového pole
vložte soupisku — každý na vlastní řádek:

```
Sára Hlušková | 2007 | BK Náchod
Barbora Sáblíková | 2010 | BK Žďár
Filip Jílek | 2010 | BZK Praha
Jiří Macháček | | | trenér
```

Oddělovat můžete svislítkem, středníkem nebo tabulátorem — takže jde vložit
i sloupce zkopírované z tabulky.

> **Kdo už v databázi je, nezaloží se podruhé** — jen se přiřadí do vybrané
> sezóny a týmu. Díky tomu můžete projít soupisky jednu po druhé a lidé,
> kteří jsou ve více z nich, se nebudou duplikovat.

### 3. Doplňte fotky

Až nakonec, u každého člověka v **Reprezentanti**. Seznam má sloupec s náhledem,
takže hned vidíte, komu fotka chybí.

---

## Jak to funguje

**Články** se načítají běžným `WP_Query` z příspěvků — první jako velká karta,
zbytek jako mřížka. Filtry se generují ze skutečných kategorií, které mají
aspoň jeden příspěvek. Tlačítko „Zobrazit více" donačítá další stránku bez
reloadu.

**Akce** se berou z pluginu The Events Calendar. Bez něj — a také když nejsou
naplánované žádné budoucí závody — se sekce na úvodní stránce nezobrazí vůbec.
Není to chyba, jen se nemá co vypsat.

**Galerie** ukazuje poslední obrázky z knihovny médií.

---

## Výsledky

Původní stránky sezón (`/speed-skating-2025-2026/` a spol.) měly výsledky
jako PDF v rámu z `docs.google.com/viewer`. Mělo to čtyři následky:

* **Na mobilu se nevešly.** Rám měl pevných 640 px, telefon má 375 px.
* **Vyhledávače o výsledcích nevěděly.** V HTML stránky nebyl žádný text —
  popis stránky pro Google zněl doslova „Muži Ženy". Kdo hledal jméno
  závodníka, svazový web nenašel.
* **Nešlo v nich hledat ani je přečíst čtečkou pro nevidomé.**
* **Každý návštěvník poslal svoji IP adresu Googlu**, aby si mohl přečíst
  výsledky českého svazu.

Nová šablona **ČSR — Výsledky** vypisuje skutečnou tabulku.

### Jak se tabulka vkládá

1. *Výsledky → Přidat tabulku*, název například `Muži`.
2. V Excelu nebo Google Tabulkách označte tabulku i s hlavičkou, `Ctrl+C`.
3. Vložte do pole **Tabulka**. Sloupce oddělené tabulátory, středníky nebo
   čárkami se rozeberou samy; **první řádek jsou názvy sloupců**.
4. Vpravo vyberte **sezónu** a **disciplínu**, pořadí na stránce určuje
   pole *Pořadí* v *Atributech stránky*.

Pod formulářem se hned ukáže náhled prvních tří řádků, ať je vidět, že se
sloupce rozdělily správně.

PDF může zůstat jako **náhradní soubor** — nabídne se ke stažení pod
nadpisem tabulky. Pokud vyplníte jen soubor a tabulku ne, administrace na to
upozorní: soubor nikdo nenajde přes vyhledávání.

### Co šablona pozná sama

| Věc | Jak |
| --- | --- |
| Číselné sloupce | podle většiny buněk; sázejí se doprava tabulkovými číslicemi, aby šly srovnat okem |
| Sloupec s pořadím | podle názvu (`Poř.`, `#`) nebo podle hodnot `1.` `2.` `3.` |
| Medailové pořadí | první tři řádky dostanou barevný pruh — ale číslo ve sloupci zůstává, barva není jediný nositel informace |

Časy se řadí správně i v českém zápisu: `36,55` je míň než `1.11,20`
a `12.29,63` je nejvíc. Bez JavaScriptu zůstane pořadí tak, jak ho zadal
správce — což je u výsledků to správné výchozí pořadí.

### Jedna šablona pro dvanáct stránek

V boxu *Výsledky — co se má vypsat* si každá stránka vybere sezónu
a disciplínu. Díky tomu obslouží jedna šablona všech dvanáct položek
z menu *Výsledky*. Na konci stránky je rozcestník na ostatní sezóny —
bez něj se mezi nimi člověk prokliká jen přes menu, což na mobilu
znamená čtyři poklepání.

### Sezóny se sdílejí s reprezentací

Taxonomii sezón **neregistrujeme znovu** — má ji už modul reprezentace
(`CSR_TAX_SEASON`). Druhá registrace stejného názvu by ji přepsala
a soupisky by o ni přišly. Výsledky se k ní jen přihlásí, takže obě sekce
sdílejí jeden seznam a „2025–2026" znamená všude totéž.

## České rekordy

Původní stránka měla v HTML jen prázdné `<div>` a JavaScript, který si
rekordy stahoval ze `speedskatingresults.com` až v prohlížeči návštěvníka.
Proto té stránce **chyběl i popis pro vyhledávače** — Rank Math neměl
z čeho ho vzít. Při výpadku cizího serveru se místo rekordů ukázalo
čtyřikrát „Chyba při načítání dat".

Teď data stahuje WordPress sám:

* **jednou denně na pozadí** (naplánovaná úloha), ne při návštěvě stránky;
* výsledek se ukládá do cache **a do trvalé zálohy**;
* když cizí server neodpoví, zůstanou **poslední známé hodnoty** a u nich
  datum, kdy byly naposledy ověřené;
* stránka je obyčejné HTML — čitelné bez JavaScriptu, dohledatelné Googlem,
  a prohlížeč návštěvníka nikam ven nechodí.

Stav a ruční stažení najdete v *Výsledky → České rekordy*.

Oproti původní verzi navíc:

* **zmizel sloupec „Pohlaví"** — u tabulky nadepsané *Senioři Muži* stálo
  šestkrát pod sebou „Muž";
* **data se píšou česky** — `16. 1. 2000` místo `2000-01-16`.

Short track rekordy tenhle zdroj nevede. Když je svaz eviduje, přidají se
ručně jako tabulka se zaškrtnutým *Zobrazit na stránce Českých rekordů*.

## Výkon a přístupnost

- **Žádná externí knihovna** — vlastní CSS a JS, dohromady ~24 kB.
  Žádná jQuery, žádný Swiper, žádné animační frameworky.
- **Assety se načítají jen na této stránce**, zbytek webu zůstává beze změny.
- Verzování podle `filemtime()` — po úpravě souboru se cache obnoví sama.
- Obrázky mají `loading="lazy"`, hero obrázek `fetchpriority="high"`.
- Bez JavaScriptu zůstane stránka **plně čitelná** — animace jsou jen ozdoba.
- Respektuje `prefers-reduced-motion` i `prefers-color-scheme`.
- Ovladatelné klávesnicí, viditelný focus, `aria-*` atributy, přeskakovací
  odkaz na obsah. Vyhledávání otevřete klávesou <kbd>/</kbd>.

---

## Než nasadíte

PHP soubory nebylo možné spustit na vývojovém stroji (není tam nainstalované),
takže prošly jen statickou kontrolou: struktura závorek a bloků, existence všech
46 funkcí a konstant, správnost použitých WordPress funkcí a shoda všech 142 klíčů
nastavení mezi šablonou a registrem. Vzhled byl ověřen v prohlížeči na šířkách
375 / 663 / 1280 / 1400 px ve světlém i tmavém režimu.

**Nasazujte nejdřív na testovacím webu nebo si udělejte zálohu.**

Návrat na starou podobu je u obou šablon stejný — v *Atributech stránky*
přepnout šablonu zpět na **Výchozí**. Elementorový obsah zůstává uložený.
