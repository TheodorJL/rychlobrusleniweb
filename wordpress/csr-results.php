<?php
/**
 * Výsledkové tabulky.
 *
 * Na starém webu byly výsledky obrázkem PDF v rámu z docs.google.com.
 * To mělo tři následky: na mobilu se nevešly (pevných 640 px), Google ani
 * čtečka pro nevidomé se k obsahu nedostaly (popis stránky byl doslova
 * „Muži Ženy") a každý návštěvník posílal svoji IP adresu Googlu, aby si
 * mohl přečíst výsledky českého svazu.
 *
 * Tady je tabulka opravdová tabulka. Správce ji vloží zkopírováním
 * z tabulkového procesoru — sloupce oddělené tabulátory se rozeberou samy.
 * PDF zůstává jako náhradní možnost, ale ukazuje se jako soubor ke stažení,
 * ne jako rám cizí služby.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Typ obsahu a sezóny
 * ---------------------------------------------------------------------- */

/**
 * Zaregistruje typ obsahu „Výsledky" a taxonomii sezón.
 */
function csr_register_results() {
	register_post_type(
		'csr_result',
		array(
			'labels'        => array(
				'name'          => 'Výsledky',
				'singular_name' => 'Výsledková tabulka',
				'add_new'       => 'Přidat tabulku',
				'add_new_item'  => 'Přidat výsledkovou tabulku',
				'edit_item'     => 'Upravit tabulku',
				'search_items'  => 'Hledat tabulku',
				'not_found'     => 'Zatím tu nejsou žádné výsledky.',
				'menu_name'     => 'Výsledky',
			),
			'public'        => false,
			'show_ui'       => true,
			'menu_icon'     => 'dashicons-editor-table',
			'menu_position' => 27,
			'supports'      => array( 'title', 'page-attributes' ),
			'has_archive'   => false,
			'rewrite'       => false,
		)
	);

	/*
	 * Taxonomii sezón NEregistrujeme znovu — má ji už modul reprezentace
	 * (CSR_TAX_SEASON). Druhá registrace stejného názvu ji přepíše a soupisky
	 * by o ni přišly. Místo toho k ní jen přihlásíme výsledky, takže obě
	 * sekce sdílejí jeden seznam sezón a „2025–2026" znamená všude totéž.
	 */
	register_taxonomy_for_object_type( CSR_TAX_SEASON, 'csr_result' );
}
// Priorita 11: taxonomie sezón vzniká v modulu reprezentace na prioritě 10.
add_action( 'init', 'csr_register_results', 11 );

/**
 * Disciplíny. Dvě a víc jich nebude, proto ne taxonomie.
 *
 * @return array
 */
function csr_result_sports() {
	return array(
		'ss' => 'Speed skating',
		'st' => 'Short track',
	);
}

/**
 * Sezóny, které web zná. Doplňují se samy, tohle je jen výchozí náplň.
 *
 * @return array
 */
function csr_result_seasons() {
	return array(
		'2025-2026' => '2025–2026',
		'2024-2025' => '2024–2025',
		'2023-2024' => '2023–2024',
		'2022-2023' => '2022–2023',
		'2021-2022' => '2021–2022',
		'2020-2021' => '2020–2021',
	);
}

/**
 * Naplní seznam sezón.
 */
function csr_seed_seasons() {
	foreach ( csr_result_seasons() as $slug => $name ) {
		if ( ! term_exists( $slug, CSR_TAX_SEASON ) ) {
			wp_insert_term( $name, CSR_TAX_SEASON, array( 'slug' => $slug ) );
		}
	}
}

/**
 * Tabulky pro danou sezónu a disciplínu, v pořadí zadaném správcem.
 *
 * @param string $season Slug sezóny, prázdné = všechny.
 * @param string $sport  Klíč disciplíny, prázdné = všechny.
 * @return WP_Post[]
 */
function csr_get_results( $season = '', $sport = '' ) {
	$args = array(
		'post_type'      => 'csr_result',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
	);

	if ( $season ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => CSR_TAX_SEASON,
				'field'    => 'slug',
				'terms'    => $season,
			),
		);
	}

	if ( $sport ) {
		$args['meta_query'] = array(
			array(
				'key'   => '_csr_result_sport',
				'value' => $sport,
			),
		);
	}

	return get_posts( $args );
}

/**
 * Tabulky označené pro stránku Českých rekordů.
 *
 * @return WP_Post[]
 */
function csr_get_record_tables() {
	return get_posts(
		array(
			'post_type'      => 'csr_result',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
			'meta_query'     => array(
				array(
					'key'   => '_csr_result_records',
					'value' => '1',
				),
			),
		)
	);
}

/**
 * Ostatní stránky s výsledky — rozcestník na konci stránky.
 *
 * Bez něj se člověk mezi dvanácti sezónami prokliká jen přes menu,
 * které na mobilu znamená čtyři poklepání.
 *
 * @param int $exclude ID aktuální stránky.
 * @return WP_Post[]
 */
function csr_results_other_pages( $exclude ) {
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 30,
			'post__not_in'   => array( (int) $exclude ),
			'orderby'        => 'title',
			'order'          => 'DESC',
			'meta_query'     => array(
				array(
					'key'     => '_wp_page_template',
					'value'   => array( CSR_RESULTS_TEMPLATE, CSR_RECORDS_TEMPLATE ),
					'compare' => 'IN',
				),
			),
		)
	);
	return $pages;
}

/**
 * Sezóna a disciplína přiřazené stránce.
 *
 * @param int $page_id ID stránky.
 * @return array Klíče season, sport.
 */
function csr_results_page_scope( $page_id ) {
	$season = (string) get_post_meta( $page_id, '_csr_results_season', true );
	$sport  = (string) get_post_meta( $page_id, '_csr_results_sport', true );

	/*
	 * Co není nastavené ručně, zkusíme odvodit z názvu stránky —
	 * „Speed skating 2025-2026" si tak sama najde sezónu i disciplínu.
	 * Hodnota „-" znamená, že správce vědomě zvolil „všechny".
	 */
	$odhad = ( '' === $season || '' === $sport ) ? csr_results_guess_scope( $page_id ) : array( '', '' );

	return array(
		'season' => '-' === $season ? '' : ( '' !== $season ? $season : $odhad[0] ),
		'sport'  => '-' === $sport ? '' : ( '' !== $sport ? $sport : $odhad[1] ),
	);
}

/**
 * Odhadne sezónu a disciplínu z názvu stránky.
 *
 * @param int $page_id ID stránky.
 * @return array Dvojice slug sezóny a klíč disciplíny, prázdné když nic nesedí.
 */
function csr_results_guess_scope( $page_id ) {
	$nazev  = csr_fold( get_the_title( $page_id ) );
	$season = '';
	$sport  = '';

	if ( preg_match( '/(\d{4})\s*[-–—\/]\s*(\d{4})/u', $nazev, $shoda ) ) {
		$hledany = $shoda[1] . '-' . $shoda[2];
		$term    = get_term_by( 'slug', $hledany, CSR_TAX_SEASON );
		if ( ! $term ) {
			$term = get_term_by( 'name', $hledany, CSR_TAX_SEASON );
		}
		if ( $term && ! is_wp_error( $term ) ) {
			$season = $term->slug;
		}
	}

	if ( false !== strpos( $nazev, 'short track' ) || preg_match( '/(^|[^a-z])st([^a-z]|$)/u', $nazev ) ) {
		$sport = 'st';
	} elseif ( false !== strpos( $nazev, 'speed skating' ) || preg_match( '/(^|[^a-z])ss([^a-z]|$)/u', $nazev ) ) {
		$sport = 'ss';
	}

	return array( $season, $sport );
}

/* -------------------------------------------------------------------------
 * Rozbor vložené tabulky
 * ---------------------------------------------------------------------- */

/**
 * Očistí vloženou tabulku.
 *
 * Nepoužíváme sanitize_textarea_field(): tabulátory jsou tu oddělovač
 * sloupců, ne bílé místo, a kdyby je někdy zahodila, celá tabulka by se
 * slila do jednoho sloupce. Proto si necháme tabulátory a nové řádky
 * a odstraníme jen značky — obsah se stejně escapuje až při vykreslení.
 *
 * @param string $raw Text z formuláře.
 * @return string
 */
function csr_sanitize_table( $raw ) {
	$raw = (string) $raw;
	$raw = wp_check_invalid_utf8( $raw );
	$raw = wp_strip_all_tags( $raw );
	$raw = str_replace( array( "\r\n", "\r" ), "\n", $raw );
	// Zbylé řídicí znaky pryč, tabulátor (\t) a nový řádek (\n) necháváme.
	$raw = preg_replace( '/[^\P{C}\t\n]/u', '', $raw );
	return trim( $raw );
}

/**
 * Uhodne oddělovač sloupců.
 *
 * Kopie z tabulkového procesoru chodí s tabulátory, export z Excelu
 * s středníky, ruční zápis s čárkami. Rozhoduje první řádek — v něm jsou
 * názvy sloupců, takže se v něm čárka z desetinného čísla neplete.
 *
 * @param string $first_line První řádek.
 * @return string
 */
function csr_table_delimiter( $first_line ) {
	foreach ( array( "\t", ';', '|' ) as $sep ) {
		if ( substr_count( $first_line, $sep ) >= 1 ) {
			return $sep;
		}
	}
	return ',';
}

/**
 * Rozebere vložený text na řádky a buňky.
 *
 * @param string $raw Text z formuláře.
 * @return array Klíče head (pole názvů) a rows (pole polí).
 */
function csr_parse_table( $raw ) {
	$raw = str_replace( array( "\r\n", "\r" ), "\n", (string) $raw );
	$raw = trim( $raw );

	if ( '' === $raw ) {
		return array( 'head' => array(), 'rows' => array() );
	}

	$lines = array_values( array_filter( explode( "\n", $raw ), function ( $line ) {
		return '' !== trim( $line );
	} ) );

	if ( ! $lines ) {
		return array( 'head' => array(), 'rows' => array() );
	}

	$sep  = csr_table_delimiter( $lines[0] );
	$rows = array();
	foreach ( $lines as $line ) {
		$cells = array_map( 'trim', explode( $sep, $line ) );
		$rows[] = $cells;
	}

	$head = array_shift( $rows );

	// Krátké řádky doplníme prázdnými buňkami, dlouhé nezkracujeme —
	// radši ať přebývá sloupec, než aby zmizel čas.
	$width = count( $head );
	foreach ( $rows as $row ) {
		$width = max( $width, count( $row ) );
	}
	$head = array_pad( $head, $width, '' );
	foreach ( $rows as $i => $row ) {
		$rows[ $i ] = array_pad( $row, $width, '' );
	}

	return array( 'head' => $head, 'rows' => $rows );
}

/**
 * Je hodnota číslo, čas nebo vzdálenost?
 *
 * České časy mají tvar „1.11,20" nebo „36,55", vzdálenosti „500 m".
 * Takové sloupce sázíme doprava a neproporcionálními číslicemi, aby
 * šly srovnávat okem pod sebou.
 *
 * @param string $value Buňka.
 * @return bool
 */
function csr_cell_is_numeric( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return false;
	}
	return (bool) preg_match( '/^[\d]+([.,:][\d]+)*\s*(m|km|b|bodů|s)?\.?$/iu', $value );
}

/**
 * Zjistí, které sloupce jsou číselné.
 *
 * Rozhoduje většina vyplněných buněk — jedno „DNF" mezi časy
 * nesmí zarovnání celého sloupce překlopit.
 *
 * @param array $rows Řádky.
 * @param int   $width Počet sloupců.
 * @return array Pole bool podle indexu sloupce.
 */
function csr_table_numeric_columns( $rows, $width ) {
	$out = array();
	for ( $c = 0; $c < $width; $c++ ) {
		$filled = 0;
		$num    = 0;
		foreach ( $rows as $row ) {
			$cell = isset( $row[ $c ] ) ? trim( $row[ $c ] ) : '';
			if ( '' === $cell ) {
				continue;
			}
			$filled++;
			if ( csr_cell_is_numeric( $cell ) ) {
				$num++;
			}
		}
		$out[ $c ] = $filled > 0 && ( $num / $filled ) >= 0.6;
	}
	return $out;
}

/**
 * Je první sloupec pořadím?
 *
 * Poznáme ho podle názvu nebo podle toho, že buňky jsou „1." „2." „3."
 * Pak dostane medailové barvy u prvních tří řádků.
 *
 * @param array $head Názvy sloupců.
 * @param array $rows Řádky.
 * @return bool
 */
function csr_table_has_rank( $head, $rows ) {
	$label = isset( $head[0] ) ? csr_fold( $head[0] ) : '';
	if ( in_array( $label, array( 'por', 'por.', 'poradi', 'misto', 'rank', '#', '' ), true ) ) {
		return true;
	}
	// Nebo první tři buňky vypadají jako 1, 2, 3.
	$seen = array();
	foreach ( array_slice( $rows, 0, 3 ) as $row ) {
		$seen[] = preg_replace( '/\D/', '', isset( $row[0] ) ? $row[0] : '' );
	}
	return array( '1', '2', '3' ) === $seen;
}

/**
 * Malá písmena bez diakritiky — na porovnávání názvů sloupců.
 *
 * @param string $text Text.
 * @return string
 */
function csr_fold( $text ) {
	$text = mb_strtolower( trim( (string) $text ), 'UTF-8' );
	return remove_accents( $text );
}

/* -------------------------------------------------------------------------
 * Formulář u tabulky
 * ---------------------------------------------------------------------- */

/**
 * Přidá boxy k výsledkové tabulce.
 */
function csr_result_metabox() {
	add_meta_box( 'csr-result-data', 'Tabulka', 'csr_result_metabox_render', 'csr_result', 'normal', 'high' );
	add_meta_box( 'csr-result-meta', 'Zařazení', 'csr_result_scope_render', 'csr_result', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'csr_result_metabox' );

/**
 * Načte výběr souboru z knihovny médií jen na stránce tabulky.
 *
 * @param string $hook Aktuální obrazovka.
 */
function csr_result_admin_assets( $hook ) {
	global $post_type;
	if ( 'csr_result' !== $post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	wp_enqueue_media();
}
add_action( 'admin_enqueue_scripts', 'csr_result_admin_assets' );

/**
 * Boční box: sezóna, disciplína, kategorie.
 *
 * @param WP_Post $post Tabulka.
 */
function csr_result_scope_render( $post ) {
	wp_nonce_field( 'csr_result_scope', 'csr_result_scope_nonce' );
	$sport = get_post_meta( $post->ID, '_csr_result_sport', true );
	$note  = get_post_meta( $post->ID, '_csr_result_note', true );
	?>
	<p>
		<label for="csr-result-sport"><strong>Disciplína</strong></label><br>
		<select name="csr_result_sport" id="csr-result-sport" style="width:100%">
			<option value="">— nezařazeno —</option>
			<?php foreach ( csr_result_sports() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $sport, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label>
			<input type="checkbox" name="csr_result_records" value="1" <?php checked( get_post_meta( $post->ID, '_csr_result_records', true ), '1' ); ?>>
			<strong>Zobrazit na stránce Českých rekordů</strong>
		</label><br>
		<span style="color:#666;font-size:12px">Sem patří třeba rekordy na short tracku, které cizí server nevede.</span>
	</p>
	<p>
		<label for="csr-result-note"><strong>Poznámka pod tabulkou</strong></label><br>
		<input type="text" id="csr-result-note" name="csr_result_note" style="width:100%"
		       value="<?php echo esc_attr( $note ); ?>" placeholder="Nominace platí do 30. 6.">
	</p>
	<p style="color:#666;font-size:12px;margin-bottom:0">
		Sezónu vyberte v boxu <em>Sezóny</em>. Pořadí tabulek na stránce
		určuje pole <em>Pořadí</em> v boxu <em>Vlastnosti stránky</em> —
		nižší číslo je výš.
	</p>
	<?php
}

/**
 * Hlavní box: vložená tabulka nebo náhradní soubor.
 *
 * @param WP_Post $post Tabulka.
 */
function csr_result_metabox_render( $post ) {
	wp_nonce_field( 'csr_result_save', 'csr_result_nonce' );

	$data    = get_post_meta( $post->ID, '_csr_result_data', true );
	$file_id = (int) get_post_meta( $post->ID, '_csr_result_file', true );
	$parsed  = csr_parse_table( $data );
	?>
	<style>
		.csr-rf label { display: block; font-weight: 600; margin-bottom: .2rem; }
		.csr-rf p.desc { margin: .3rem 0 0; color: #666; font-size: 12px; }
		.csr-rf__row { margin-bottom: 1.4rem; }
		.csr-rf textarea { width: 100%; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; line-height: 1.6; }
		.csr-rf__file { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
		.csr-rf__name { font-weight: 600; }
		.csr-rf__warn { padding: .6rem .8rem; background: #fcf9e8; border-left: 4px solid #dba617; }
		.csr-rf__ok { padding: .6rem .8rem; background: #edfaef; border-left: 4px solid #00a32a; }
		.csr-rf__prev { border-collapse: collapse; width: 100%; font-size: 12px; }
		.csr-rf__prev th, .csr-rf__prev td { border: 1px solid #dcdcde; padding: .3rem .5rem; text-align: left; }
		.csr-rf__prev th { background: #f6f7f7; }
	</style>

	<div class="csr-rf">
		<div class="csr-rf__row">
			<label for="csr-result-data">Tabulka</label>
			<textarea id="csr-result-data" name="csr_result_data" rows="12" placeholder="Poř.	Jméno	Klub	Čas
1.	Metoděj Jílek	Sportovní klub	1.43,91"><?php echo esc_textarea( $data ); ?></textarea>
			<p class="desc">
				Označte tabulku v Excelu nebo Google Tabulkách, zkopírujte
				(<kbd>Ctrl</kbd>+<kbd>C</kbd>) a vložte sem. Sloupce oddělené tabulátory,
				středníky nebo čárkami se rozeberou samy. <strong>První řádek jsou názvy sloupců.</strong>
			</p>
		</div>

		<?php if ( $parsed['rows'] ) : ?>
			<div class="csr-rf__row">
				<p class="csr-rf__ok">
					Rozpoznáno: <strong><?php echo count( $parsed['rows'] ); ?></strong>
					<?php echo esc_html( 1 === count( $parsed['rows'] ) ? 'řádek' : ( count( $parsed['rows'] ) < 5 ? 'řádky' : 'řádků' ) ); ?>,
					<strong><?php echo count( $parsed['head'] ); ?></strong> sloupců. Náhled prvních tří řádků:
				</p>
				<table class="csr-rf__prev">
					<tr><?php foreach ( $parsed['head'] as $csr_h ) : ?><th><?php echo esc_html( $csr_h ); ?></th><?php endforeach; ?></tr>
					<?php foreach ( array_slice( $parsed['rows'], 0, 3 ) as $csr_r ) : ?>
						<tr><?php foreach ( $csr_r as $csr_c ) : ?><td><?php echo esc_html( $csr_c ); ?></td><?php endforeach; ?></tr>
					<?php endforeach; ?>
				</table>
			</div>
		<?php endif; ?>

		<div class="csr-rf__row">
			<label>Náhradní soubor (PDF)</label>
			<div class="csr-rf__file">
				<button type="button" class="button" id="csr-result-pick">Vybrat soubor</button>
				<button type="button" class="button-link" id="csr-result-clear"<?php echo $file_id ? '' : ' style="display:none"'; ?>>Odebrat</button>
				<span class="csr-rf__name" id="csr-result-name"><?php echo $file_id ? esc_html( basename( (string) get_attached_file( $file_id ) ) ) : ''; ?></span>
			</div>
			<input type="hidden" name="csr_result_file" id="csr-result-file" value="<?php echo esc_attr( $file_id ); ?>">
			<p class="desc">Nabídne se ke stažení pod tabulkou. Když tabulku nevyplníte, ukáže se aspoň soubor.</p>
		</div>

		<?php if ( $file_id && ! $parsed['rows'] ) : ?>
			<p class="csr-rf__warn">
				Stránka ukáže jen odkaz na soubor. <strong>PDF nikdo nenajde přes vyhledávání</strong> —
				Google ani hledání na webu do něj nevidí, na mobilu se špatně čte a čtečka
				pro nevidomé ho nepřečte. Vložte tabulku do pole výše; soubor může zůstat
				jako příloha ke stažení.
			</p>
		<?php endif; ?>
	</div>

	<script>
	jQuery(function ($) {
		var frame;
		$('#csr-result-pick').on('click', function (e) {
			e.preventDefault();
			if (frame) { frame.open(); return; }
			frame = wp.media({ title: 'Vyberte soubor s výsledky', button: { text: 'Použít soubor' }, multiple: false });
			frame.on('select', function () {
				var file = frame.state().get('selection').first().toJSON();
				$('#csr-result-file').val(file.id);
				$('#csr-result-name').text(file.filename);
				$('#csr-result-clear').show();
			});
			frame.open();
		});
		$('#csr-result-clear').on('click', function (e) {
			e.preventDefault();
			$('#csr-result-file').val('');
			$('#csr-result-name').text('');
			$(this).hide();
		});
	});
	</script>
	<?php
}

/**
 * Uloží tabulku.
 *
 * @param int $post_id ID tabulky.
 */
function csr_result_save( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['csr_result_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['csr_result_nonce'] ), 'csr_result_save' ) ) {
		if ( isset( $_POST['csr_result_data'] ) ) {
			update_post_meta( $post_id, '_csr_result_data', csr_sanitize_table( wp_unslash( $_POST['csr_result_data'] ) ) );
		}
		if ( isset( $_POST['csr_result_file'] ) ) {
			update_post_meta( $post_id, '_csr_result_file', absint( $_POST['csr_result_file'] ) );
		}
	}

	if ( isset( $_POST['csr_result_scope_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['csr_result_scope_nonce'] ), 'csr_result_scope' ) ) {
		if ( isset( $_POST['csr_result_sport'] ) ) {
			$sport = sanitize_key( wp_unslash( $_POST['csr_result_sport'] ) );
			update_post_meta( $post_id, '_csr_result_sport', array_key_exists( $sport, csr_result_sports() ) ? $sport : '' );
		}
		update_post_meta( $post_id, '_csr_result_records', empty( $_POST['csr_result_records'] ) ? '' : '1' );
		if ( isset( $_POST['csr_result_note'] ) ) {
			update_post_meta( $post_id, '_csr_result_note', sanitize_text_field( wp_unslash( $_POST['csr_result_note'] ) ) );
		}
	}
}
add_action( 'save_post_csr_result', 'csr_result_save' );

/* -------------------------------------------------------------------------
 * Vykreslení na stránce
 * ---------------------------------------------------------------------- */

/**
 * Údaje o náhradním souboru.
 *
 * @param int $post_id ID tabulky.
 * @return array Klíče url, ext, size.
 */
function csr_result_file( $post_id ) {
	$file_id = (int) get_post_meta( $post_id, '_csr_result_file', true );
	if ( ! $file_id ) {
		return array( 'url' => '', 'ext' => '', 'size' => '' );
	}
	$path = get_attached_file( $file_id );
	return array(
		'url'  => (string) wp_get_attachment_url( $file_id ),
		'ext'  => $path ? strtoupper( pathinfo( $path, PATHINFO_EXTENSION ) ) : '',
		'size' => ( $path && file_exists( $path ) ) ? size_format( filesize( $path ) ) : '',
	);
}

/**
 * Vykreslí jednu výsledkovou tabulku.
 *
 * @param WP_Post $post Tabulka.
 */
function csr_render_result( $post ) {
	$parsed = csr_parse_table( get_post_meta( $post->ID, '_csr_result_data', true ) );
	$file   = csr_result_file( $post->ID );
	$note   = get_post_meta( $post->ID, '_csr_result_note', true );
	$sport  = get_post_meta( $post->ID, '_csr_result_sport', true );
	$sports = csr_result_sports();
	$title  = get_the_title( $post );
	$id     = 'vysledky-' . $post->ID;

	if ( ! $parsed['rows'] && ! $file['url'] ) {
		// Prázdná tabulka je nedodělek — návštěvníkovi ji neukazujeme,
		// ale správce o ní musí vědět.
		if ( current_user_can( 'edit_posts' ) ) {
			printf(
				'<div class="csr-restable csr-restable--empty"><p>Vidíte jen vy jako správce: tabulka <strong>%s</strong> nemá vyplněná data ani soubor.</p></div>',
				esc_html( $title )
			);
		}
		return;
	}

	$width   = count( $parsed['head'] );
	$numeric = csr_table_numeric_columns( $parsed['rows'], $width );
	$rank    = csr_table_has_rank( $parsed['head'], $parsed['rows'] );
	?>
	<section class="csr-restable csr-reveal" id="<?php echo esc_attr( $id ); ?>">
		<header class="csr-restable__head">
			<h2 class="csr-restable__title"><?php echo esc_html( $title ); ?></h2>
			<div class="csr-restable__tools">
				<?php if ( $sport && isset( $sports[ $sport ] ) ) : ?>
					<span class="csr-chip csr-chip--<?php echo 'st' === $sport ? 'st' : 'ss'; ?>">
						<?php echo esc_html( $sports[ $sport ] ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $file['url'] ) : ?>
					<a class="csr-restable__file" href="<?php echo esc_url( $file['url'] ); ?>" download>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
						Stáhnout<?php echo $file['ext'] ? ' ' . esc_html( $file['ext'] ) : ''; ?><?php echo $file['size'] ? ' <small>(' . esc_html( $file['size'] ) . ')</small>' : ''; ?>
					</a>
				<?php endif; ?>
			</div>
		</header>

		<?php if ( $parsed['rows'] ) : ?>
			<?php
			/*
			 * tabindex a role dělají z posuvného rámu cíl pro klávesnici —
			 * bez toho se k širší tabulce na mobilu nedostane nikdo, kdo
			 * nepoužívá myš ani dotyk.
			 */
			?>
			<div class="csr-restable__scroll" tabindex="0" role="region"
			     aria-label="<?php echo esc_attr( $title . ' — tabulka' ); ?>">
				<table class="csr-table<?php echo $rank ? ' csr-table--rank' : ''; ?>" data-csr-table>
					<caption class="screen-reader-text"><?php echo esc_html( $title ); ?></caption>
					<thead>
						<tr>
							<?php foreach ( $parsed['head'] as $c => $csr_h ) : ?>
								<th scope="col"
								    class="<?php echo ! empty( $numeric[ $c ] ) ? 'csr-table__num' : ''; ?>"
								    data-csr-sort="<?php echo ! empty( $numeric[ $c ] ) ? 'num' : 'text'; ?>">
									<?php echo esc_html( $csr_h ); ?>
								</th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $parsed['rows'] as $r => $csr_row ) : ?>
							<tr<?php echo ( $rank && $r < 3 ) ? ' class="csr-table__medal csr-table__medal--' . ( $r + 1 ) . '"' : ''; ?>>
								<?php foreach ( $csr_row as $c => $csr_cell ) : ?>
									<?php
									// První sloupec je záhlaví řádku — čtečka pak u každé
									// buňky řekne, o koho jde, ne jen holé číslo.
									$tag = ( 0 === $c ) ? 'th' : 'td';
									?>
									<<?php echo $tag; ?><?php echo ( 0 === $c ) ? ' scope="row"' : ''; ?>
									   class="<?php echo ! empty( $numeric[ $c ] ) ? 'csr-table__num' : ''; ?>"
									   data-csr-label="<?php echo esc_attr( isset( $parsed['head'][ $c ] ) ? $parsed['head'][ $c ] : '' ); ?>">
										<?php echo esc_html( $csr_cell ); ?>
									</<?php echo $tag; ?>>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<p class="csr-restable__only-file">
				Tahle tabulka je zatím jen v souboru ke stažení.
			</p>
		<?php endif; ?>

		<?php if ( $note ) : ?>
			<p class="csr-restable__note"><?php echo esc_html( $note ); ?></p>
		<?php endif; ?>
	</section>
	<?php
}

/* -------------------------------------------------------------------------
 * Přehled v administraci
 * ---------------------------------------------------------------------- */

/**
 * Sloupce v seznamu tabulek.
 *
 * @param array $columns Původní sloupce.
 * @return array
 */
function csr_result_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['csr_season'] = 'Sezóna';
			$new['csr_sport']  = 'Disciplína';
			$new['csr_rows']  = 'Obsah';
		}
	}
	return $new;
}
add_filter( 'manage_csr_result_posts_columns', 'csr_result_columns' );

/**
 * Obsah vlastních sloupců.
 *
 * @param string $column  Klíč sloupce.
 * @param int    $post_id ID tabulky.
 */
function csr_result_column( $column, $post_id ) {
	if ( 'csr_season' === $column ) {
		$names = wp_get_object_terms( $post_id, CSR_TAX_SEASON, array( 'fields' => 'names' ) );
		echo $names && ! is_wp_error( $names )
			? esc_html( implode( ', ', $names ) )
			: '<span style="color:#b32d2e">nezařazeno</span>';
		return;
	}

	if ( 'csr_sport' === $column ) {
		$sport  = get_post_meta( $post_id, '_csr_result_sport', true );
		$sports = csr_result_sports();
		echo isset( $sports[ $sport ] ) ? esc_html( $sports[ $sport ] ) : '<span style="color:#b32d2e">nezařazeno</span>';
		return;
	}

	if ( 'csr_rows' !== $column ) {
		return;
	}

	$parsed = csr_parse_table( get_post_meta( $post_id, '_csr_result_data', true ) );
	$file   = csr_result_file( $post_id );

	if ( $parsed['rows'] ) {
		printf(
			'<span style="color:#00694e">✓ tabulka</span><br><small>%d řádků · %d sloupců%s</small>',
			count( $parsed['rows'] ),
			count( $parsed['head'] ),
			$file['url'] ? ' · soubor ' . esc_html( $file['ext'] ) : ''
		);
		return;
	}

	if ( $file['url'] ) {
		printf(
			'<span style="color:#996800">⚠ jen soubor %s</span><br><small>nedohledatelné ve vyhledávání</small>',
			esc_html( $file['ext'] )
		);
		return;
	}

	echo '<span style="color:#b32d2e">prázdné</span>';
}
add_action( 'manage_csr_result_posts_custom_column', 'csr_result_column', 10, 2 );

/**
 * Upozorní, kolik tabulek existuje jen jako soubor.
 */
function csr_result_admin_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-csr_result' !== $screen->id ) {
		return;
	}

	$only_file = 0;
	foreach ( csr_get_results() as $result ) {
		$parsed = csr_parse_table( get_post_meta( $result->ID, '_csr_result_data', true ) );
		$file   = csr_result_file( $result->ID );
		if ( ! $parsed['rows'] && $file['url'] ) {
			$only_file++;
		}
	}

	if ( ! $only_file ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p><strong>%d %s jen jako soubor.</strong> Vyhledávače ani hledání na webu do PDF nevidí a na mobilu se špatně čte. Otevřete tabulku a vložte obsah do pole <em>Tabulka</em> — soubor může zůstat jako příloha.</p></div>',
		(int) $only_file,
		esc_html( 1 === $only_file ? 'tabulka je' : ( $only_file < 5 ? 'tabulky jsou' : 'tabulek je' ) )
	);
}
add_action( 'admin_notices', 'csr_result_admin_notice' );

/* -------------------------------------------------------------------------
 * Nastavení stránky
 * ---------------------------------------------------------------------- */

/**
 * Box u stránky: která sezóna a disciplína se má vypsat.
 */
function csr_results_page_metabox() {
	add_meta_box( 'csr_results_settings', 'Výsledky — co se má vypsat', 'csr_results_page_metabox_render', 'page', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'csr_results_page_metabox' );

/**
 * Vykreslí výběr sezóny a disciplíny.
 *
 * @param WP_Post $post Upravovaná stránka.
 */
function csr_results_page_metabox_render( $post ) {
	wp_nonce_field( 'csr_results_page_save', 'csr_results_page_nonce' );

	$scope = csr_results_page_scope( $post->ID );
	$odhad = csr_results_guess_scope( $post->ID );
	$terms = get_terms( array( 'taxonomy' => CSR_TAX_SEASON, 'hide_empty' => false ) );

	echo '<p><label for="csr_results_season"><strong>Sezóna</strong></label>';
	echo '<select id="csr_results_season" name="csr_results_season" style="width:100%">';
	printf(
		'<option value=""%s>%s</option>',
		selected( get_post_meta( $post->ID, '_csr_results_season', true ), '', false ),
		$odhad[0] ? esc_html( 'Podle názvu stránky — ' . $odhad[0] ) : 'Podle názvu stránky'
	);
	echo '<option value="-">Všechny sezóny</option>';
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $term->slug ),
				selected( $scope['season'], $term->slug, false ),
				esc_html( $term->name )
			);
		}
	}
	echo '</select></p>';

	echo '<p><label for="csr_results_sport"><strong>Disciplína</strong></label>';
	echo '<select id="csr_results_sport" name="csr_results_sport" style="width:100%">';
	$sporty = csr_result_sports();
	printf(
		'<option value=""%s>%s</option>',
		selected( get_post_meta( $post->ID, '_csr_results_sport', true ), '', false ),
		$odhad[1] ? esc_html( 'Podle názvu stránky — ' . $sporty[ $odhad[1] ] ) : 'Podle názvu stránky'
	);
	echo '<option value="-">Obě disciplíny</option>';
	foreach ( csr_result_sports() as $key => $label ) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $key ),
			selected( $scope['sport'], $key, false ),
			esc_html( $label )
		);
	}
	echo '</select></p>';
	if ( CSR_RESULTS_TEMPLATE !== get_post_meta( $post->ID, '_wp_page_template', true ) ) {
		echo '<p class="description">Uplatní se, až stránka dostane šablonu <strong>„ČSR — Výsledky"</strong>.</p>';
		return;
	}
	echo '<p class="description">Jedna šablona takhle obslouží všech dvanáct stránek s výsledky — každá vypíše svou sezónu a disciplínu.</p>';
}

/**
 * Uloží nastavení stránky.
 *
 * @param int $post_id ID stránky.
 */
function csr_results_page_save( $post_id ) {
	if ( ! isset( $_POST['csr_results_page_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['csr_results_page_nonce'] ), 'csr_results_page_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	// „-" znamená vědomou volbu „všechny"; sanitizace by z něj udělala prázdno.
	if ( isset( $_POST['csr_results_season'] ) ) {
		$season = wp_unslash( $_POST['csr_results_season'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		update_post_meta( $post_id, '_csr_results_season', '-' === $season ? '-' : sanitize_title( $season ) );
	}
	if ( isset( $_POST['csr_results_sport'] ) ) {
		$sport = wp_unslash( $_POST['csr_results_sport'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '-' === $sport ) {
			update_post_meta( $post_id, '_csr_results_sport', '-' );
		} else {
			$sport = sanitize_key( $sport );
			update_post_meta( $post_id, '_csr_results_sport', array_key_exists( $sport, csr_result_sports() ) ? $sport : '' );
		}
	}
}
add_action( 'save_post_page', 'csr_results_page_save' );

/* -------------------------------------------------------------------------
 * Hromadné přidání
 * ---------------------------------------------------------------------- */

/**
 * Podstránka pro hromadné založení tabulek.
 */
function csr_results_import_page() {
	add_submenu_page(
		'edit.php?post_type=csr_result',
		'Hromadné přidání',
		'Hromadné přidání',
		'edit_posts',
		'csr-results-import',
		'csr_results_import_render'
	);
}
add_action( 'admin_menu', 'csr_results_import_page' );

/**
 * Formulář hromadného přidání.
 */
/**
 * Smaže všechny výsledkové tabulky.
 *
 * Slouží k opravě, když se tabulky přiřadily špatně. Tenhle typ obsahu
 * zakládá jen hromadné vložení, takže se nemaže nic, co by se nedalo
 * znovu vložit. Maže se natvrdo, aby smazané záznamy nepřekážely
 * při novém vkládání.
 *
 * @return int Počet smazaných tabulek.
 */
function csr_results_smazat_vse() {
	$vsechny = get_posts(
		array(
			'post_type'      => 'csr_result',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	$smazano = 0;
	foreach ( $vsechny as $id ) {
		if ( wp_delete_post( (int) $id, true ) ) {
			$smazano++;
		}
	}
	return $smazano;
}

/**
 * Rozebere vložený text na tabulky.
 *
 * Zvládne dvě podoby:
 *   • jeden název tabulky na řádek (sezónu a disciplínu vyberete nad polem)
 *   • „=== sezóna | disciplína | název" a pod tím řádky oddělené tabulátory
 *
 * @param string $text     Vložený text.
 * @param string $sezona   Sezóna z formuláře.
 * @param string $sport    Disciplína z formuláře.
 * @return array Tabulky s klíči nazev, sezona, sport, data.
 */
function csr_results_parse_bulk( $text, $sezona = '', $sport = '' ) {
	$tabulky = array();
	$akt     = null;

	foreach ( preg_split( '/\R/', (string) $text ) as $radek ) {
		$orez = trim( $radek );

		if ( '' !== $orez && '#' === $orez[0] ) {
			continue;
		}

		if ( 0 === strpos( $orez, '===' ) ) {
			if ( $akt ) {
				$tabulky[] = $akt;
			}
			$casti = array_map( 'trim', explode( '|', substr( $orez, 3 ) ) );
			$akt   = array(
				'sezona' => isset( $casti[0] ) ? sanitize_title( $casti[0] ) : $sezona,
				'sport'  => isset( $casti[1] ) ? sanitize_key( $casti[1] ) : $sport,
				'nazev'  => isset( $casti[2] ) ? $casti[2] : '',
				'data'   => array(),
			);
			continue;
		}

		if ( $akt ) {
			// Uvnitř tabulky si prázdné řádky nedržíme, oddělují jen odstavce.
			if ( '' !== $orez ) {
				$akt['data'][] = rtrim( $radek );
			}
			continue;
		}

		// Starší podoba: jeden název na řádek, bez dat.
		if ( '' !== $orez ) {
			$tabulky[] = array( 'sezona' => $sezona, 'sport' => $sport, 'nazev' => $orez, 'data' => array() );
		}
	}
	if ( $akt ) {
		$tabulky[] = $akt;
	}

	return array_values( array_filter( $tabulky, function ( $t ) {
		return '' !== $t['nazev'];
	} ) );
}

/**
 * Založí nebo doplní tabulky.
 *
 * Tabulka stejného názvu ve stejné sezóně se nezakládá podruhé —
 * jen se jí doplní data, když je nemá.
 *
 * @param string $text   Vložený text.
 * @param string $sezona Sezóna z formuláře.
 * @param string $sport  Disciplína z formuláře.
 * @return int Počet založených a doplněných tabulek.
 */
function csr_results_import_run( $text, $sezona = '', $sport = '' ) {
	$hotovo = 0;
	$poradi = 0;

	foreach ( csr_results_parse_bulk( $text, $sezona, $sport ) as $t ) {
		$poradi += 10;

		/*
		 * Do klíče patří i disciplína. „Ženy 500 m" v jedné sezóně existuje
		 * na krátké i na dlouhé dráze a bez toho by druhá v pořadí sáhla
		 * po té první a nechala v ní cizí výsledky.
		 */
		$args = array(
			'post_type'      => 'csr_result',
			'title'          => $t['nazev'],
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		);
		if ( $t['sezona'] ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array( 'taxonomy' => CSR_TAX_SEASON, 'field' => 'slug', 'terms' => $t['sezona'] ),
			);
		}
		if ( $t['sport'] ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'key' => '_csr_result_sport', 'value' => $t['sport'] ),
			);
		}
		$existuje = get_posts( $args );

		if ( $existuje ) {
			$id = (int) $existuje[0];
		} else {
			$id = wp_insert_post(
				array(
					'post_type'   => 'csr_result',
					'post_status' => 'publish',
					'post_title'  => $t['nazev'],
					'menu_order'  => $poradi,
				)
			);
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
		}

		if ( $t['data'] && '' === (string) get_post_meta( $id, '_csr_result_data', true ) ) {
			update_post_meta( $id, '_csr_result_data', csr_sanitize_table( implode( "\n", $t['data'] ) ) );
		}
		if ( $t['sport'] && array_key_exists( $t['sport'], csr_result_sports() ) ) {
			update_post_meta( $id, '_csr_result_sport', $t['sport'] );
		}
		if ( $t['sezona'] ) {
			// Sezóna je hierarchická, ze samotného názvu by ji WordPress nezaložil.
			$term = csr_term_id_by_name( CSR_TAX_SEASON, $t['sezona'] );
			if ( $term ) {
				wp_set_object_terms( $id, array( $term ), CSR_TAX_SEASON );
			}
		}
		$hotovo++;
	}

	return $hotovo;
}

function csr_results_import_render() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Na tohle nemáte oprávnění.' );
	}

	$done    = 0;
	$smazano = 0;
	if ( isset( $_POST['csr_results_import_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['csr_results_import_nonce'] ), 'csr_results_import' ) ) {
		csr_seed_seasons();

		$season = isset( $_POST['csr_import_season'] ) ? sanitize_title( wp_unslash( $_POST['csr_import_season'] ) ) : '';
		$sport  = isset( $_POST['csr_import_sport'] ) ? sanitize_key( wp_unslash( $_POST['csr_import_sport'] ) ) : '';
		// Vlastní sanitizace — sanitize_textarea_field() by zahodila tabulátory,
		// kterými jsou oddělené sloupce tabulek.
		$titles = isset( $_POST['csr_import_titles'] ) ? csr_sanitize_table( wp_unslash( $_POST['csr_import_titles'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$smazano = 0;
		if ( ! empty( $_POST['csr_import_reset'] ) ) {
			$smazano = csr_results_smazat_vse();
		}

		$done = csr_results_import_run( $titles, $season, $sport );
	}

	$terms = get_terms( array( 'taxonomy' => CSR_TAX_SEASON, 'hide_empty' => false ) );
	?>
	<div class="wrap">
		<h1>Hromadné přidání výsledkových tabulek</h1>

		<?php if ( $smazano ) : ?>
			<div class="notice notice-warning"><p>Smazáno <strong><?php echo (int) $smazano; ?></strong> starých tabulek.</p></div>
		<?php endif; ?>
		<?php if ( $done ) : ?>
			<div class="notice notice-success"><p>Zpracováno <strong><?php echo (int) $done; ?></strong> tabulek.</p></div>
		<?php endif; ?>

		<p>Pole je předvyplněné žebříčky, které byly na starých stránkách vložené jako PDF
			přes Google Viewer — <strong>132 tabulek</strong> od sezóny 2020-2021 po 2026-2027.
			Sezóna i disciplína jsou u každé uvedené, výběr nahoře se použije jen u tabulek,
			které je neuvádějí.</p>

		<form method="post">
			<?php wp_nonce_field( 'csr_results_import', 'csr_results_import_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="csr_import_season">Sezóna</label></th>
					<td>
						<select name="csr_import_season" id="csr_import_season">
							<option value="">— žádná —</option>
							<?php if ( ! is_wp_error( $terms ) ) : ?>
								<?php foreach ( $terms as $term ) : ?>
									<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
						<p class="description">Seznam se doplní po prvním uložení. Chybí sezóna? Přidejte ji v <em>Výsledky → Sezóny</em>.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="csr_import_sport">Disciplína</label></th>
					<td>
						<select name="csr_import_sport" id="csr_import_sport">
							<?php foreach ( csr_result_sports() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="csr_import_titles">Tabulky</label></th>
					<td>
						<?php echo wp_kses_post( csr_import_seed_note( 'vysledky-tabulky' ) ); ?>
						<textarea name="csr_import_titles" id="csr_import_titles" rows="16" class="large-text code" placeholder="Muži&#10;Ženy"><?php echo esc_textarea( csr_import_seed( 'vysledky-tabulky' ) ); ?></textarea>
						<p class="description">
							Buď jeden název tabulky na řádek — pak se použije sezóna a disciplína vybraná nahoře —
							nebo rovnou celé tabulky: řádek <code>=== sezóna | disciplína | název</code>
							zakládá novou a pod ním jsou data oddělená tabulátory.
							Vložení jde pustit znovu, tabulka stejného názvu se nezaloží podruhé.
						</p>
					</td>
				</tr>
			</table>
			<p>
				<label>
					<input type="checkbox" name="csr_import_reset" value="1">
					<strong>Nejdřív smazat všechny výsledkové tabulky</strong>
				</label><br>
				<span class="description">
					Smaže <em>všechny</em> tabulky a vloží je znovu podle pole níž. Použijte,
					když se tabulky přiřadily ke špatné disciplíně. Jiný obsah se nemaže.
				</span>
			</p>
			<?php submit_button( 'Vložit tabulky' ); ?>
		</form>
	</div>
	<?php
}
