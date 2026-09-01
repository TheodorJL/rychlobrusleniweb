<?php
/**
 * Nastavení úvodní stránky v administraci WordPressu.
 *
 * Vše se edituje ve Vzhled → Přizpůsobit → „Úvodní stránka ČSR" s živým
 * náhledem. Pole se definují v jednom registru níž, registrace do Customizeru
 * pak probíhá ve smyčce — přidání dalšího pole je jeden řádek.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 1. IKONY PRO DLAŽDICE RYCHLÝCH ODKAZŮ
 * ====================================================================== */

/**
 * Knihovna ikon — klíč => [popisek, SVG obsah].
 */
function csr_icon_library() {
	return array(
		'kalendar'  => array( 'Kalendář',      '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/>' ),
		'graf'      => array( 'Graf/výsledky', '<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>' ),
		'hvezda'    => array( 'Hvězda',        '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3Z"/>' ),
		'dokument'  => array( 'Dokument',      '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>' ),
		'lide'      => array( 'Lidé/kluby',    '<path d="M17 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="3.5"/><path d="M22 20v-2a4 4 0 0 0-3-3.9M16.5 3.6a3.5 3.5 0 0 1 0 6.8"/>' ),
		'brusle'    => array( 'Brusle',        '<path d="M4 17c5 1.6 10.5.4 14-3s5-9 4.6-13"/><path d="M5 20h13"/>' ),
		'pohar'     => array( 'Pohár',         '<path d="M8 4h8v5a4 4 0 0 1-8 0V4Z"/><path d="M16 5h3v2a3 3 0 0 1-3 3M8 5H5v2a3 3 0 0 0 3 3M10 17h4M9 20h6"/>' ),
		'info'      => array( 'Informace',     '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>' ),
		'stopky'    => array( 'Stopky',        '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 1.7M9.5 2h5"/>' ),
		'vlajka'    => array( 'Vlajka',        '<path d="M5 21V4M5 4h13l-2.5 4L18 12H5"/>' ),
	);
}

/**
 * Vrátí SVG cesty ikony podle klíče.
 */
function csr_icon_paths( $key ) {
	$lib = csr_icon_library();
	return isset( $lib[ $key ] ) ? $lib[ $key ][1] : $lib['info'][1];
}

/**
 * Seznam ikon pro rozbalovací nabídku v Customizeru.
 */
function csr_icon_choices() {
	$out = array();
	foreach ( csr_icon_library() as $key => $data ) {
		$out[ $key ] = $data[0];
	}
	return $out;
}

/* =========================================================================
 * 2. REGISTR POLÍ
 * ====================================================================== */

/**
 * Vygeneruje opakující se skupinu polí (statistiky, dlaždice, partneři…).
 *
 * @param string $prefix   Předpona klíče, např. "csr_stat".
 * @param int    $count    Kolik položek.
 * @param array  $template Šablona polí; %d v popisku se nahradí pořadím.
 * @param array  $defaults Výchozí hodnoty po položkách [1 => [...], 2 => [...]].
 */
function csr_repeat_fields( $prefix, $count, $template, $defaults = array() ) {
	$out = array();
	for ( $i = 1; $i <= $count; $i++ ) {
		foreach ( $template as $suffix => $field ) {
			$field['label'] = sprintf( $field['label'], $i );
			if ( isset( $defaults[ $i ][ $suffix ] ) ) {
				$field['default'] = $defaults[ $i ][ $suffix ];
			} elseif ( ! isset( $field['default'] ) ) {
				$field['default'] = '';
			}
			// Popis dáváme jen k prvnímu, ať panel není ukecaný.
			if ( $i > 1 ) {
				unset( $field['desc'] );
			}
			$out[ "{$prefix}{$i}_{$suffix}" ] = $field;
		}
	}
	return $out;
}

/**
 * Kompletní registr nastavení: sekce => pole.
 */
function csr_settings_registry() {
	$sections = array();

	/* ---- Značka a logo ---- */
	$sections['brand'] = array(
		'title'  => 'Značka a logo',
		'desc'   => 'Logo se bere z „Identity webu", pokud tu nenahrajete jiné.',
		'fields' => array(
			'csr_logo' => array(
				'type'  => 'image',
				'label' => 'Logo v hlavičce a patičce',
				'desc'  => 'Nevyplněno = použije se logo webu z Vzhled → Přizpůsobit → Identita webu.',
			),
			'csr_logo_invert' => array(
				'type'    => 'checkbox',
				'label'   => 'Převést logo na bílé',
				'desc'    => 'Zapněte jen u jednobarevného tmavého loga, které by na tmavé hlavičce zaniklo. U barevného loga to nezapínejte — převod smaže i barvy a zbude bílá plocha.',
				'default' => false,
			),
			'csr_logo_height' => array(
				'type'    => 'number',
				'label'   => 'Výška loga v hlavičce (px)',
				'desc'    => 'Hlavička se logu přizpůsobí. Nad 60 px roste i ona.',
				'default' => 60,
				'min'     => 20,
				'max'     => 90,
			),
			'csr_brand_show_text' => array(
				'type'    => 'checkbox',
				'label'   => 'Zobrazit název vedle loga',
				'desc'    => 'Nechte vypnuté, pokud logo název už obsahuje.',
				'default' => false,
			),
			'csr_brand_name' => array( 'type' => 'text', 'label' => 'Název — první řádek', 'default' => 'Český svaz' ),
			'csr_brand_sub'  => array( 'type' => 'text', 'label' => 'Název — druhý řádek', 'default' => 'Rychlobruslení' ),
		),
	);

	/* ---- Hero ---- */
	$sections['hero'] = array(
		'title'  => 'Úvodní blok (hero)',
		'fields' => array(
			'csr_hero_image' => array(
				'type'  => 'image',
				'label' => 'Fotka na pozadí',
				'desc'  => 'Ideálně 2400 × 1400 px, s volným místem vlevo pro text. Bez fotky se zobrazí animované grafické pozadí.',
			),
			'csr_hero_show_badge'  => array( 'type' => 'checkbox', 'label' => 'Zobrazit odznak nad nadpisem', 'default' => true ),
			'csr_hero_badge_live'  => array( 'type' => 'text', 'label' => 'Odznak — červená část', 'default' => 'Živě' ),
			'csr_hero_badge_text'  => array( 'type' => 'text', 'label' => 'Odznak — text',        'default' => 'Sezóna 2025 / 2026' ),
			'csr_hero_line1' => array( 'type' => 'text', 'label' => 'Nadpis — 1. řádek', 'default' => 'Rychlost' ),
			'csr_hero_line2' => array( 'type' => 'text', 'label' => 'Nadpis — 2. řádek', 'default' => 'má' ),
			'csr_hero_line3' => array(
				'type'    => 'text',
				'label'   => 'Nadpis — 3. řádek (barevný)',
				'desc'    => 'Tento řádek je zvýrazněný ledovým přechodem.',
				'default' => 'české jméno',
			),
			'csr_hero_lead' => array(
				'type'    => 'textarea',
				'label'   => 'Úvodní odstavec',
				'default' => 'Oficiální web Českého svazu rychlobruslení. Výsledky, nominace, kalendář závodů a vše kolem speed skatingu i short tracku na jednom místě.',
			),
			'csr_hero_btn1_label' => array( 'type' => 'text', 'label' => '1. tlačítko — text',  'default' => 'Aktuality' ),
			'csr_hero_btn1_url'   => array( 'type' => 'url',  'label' => '1. tlačítko — odkaz', 'default' => '#aktuality' ),
			'csr_hero_btn2_label' => array( 'type' => 'text', 'label' => '2. tlačítko — text',  'default' => 'Kalendář závodů' ),
			'csr_hero_btn2_url'   => array( 'type' => 'url',  'label' => '2. tlačítko — odkaz', 'default' => '/akce/' ),
			'csr_hero_show_stats' => array( 'type' => 'checkbox', 'label' => 'Zobrazit čísla pod úvodním blokem', 'default' => true ),
			'csr_hero_rink'       => array(
				'type'    => 'checkbox',
				'label'   => 'Animovaný ovál vpravo v úvodním bloku',
				'desc'    => 'Trojrozměrná čtyřistametrová dráha se světelnými stopami. Jen na širokých displejích; kdo má v systému vypnuté animace, uvidí statické pozadí.',
				'default' => true,
			),
		),
	);

	/* ---- Čísla pod hero ---- */
	$sections['stats'] = array(
		'title'  => 'Čísla pod úvodním blokem',
		'desc'   => 'Čtyři údaje o svazu. Prázdný popisek číslo skryje.',
		'fields' => csr_repeat_fields(
			'csr_stat',
			4,
			array(
				'source'  => array(
					'type'    => 'select',
					'label'   => '%d. číslo — odkud brát',
					'desc'    => 'Spočítá se z obsahu webu. Vlastní hodnotu pište jen tehdy, když ji máte čím doložit — návštěvník nepozná, že je to odhad.',
					'choices' => csr_stat_source_choices(),
				),
				'value'   => array( 'type' => 'number', 'label' => '%d. číslo — vlastní hodnota', 'desc' => 'Platí jen při volbě „Vlastní hodnota". Prázdné nebo nula číslo skryje.', 'min' => 0, 'max' => 100000 ),
				'suffix'  => array( 'type' => 'text', 'label' => '%d. číslo — přípona', 'desc' => 'Např. „+" nebo „×".' ),
				'label'   => array( 'type' => 'text', 'label' => '%d. číslo — popisek', 'desc' => 'U vlastní hodnoty je nutný — bez popisku se číslo nezobrazí. U spočítaného zdroje se doplní sám.' ),
				'nogroup' => array( 'type' => 'checkbox', 'label' => '%d. číslo — nedělit tisíce', 'desc' => 'Zapněte u letopočtů, ať se nezobrazí „1 993".' ),
			),
			array(
				/*
				 * Výchozí hodnoty se počítají z obsahu webu. Dřív tu byla
				 * čísla napsaná natvrdo — a tedy vymyšlená; návštěvník
				 * nemá jak poznat, že „480 závodníků" je odhad.
				 */
				1 => array( 'source' => 'kluby' ),
				2 => array( 'source' => 'zavodnici' ),
				3 => array( 'source' => 'alba' ),
				4 => array( 'source' => 'dokumenty' ),
			)
		),
	);

	/* ---- InfoFeed lišta ---- */
	$sections['ticker'] = array(
		'title'  => 'Běžící lišta (InfoFeed)',
		'fields' => array(
			'csr_ticker_show'  => array( 'type' => 'checkbox', 'label' => 'Zobrazit lištu', 'default' => true ),
			'csr_ticker_label' => array( 'type' => 'text', 'label' => 'Popisek vlevo', 'default' => 'InfoFeed' ),
			'csr_ticker_count' => array( 'type' => 'number', 'label' => 'Kolik článků zobrazit', 'default' => 5, 'min' => 2, 'max' => 15 ),
		),
	);

	/* ---- Rychlé odkazy ---- */
	$sections['quick'] = array(
		'title'  => 'Dlaždice rychlých odkazů',
		'desc'   => 'Prázdný popisek dlaždici skryje.',
		'fields' => csr_repeat_fields(
			'csr_quick',
			5,
			array(
				'label' => array( 'type' => 'text', 'label' => '%d. dlaždice — nadpis' ),
				'desc'  => array( 'type' => 'text', 'label' => '%d. dlaždice — popisek' ),
				'url'   => array( 'type' => 'url',  'label' => '%d. dlaždice — odkaz' ),
				'icon'  => array( 'type' => 'select', 'label' => '%d. dlaždice — ikona', 'choices' => csr_icon_choices(), 'default' => 'info' ),
			),
			array(
				1 => array( 'label' => 'Kalendář',     'desc' => 'Termíny závodů a soustředění', 'url' => '/akce/',          'icon' => 'kalendar' ),
				2 => array( 'label' => 'Výsledky',     'desc' => 'Startovky, časy a české rekordy', 'url' => '/ceske-rekordy/', 'icon' => 'graf' ),
				3 => array( 'label' => 'Reprezentace', 'desc' => 'Nominace a složení týmů',      'url' => '/ss-seniori-5/',  'icon' => 'hvezda' ),
				4 => array( 'label' => 'Dokumenty',    'desc' => 'Směrnice, formuláře a předpisy', 'url' => '/dokumenty/',   'icon' => 'dokument' ),
				5 => array( 'label' => 'Kluby',        'desc' => 'Kde u nás bruslit',            'url' => '/kluby-2/',       'icon' => 'lide' ),
			)
		),
	);

	/* ---- Aktuality ---- */
	$sections['news'] = array(
		'title'  => 'Sekce s články',
		'fields' => array(
			'csr_news_show'         => array( 'type' => 'checkbox', 'label' => 'Zobrazit sekci', 'default' => true ),
			'csr_news_eyebrow'      => array( 'type' => 'text', 'label' => 'Nadřazený popisek', 'default' => 'Novinky ze svazu' ),
			'csr_news_title'        => array( 'type' => 'text', 'label' => 'Nadpis', 'default' => 'Co je' ),
			'csr_news_title_accent' => array( 'type' => 'text', 'label' => 'Nadpis — zvýrazněné slovo', 'desc' => 'Vykreslí se ledovým přechodem za hlavním nadpisem.', 'default' => 'nového' ),
			'csr_news_lead'         => array( 'type' => 'textarea', 'label' => 'Popis pod nadpisem', 'default' => 'Zprávy z reprezentace, závodů i organizace svazu. Filtrujte podle toho, co vás zajímá.' ),
			'csr_news_count'        => array( 'type' => 'number', 'label' => 'Kolik článků zobrazit', 'default' => 6, 'min' => 3, 'max' => 24 ),
			'csr_news_category'     => array( 'type' => 'category', 'label' => 'Zobrazit jen z kategorie', 'desc' => 'Nechte na „Všechny kategorie" pro běžný výpis.', 'default' => 0 ),
			'csr_news_show_filters' => array( 'type' => 'checkbox', 'label' => 'Zobrazit filtry kategorií', 'default' => true ),
			'csr_news_filter_count' => array( 'type' => 'number', 'label' => 'Kolik filtrů zobrazit', 'default' => 5, 'min' => 2, 'max' => 10 ),
		),
	);

	/* ---- Akce ---- */
	$sections['events'] = array(
		'title'  => 'Nadcházející akce',
		'desc'   => 'Bere se z pluginu The Events Calendar. Bez něj se sekce nezobrazí.',
		'fields' => array(
			'csr_events_show'         => array( 'type' => 'checkbox', 'label' => 'Zobrazit sekci', 'default' => true ),
			'csr_events_eyebrow'      => array( 'type' => 'text', 'label' => 'Nadřazený popisek', 'default' => 'Kalendář' ),
			'csr_events_title'        => array( 'type' => 'text', 'label' => 'Nadpis', 'default' => 'Nadcházející' ),
			'csr_events_title_accent' => array( 'type' => 'text', 'label' => 'Nadpis — zvýrazněné slovo', 'default' => 'akce' ),
			'csr_events_count'        => array( 'type' => 'number', 'label' => 'Kolik akcí zobrazit', 'default' => 4, 'min' => 1, 'max' => 12 ),
			'csr_events_url'          => array( 'type' => 'url', 'label' => 'Odkaz na celý kalendář', 'default' => '/akce/' ),
		),
	);

	/* ---- Úspěchy ---- */
	$sections['achieve'] = array(
		'title'  => 'Sekce úspěchů',
		'fields' => array_merge(
			array(
				'csr_achieve_show'         => array( 'type' => 'checkbox', 'label' => 'Zobrazit sekci', 'default' => true ),
				'csr_achieve_eyebrow'      => array( 'type' => 'text', 'label' => 'Nadřazený popisek', 'default' => 'Naše stopa na ledě' ),
				'csr_achieve_title'        => array( 'type' => 'text', 'label' => 'Nadpis', 'default' => 'Česká' ),
				'csr_achieve_title_accent' => array( 'type' => 'text', 'label' => 'Nadpis — zvýrazněné slovo', 'default' => 'rychlobruslařská' ),
				'csr_achieve_title_rest'   => array( 'type' => 'text', 'label' => 'Nadpis — zbytek', 'default' => 'historie' ),
				'csr_achieve_lead'         => array( 'type' => 'textarea', 'label' => 'Text', 'default' => 'Přehled platných českých rekordů na dlouhé dráze.', 'desc' => 'Původní text tu popisoval historii, kterou si šablona vymyslela. Napište sem, co o svazu platí.' ),
				'csr_achieve_btn_label'    => array( 'type' => 'text', 'label' => 'Tlačítko — text', 'default' => 'Přehled medailí a rekordů' ),
				'csr_achieve_btn_url'      => array( 'type' => 'url',  'label' => 'Tlačítko — odkaz', 'default' => '/ceske-rekordy/' ),
				/*
				 * Medaile ani počítadla níž se z ničeho na webu spočítat
				 * nedají. Ve výchozím stavu jsou proto vypnuté a prázdné —
				 * dokud je někdo nevyplní podle skutečné evidence svazu,
				 * je lepší je neukazovat než uvést číslo, které si vymyslel
				 * autor šablony.
				 */
				'csr_medals_show'          => array( 'type' => 'checkbox', 'label' => 'Zobrazit medailové pruhy', 'desc' => 'Zapněte, až budete mít skutečné počty.', 'default' => false ),
				'csr_medal_gold'           => array( 'type' => 'number', 'label' => 'Počet zlatých',   'default' => 0, 'min' => 0, 'max' => 9999 ),
				'csr_medal_silver'         => array( 'type' => 'number', 'label' => 'Počet stříbrných', 'default' => 0, 'min' => 0, 'max' => 9999 ),
				'csr_medal_bronze'         => array( 'type' => 'number', 'label' => 'Počet bronzových', 'default' => 0, 'min' => 0, 'max' => 9999 ),
			),
			csr_repeat_fields(
				'csr_counter',
				4,
				array(
					'source' => array(
						'type'    => 'select',
						'label'   => '%d. počítadlo — odkud brát',
						'desc'    => 'Spočítá se z obsahu webu. Vlastní hodnotu pište jen tehdy, když ji máte čím doložit.',
						'choices' => csr_stat_source_choices(),
					),
					'value'  => array( 'type' => 'number', 'label' => '%d. počítadlo — vlastní hodnota', 'min' => 0, 'max' => 100000 ),
					'suffix' => array( 'type' => 'text', 'label' => '%d. počítadlo — přípona' ),
					'label'  => array( 'type' => 'text', 'label' => '%d. počítadlo — popisek' ),
					'gold'   => array( 'type' => 'checkbox', 'label' => '%d. počítadlo — zlatě', 'desc' => 'Zvýrazní číslo zlatou barvou.' ),
				),
				array(
					// Rekordy se počítají ze stažených dat. Zbylá tři počítadla
					// jsou prázdná — medaile, účasti na olympiádách ani stáří
					// sportu se z obsahu webu spočítat nedají.
					1 => array( 'source' => 'rekordy', 'gold' => true ),
					2 => array(),
					3 => array(),
					4 => array(),
				)
			)
		),
	);

	/* ---- CTA ---- */
	$sections['cta'] = array(
		'title'  => 'Výzva k akci (barevný pruh)',
		'fields' => array(
			'csr_cta_show'      => array( 'type' => 'checkbox', 'label' => 'Zobrazit sekci', 'default' => true ),
			'csr_cta_title'     => array( 'type' => 'text', 'label' => 'Nadpis', 'default' => 'Rozjeďte rychlobruslení ve svém městě' ),
			'csr_cta_text'      => array( 'type' => 'textarea', 'label' => 'Text', 'default' => 'Máte trenérské zkušenosti, baví vás sport, nebo hledáte nový impuls? Přihlaste se na seminář, který vám ukáže, jak lze rychlobruslení reálně rozjet i u vás.' ),
			'csr_cta_btn_label' => array( 'type' => 'text', 'label' => 'Tlačítko — text', 'default' => 'Chci vědět víc' ),
			'csr_cta_btn_url'   => array( 'type' => 'url',  'label' => 'Tlačítko — odkaz', 'default' => '/rozjedte-rychlobrusleni-ve-svem-meste/' ),
		),
	);

	/* ---- Galerie ---- */
	$sections['gallery'] = array(
		'title'  => 'Fotogalerie',
		'fields' => array(
			'csr_gallery_show'    => array( 'type' => 'checkbox', 'label' => 'Zobrazit sekci', 'default' => true ),
			'csr_gallery_eyebrow' => array( 'type' => 'text', 'label' => 'Nadřazený popisek', 'default' => 'Ze závodů' ),
			'csr_gallery_title'   => array( 'type' => 'text', 'label' => 'Nadpis', 'default' => 'Fotogalerie' ),
			'csr_gallery_count'   => array( 'type' => 'number', 'label' => 'Kolik alb zobrazit', 'desc' => 'Ukazují se alba z Fotogalerie, ne obrázky z knihovny médií.', 'default' => 6, 'min' => 1, 'max' => 12 ),
			'csr_gallery_url'     => array( 'type' => 'url', 'label' => 'Odkaz na celou galerii', 'default' => '/galerie-1-1/' ),
		),
	);

	/* ---- Partneři ---- */
	$sections['partners'] = array(
		'title'  => 'Partneři',
		'desc'   => 'Prázdný název partnera skryje. Bez nahraného loga se zobrazí textový rámeček.',
		'fields' => array_merge(
			array(
				'csr_partners_show'  => array( 'type' => 'checkbox', 'label' => 'Zobrazit sekci', 'default' => true ),
				'csr_partners_title' => array( 'type' => 'text', 'label' => 'Popisek nad logy', 'default' => 'Partneři a organizace' ),
			),
			csr_repeat_fields(
				'csr_partner',
				6,
				array(
					'name' => array( 'type' => 'text',  'label' => '%d. partner — název' ),
					'logo' => array( 'type' => 'image', 'label' => '%d. partner — logo' ),
					'url'  => array( 'type' => 'url',   'label' => '%d. partner — odkaz' ),
				),
				array(
					1 => array( 'name' => 'ISU',                        'url' => 'https://www.isu.org/' ),
					2 => array( 'name' => 'Czech Olympic Team',         'url' => 'https://www.olympijskytym.cz/' ),
					3 => array( 'name' => 'Olymp Centrum sportu',       'url' => 'https://www.olympcsmv.cz/' ),
					4 => array( 'name' => 'Národní sportovní agentura', 'url' => 'https://agenturasport.cz/' ),
				)
			)
		),
	);

	/* ---- Stránka kalendáře ---- */
	$sections['calendar'] = array(
		'title'  => 'Stránka kalendáře',
		'desc'   => 'Týká se stránky se šablonou „ČSR — Kalendář akcí".',
		'fields' => array(
			'csr_cal_lead' => array(
				'type'    => 'textarea',
				'label'   => 'Popisek pod nadpisem',
				'default' => 'Domácí i mezinárodní závody, soustředění a semináře. Termíny průběžně doplňujeme.',
			),
			'csr_cal_per_page' => array(
				'type'    => 'number',
				'label'   => 'Kolik závodů na stránku',
				'default' => 20,
				'min'     => 5,
				'max'     => 100,
			),
		),
	);

	/* ---- Stránka InfoFeed ---- */
	$sections['infofeed'] = array(
		'title'  => 'Stránka InfoFeed',
		'desc'   => 'Týká se stránky se šablonou „ČSR — InfoFeed".',
		'fields' => array(
			'csr_feed_lead' => array(
				'type'    => 'textarea',
				'label'   => 'Popisek pod nadpisem',
				'default' => 'Oznámení svazu, dokumenty ISU a výsledkové listiny. Nejnovější nahoře.',
			),
			'csr_feed_per_page' => array(
				'type'    => 'number',
				'label'   => 'Kolik oznámení na stránku',
				'default' => 24,
				'min'     => 6,
				'max'     => 100,
			),
		),
	);

	/* ---- Stránky novinek ---- */
	$sections['season'] = array(
		'title'  => 'Stránky novinek',
		'desc'   => 'Týká se stránek se šablonou „ČSR — Novinky sezóny".',
		'fields' => array(
			'csr_season_per_page' => array(
				'type'    => 'number',
				'label'   => 'Kolik článků na stránku',
				'default' => 12,
				'min'     => 3,
				'max'     => 60,
			),
		),
	);

	/* ---- Články ---- */
	$sections['article'] = array(
		'title'  => 'Články',
		'desc'   => 'Vzhled jednotlivého článku (příspěvku).',
		'fields' => array(
			'csr_article_enable' => array(
				'type'    => 'checkbox',
				'label'   => 'Použít nový vzhled článků',
				'desc'    => 'Vypnutím se články vrátí k původní šabloně.',
				'default' => 1,
			),
			'csr_article_unwrap' => array(
				'type'    => 'checkbox',
				'label'   => 'Opravit zalomení vloženého textu',
				'desc'    => 'Text nakopírovaný z Wordu nebo PDF spojí zpět do odstavců. Obsah v databázi zůstává beze změny.',
				'default' => 1,
			),
			'csr_article_share' => array(
				'type'    => 'checkbox',
				'label'   => 'Zobrazit tlačítka pro sdílení',
				'default' => 1,
			),
		),
	);

	/* ---- Kluby ---- */
	$sections['clubs'] = array(
		'title'  => 'Kluby',
		'desc'   => 'Stránka se šablonou „ČSR — Kluby".',
		'fields' => array(
			'csr_clubs_lead' => array(
				'type'    => 'textarea',
				'label'   => 'Text pod nadpisem',
				'default' => '',
			),
			'csr_clubs_ares' => array(
				'type'    => 'checkbox',
				'label'   => 'IČO odkazovat do rejstříku ARES',
				'default' => 1,
			),
		),
	);

	/* ---- Lidé ---- */
	$sections['people'] = array(
		'title'  => 'Struktura svazu',
		'desc'   => 'Stránka se šablonou „ČSR — Struktura svazu".',
		'fields' => array(
			'csr_people_lead' => array(
				'type'    => 'textarea',
				'label'   => 'Text pod nadpisem',
				'default' => '',
			),
			'csr_people_names_in_photo' => array(
				'type'    => 'checkbox',
				'label'   => 'Jména jsou vypsaná přímo ve fotkách',
				'desc'    => 'Zapněte, dokud jsou na webu grafiky se jménem v obrázku. Text se schová před oči, ale zůstane pro odečítače a vyhledávače.',
				'default' => 0,
			),
		),
	);

	/* ---- Dokumenty ---- */
	$sections['docs'] = array(
		'title'  => 'Dokumenty',
		'desc'   => 'Stránka se šablonou „ČSR — Dokumenty".',
		'fields' => array(
			'csr_gallery_lead' => array(
				'type'  => 'textarea',
				'label' => 'Úvodní text nad alby',
				'desc'  => 'Nepovinné. Prázdné pole se nezobrazí.',
			),
			'csr_gallery_page' => array(
				'type'  => 'number',
				'label' => 'ID stránky s galerií',
				'desc'  => 'Kvůli odkazu „Zpět na všechna alba" v detailu alba. ID najdete v adrese stránky při její úpravě (post=123).',
				'min'   => 0,
				'max'   => 999999,
			),
			'csr_docs_lead' => array(
				'type'    => 'textarea',
				'label'   => 'Text pod nadpisem',
				'default' => '',
			),
		),
	);

	/* ---- Patička ---- */
	/* ---- Kontakty ---- */
	$sections['kontakt'] = array(
		'title'  => 'Kontakty',
		'desc'   => 'Adresa a e-mail se berou z oddílu Patička — jsou stejné na obou místech, ať se nerozejdou. Lidi vypisuje databáze Lidé ve svazu; do kontaktů se dostane každý, kdo tam má e-mail nebo telefon.',
		'fields' => array_merge(
			array(
				'csr_contact_lead'    => array( 'type' => 'textarea', 'label' => 'Úvodní text', 'desc' => 'Nepovinné.' ),
				'csr_contact_phone'   => array( 'type' => 'text', 'label' => 'Telefon svazu', 'desc' => 'Zobrazí se jako odkaz, na mobilu jde rovnou vytočit.' ),
				'csr_contact_ico'     => array( 'type' => 'text', 'label' => 'IČO', 'default' => '45769451' ),
				'csr_contact_account' => array( 'type' => 'text', 'label' => 'Číslo účtu', 'default' => '1727006504/0600' ),
				'csr_contact_databox' => array( 'type' => 'text', 'label' => 'Datová schránka', 'desc' => 'Nepovinné.' ),
			),
			csr_repeat_fields(
				'csr_org',
				4,
				array(
					'label' => array( 'type' => 'text', 'label' => '%d. organizace — název' ),
					'url'   => array( 'type' => 'url',  'label' => '%d. organizace — odkaz' ),
				),
				array(
					1 => array( 'label' => 'International Skating Union (ISU)', 'url' => 'https://www.isu.org' ),
				)
			)
		),
	);

	/* ---- Hledání a závody ---- */
	$sections['hledani'] = array(
		'title'  => 'Hledání a detail závodu',
		'desc'   => 'Výsledky hledání ani detail závodu nejsou stránka, takže se k nim šablona nepřiřazuje ručně — zapíná se tady.',
		'fields' => array(
			'csr_search_enable' => array(
				'type'    => 'checkbox',
				'label'   => 'Výsledky hledání v novém vzhledu',
				'default' => true,
			),
			'csr_search_lead' => array(
				'type'  => 'textarea',
				'label' => 'Text nad výsledky hledání',
				'desc'  => 'Nepovinné.',
			),
			'csr_event_enable' => array(
				'type'    => 'checkbox',
				'label'   => 'Detail závodu v novém vzhledu',
				'desc'    => 'Týká se závodů z pluginu The Events Calendar.',
				'default' => true,
			),
		),
	);

	/* ---- Výsledky a rekordy ---- */
	$sections['vysledky'] = array(
		'title'  => 'Výsledky a rekordy',
		'desc'   => 'Tabulky se vkládají v administraci v sekci Výsledky. Tady se nastavuje jen text nad nimi.',
		'fields' => array(
			'csr_results_lead' => array(
				'type'  => 'textarea',
				'label' => 'Text nad tabulkami výsledků',
				'desc'  => 'Nepovinné. Ukáže se na všech stránkách se šablonou „ČSR — Výsledky".',
			),
			'csr_records_lead' => array(
				'type'  => 'textarea',
				'label' => 'Text nad rekordy',
				'desc'  => 'Nepovinné.',
			),
		),
	);

	$sections['footer'] = array(
		'title'  => 'Patička',
		'desc'   => 'Odkazové sloupce se nastavují ve Vzhled → Menu (pozice „ČSR — patička").',
		'fields' => array(
			'csr_footer_about'      => array( 'type' => 'textarea', 'label' => 'Text o svazu', 'default' => 'Oficiální web Českého svazu rychlobruslení — zastřešující organizace pro speed skating a short track v České republice.' ),
			'csr_footer_col2_title' => array( 'type' => 'text', 'label' => 'Nadpis 2. sloupce', 'default' => 'Svaz' ),
			'csr_footer_col3_title' => array( 'type' => 'text', 'label' => 'Nadpis 3. sloupce', 'default' => 'Sport' ),
			'csr_footer_address'    => array( 'type' => 'textarea', 'label' => 'Adresa', 'desc' => 'Použije se v patičce i na stránce Kontakty.', 'default' => "Zátopkova 100/2\n169 00 Praha 6" ),
			'csr_footer_email'      => array( 'type' => 'text', 'label' => 'E-mail' ),
			'csr_footer_fb'         => array( 'type' => 'url', 'label' => 'Facebook' ),
			'csr_footer_ig'         => array( 'type' => 'url', 'label' => 'Instagram' ),
			'csr_footer_yt'         => array( 'type' => 'url', 'label' => 'YouTube' ),
		),
	);

	/* ---- Barvy ---- */
	$sections['colors'] = array(
		'title'  => 'Barvy',
		'desc'   => 'Změna se propíše do celé stránky.',
		'fields' => array(
			'csr_color_accent' => array( 'type' => 'color', 'label' => 'Hlavní akcent (ledová modrá)', 'default' => '#109ade' ),
			'csr_color_second' => array( 'type' => 'color', 'label' => 'Druhý akcent (červená)',       'default' => '#e01f26' ),
			'csr_color_dark'   => array( 'type' => 'color', 'label' => 'Tmavé pozadí',                 'default' => '#060e1a' ),
		),
	);

	return $sections;
}

/* =========================================================================
 * 3. ČTENÍ HODNOT
 * ====================================================================== */

/**
 * Plochá mapa klíč => definice pole (kvůli výchozím hodnotám).
 */
function csr_field_map() {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$map = array();
	foreach ( csr_settings_registry() as $section ) {
		foreach ( $section['fields'] as $key => $field ) {
			$map[ $key ] = $field;
		}
	}
	return $map;
}

/**
 * Vrátí hodnotu nastavení z Customizeru, jinak výchozí hodnotu z registru.
 *
 * @param string $key      Klíč nastavení.
 * @param mixed  $fallback Nouzová hodnota, když klíč v registru není.
 */
function csr_opt( $key, $fallback = '' ) {
	$map     = csr_field_map();
	$default = isset( $map[ $key ]['default'] ) ? $map[ $key ]['default'] : $fallback;
	return get_theme_mod( $key, $default );
}

/**
 * URL obrázku uloženého jako ID přílohy.
 *
 * @param string $key  Klíč nastavení.
 * @param string $size Velikost obrázku.
 */
function csr_opt_image( $key, $size = 'full' ) {
	$value = csr_opt( $key, 0 );
	if ( ! $value ) {
		return '';
	}
	// Customizer ukládá ID přílohy; starší hodnoty mohly být přímo URL.
	if ( is_numeric( $value ) ) {
		$url = wp_get_attachment_image_url( (int) $value, $size );
		return $url ? $url : '';
	}
	return esc_url_raw( $value );
}

/**
 * Logo: vlastní nastavení → logo webu → dodaná značka.
 *
 * @return array [url, je_to_nase_znacka]
 */
function csr_logo_url() {
	$custom = csr_opt_image( 'csr_logo', 'full' );
	if ( $custom ) {
		return array( $custom, false );
	}

	$site_logo = get_theme_mod( 'custom_logo' );
	if ( $site_logo ) {
		$url = wp_get_attachment_image_url( (int) $site_logo, 'full' );
		if ( $url ) {
			return array( $url, false );
		}
	}

	return array( get_stylesheet_directory_uri() . '/assets/img/logo-mark.svg', true );
}

/* =========================================================================
 * 4. REGISTRACE DO CUSTOMIZERU
 * ====================================================================== */

/**
 * Ověří, že vybraná hodnota je z povoleného seznamu.
 */
function csr_sanitize_select( $value, $setting ) {
	$map     = csr_field_map();
	$key     = $setting->id;
	$choices = isset( $map[ $key ]['choices'] ) ? $map[ $key ]['choices'] : array();
	return array_key_exists( $value, $choices ) ? $value : $setting->default;
}

/**
 * Postaví panel „Úvodní stránka ČSR" podle registru.
 *
 * @param WP_Customize_Manager $wp_customize Správce Customizeru.
 */
function csr_customize_register( $wp_customize ) {
	$wp_customize->add_panel(
		'csr_home_panel',
		array(
			'title'       => 'Úvodní stránka ČSR',
			'description' => 'Veškerý obsah nové úvodní stránky. Změny se hned projeví v náhledu vpravo.',
			'priority'    => 20,
		)
	);

	$priority = 10;

	foreach ( csr_settings_registry() as $slug => $section ) {
		$section_id = 'csr_section_' . $slug;

		$wp_customize->add_section(
			$section_id,
			array(
				'title'       => $section['title'],
				'description' => isset( $section['desc'] ) ? $section['desc'] : '',
				'panel'       => 'csr_home_panel',
				'priority'    => $priority,
			)
		);
		$priority += 10;

		foreach ( $section['fields'] as $key => $field ) {
			$type    = $field['type'];
			$default = isset( $field['default'] ) ? $field['default'] : '';

			// Sanitizace podle typu pole.
			switch ( $type ) {
				case 'textarea':
					$sanitize = 'sanitize_textarea_field';
					break;
				case 'url':
					$sanitize = 'esc_url_raw';
					break;
				case 'number':
				case 'image':
				case 'category':
					$sanitize = 'absint';
					break;
				case 'checkbox':
					$sanitize = 'rest_sanitize_boolean';
					break;
				case 'color':
					$sanitize = 'sanitize_hex_color';
					break;
				case 'select':
					$sanitize = 'csr_sanitize_select';
					break;
				default:
					$sanitize = 'sanitize_text_field';
			}

			$wp_customize->add_setting(
				$key,
				array(
					'default'           => $default,
					'sanitize_callback' => $sanitize,
					'transport'         => 'refresh',
				)
			);

			$args = array(
				'label'       => $field['label'],
				'description' => isset( $field['desc'] ) ? $field['desc'] : '',
				'section'     => $section_id,
			);

			if ( 'image' === $type ) {
				$wp_customize->add_control(
					new WP_Customize_Media_Control(
						$wp_customize,
						$key,
						array_merge( $args, array( 'mime_type' => 'image' ) )
					)
				);
				continue;
			}

			if ( 'color' === $type ) {
				$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $key, $args ) );
				continue;
			}

			if ( 'category' === $type ) {
				$options = array( 0 => 'Všechny kategorie' );
				foreach ( get_categories( array( 'hide_empty' => false ) ) as $cat ) {
					$options[ $cat->term_id ] = $cat->name;
				}
				$args['type']    = 'select';
				$args['choices'] = $options;
				$wp_customize->add_control( $key, $args );
				continue;
			}

			if ( 'select' === $type ) {
				$args['type']    = 'select';
				$args['choices'] = $field['choices'];
				$wp_customize->add_control( $key, $args );
				continue;
			}

			if ( 'number' === $type ) {
				$args['type']        = 'number';
				$args['input_attrs'] = array(
					'min' => isset( $field['min'] ) ? $field['min'] : 0,
					'max' => isset( $field['max'] ) ? $field['max'] : 100000,
				);
				$wp_customize->add_control( $key, $args );
				continue;
			}

			$args['type'] = ( 'textarea' === $type ) ? 'textarea' : 'text';
			$wp_customize->add_control( $key, $args );
		}
	}
}
add_action( 'customize_register', 'csr_customize_register' );

/* =========================================================================
 * 5. BARVY Z NASTAVENÍ DO STRÁNKY
 * ====================================================================== */

/**
 * Přepíše barevné tokeny podle nastavení v Customizeru.
 */
function csr_inline_colors() {
	if ( ! csr_is_csr_template() ) {
		return;
	}

	$accent = csr_opt( 'csr_color_accent' );
	$second = csr_opt( 'csr_color_second' );
	$dark   = csr_opt( 'csr_color_dark' );
	$logo_h = (int) csr_opt( 'csr_logo_height' );

	$css  = ':root{';
	$css .= '--csr-ice-500:' . esc_attr( $accent ) . ';';
	$css .= '--csr-red:' . esc_attr( $second ) . ';';
	$css .= '--csr-navy-900:' . esc_attr( $dark ) . ';';
	$css .= '--csr-logo-h:' . max( 20, min( 90, $logo_h ) ) . 'px;';
	$css .= '}';

	wp_add_inline_style( 'csr-home', $css );
}
add_action( 'wp_enqueue_scripts', 'csr_inline_colors', 30 );
