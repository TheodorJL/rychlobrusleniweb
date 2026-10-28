<?php
/**
 * Akce — hromadné vložení termínů do kalendáře.
 *
 * Závody vede plugin The Events Calendar. Zadat jich ručně pětadvacet
 * znamená pětadvacetkrát projít formulář s datem, místem a kategorií.
 * Tenhle formulář zvládne celou sezónu jedním vložením a dá se pustit
 * i opakovaně — co už v kalendáři je, přeskočí.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Je plugin s kalendářem k dispozici?
 *
 * @return bool
 */
function csr_ma_kalendar() {
	return function_exists( 'tribe_create_event' ) && post_type_exists( 'tribe_events' );
}

/**
 * Přidá do administrace položku pro hromadné vložení.
 */
function csr_events_import_page() {
	if ( ! post_type_exists( 'tribe_events' ) ) {
		return;
	}
	add_submenu_page(
		'edit.php?post_type=tribe_events',
		'Hromadné přidání akcí',
		'Hromadné přidání',
		'edit_posts',
		'csr-events-import',
		'csr_events_import_render'
	);
}
add_action( 'admin_menu', 'csr_events_import_page', 20 );

/**
 * Rozebere jeden řádek.
 *
 * @param string $line Řádek oddělený svislítky.
 * @return array|null
 */
function csr_parse_event_line( $line ) {
	$line = trim( $line );
	if ( '' === $line || 0 === strpos( $line, '#' ) ) {
		return null;
	}
	$parts = array_map( 'trim', explode( '|', $line ) );
	if ( '' === $parts[0] ) {
		return null;
	}

	$od = csr_event_date( isset( $parts[1] ) ? $parts[1] : '' );
	if ( '' === $od ) {
		return null;
	}
	$do = csr_event_date( isset( $parts[2] ) ? $parts[2] : '' );

	return array(
		'name'  => $parts[0],
		'from'  => $od,
		// Jednodenní závod má konec stejný jako začátek.
		'to'    => '' === $do || $do < $od ? $od : $do,
		'place' => isset( $parts[3] ) ? $parts[3] : '',
		'cat'   => isset( $parts[4] ) ? $parts[4] : '',
	);
}

/**
 * Datum na tvar Y-m-d. Bere „13. 11. 2026" i „2026-11-13".
 *
 * @param string $text Zapsané datum.
 * @return string Prázdno, když se datum nepodařilo přečíst.
 */
function csr_event_date( $text ) {
	$text = trim( (string) $text );
	if ( '' === $text ) {
		return '';
	}
	if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $m ) ) {
		$rok   = (int) $m[1];
		$mesic = (int) $m[2];
		$den   = (int) $m[3];
	} elseif ( preg_match( '/^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})$/u', $text, $m ) ) {
		$den   = (int) $m[1];
		$mesic = (int) $m[2];
		$rok   = (int) $m[3];
	} else {
		return '';
	}
	if ( ! checkdate( $mesic, $den, $rok ) ) {
		return '';
	}
	return sprintf( '%04d-%02d-%02d', $rok, $mesic, $den );
}

/**
 * Najde už zavedenou akci.
 *
 * Klíčem je název i den začátku. Světový pohár se jmenuje stejně každou
 * sezónu, takže samotný název by druhý ročník nepustil dovnitř.
 *
 * @param string $nazev Název akce.
 * @param string $od    Začátek ve tvaru Y-m-d.
 * @return int ID, nebo nula.
 */
function csr_find_event( $nazev, $od ) {
	$nalezene = get_posts(
		array(
			'post_type'      => 'tribe_events',
			'title'          => $nazev,
			'post_status'    => 'any',
			'posts_per_page' => 20,
			'fields'         => 'ids',
		)
	);

	foreach ( $nalezene as $id ) {
		$start = (string) get_post_meta( $id, '_EventStartDate', true );
		if ( 0 === strpos( $start, $od ) ) {
			return (int) $id;
		}
	}
	return 0;
}

/**
 * ID místa konání. Když ještě není, založí ho.
 *
 * @param string $nazev Město i se zkratkou země, třeba „Heerenveen /NED".
 * @return int ID, nebo nula.
 */
function csr_event_venue_id( $nazev ) {
	$nazev = trim( (string) $nazev );
	if ( '' === $nazev || ! post_type_exists( 'tribe_venue' ) ) {
		return 0;
	}

	$nalezene = get_posts(
		array(
			'post_type'      => 'tribe_venue',
			'title'          => $nazev,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	if ( $nalezene ) {
		return (int) $nalezene[0];
	}

	if ( function_exists( 'tribe_create_venue' ) ) {
		$id = tribe_create_venue(
			array(
				'Venue'       => $nazev,
				'post_status' => 'publish',
			)
		);
		return is_wp_error( $id ) || ! $id ? 0 : (int) $id;
	}

	return 0;
}

/**
 * Kategorie akce. Zkratky se převedou na plné názvy s barevným štítkem.
 *
 * @param string $zapis Zapsaná kategorie.
 * @return string Slug, nebo prázdno.
 */
function csr_event_cat_slug( $zapis ) {
	$zapis = strtolower( trim( (string) $zapis ) );
	if ( '' === $zapis ) {
		return '';
	}
	if ( in_array( $zapis, array( 'ss', 'dlouha', 'dlouhá', 'speed-skating', 'speed skating' ), true ) ) {
		return 'speed-skating';
	}
	if ( in_array( $zapis, array( 'st', 'kratka', 'krátká', 'short-track', 'short track' ), true ) ) {
		return 'short-track';
	}
	return sanitize_title( $zapis );
}

/**
 * Názvy kategorií, které umíme založit samy.
 *
 * @return array Slug => název.
 */
function csr_event_cat_names() {
	return array(
		'speed-skating' => 'Speed Skating',
		'short-track'   => 'Short Track',
	);
}

/**
 * Přiřadí akci kategorii, případně ji nejdřív založí.
 *
 * @param int    $post_id ID akce.
 * @param string $slug    Slug kategorie.
 */
function csr_event_set_cat( $post_id, $slug ) {
	if ( '' === $slug || ! taxonomy_exists( 'tribe_events_cat' ) ) {
		return;
	}

	$term = get_term_by( 'slug', $slug, 'tribe_events_cat' );
	if ( ! $term ) {
		$nazvy = csr_event_cat_names();
		$nazev = isset( $nazvy[ $slug ] ) ? $nazvy[ $slug ] : ucfirst( str_replace( '-', ' ', $slug ) );
		$novy  = wp_insert_term( $nazev, 'tribe_events_cat', array( 'slug' => $slug ) );
		if ( is_wp_error( $novy ) ) {
			return;
		}
		$term_id = (int) $novy['term_id'];
	} else {
		$term_id = (int) $term->term_id;
	}

	wp_set_object_terms( $post_id, array( $term_id ), 'tribe_events_cat' );
}

/**
 * Založí jednu akci.
 *
 * @param array $akce Data z řádku.
 * @return int ID, nebo nula.
 */
function csr_create_event( $akce ) {
	$venue_id = csr_event_venue_id( $akce['place'] );

	/*
	 * Časy závodů ISU dopředu nezveřejňuje a psát „00:00" by na webu
	 * vypadalo jako začátek o půlnoci. Akce je proto celodenní.
	 */
	$data = array(
		'post_title'     => sanitize_text_field( $akce['name'] ),
		'post_status'    => 'publish',
		'EventStartDate' => $akce['from'],
		'EventEndDate'   => $akce['to'],
		'EventAllDay'    => true,
		'EventTimezone'  => wp_timezone_string(),
	);
	if ( $venue_id ) {
		$data['Venue'] = array( 'VenueID' => $venue_id );
	}

	$id = tribe_create_event( $data );
	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}
	$id = (int) $id;

	// Pojistka: starší verze pluginu berou místo jen z „EventVenueID".
	if ( $venue_id && (int) get_post_meta( $id, '_EventVenueID', true ) !== $venue_id ) {
		update_post_meta( $id, '_EventVenueID', $venue_id );
	}

	csr_event_zapsat_termin( $id, $akce['from'], $akce['to'] );

	csr_event_set_cat( $id, csr_event_cat_slug( $akce['cat'] ) );

	return $id;
}

/**
 * Dopíše termín akce, když ho plugin sám nezaložil.
 *
 * Kalendář vyhledává podle „_EventStartDate" a „_EventStartDateUTC".
 * Kdyby některá verze pluginu celodenní akci uložila bez nich, akce by
 * v kalendáři nebyla vidět a další vložení by ji nepoznalo jako
 * existující a založilo ji znovu.
 *
 * @param int    $id ID akce.
 * @param string $od Začátek ve tvaru Y-m-d.
 * @param string $do Konec ve tvaru Y-m-d.
 */
function csr_event_zapsat_termin( $id, $od, $do ) {
	if ( 0 === strpos( (string) get_post_meta( $id, '_EventStartDate', true ), $od ) ) {
		return;
	}

	$zacatek = $od . ' 00:00:00';
	$konec   = $do . ' 23:59:59';

	update_post_meta( $id, '_EventStartDate', $zacatek );
	update_post_meta( $id, '_EventEndDate', $konec );
	update_post_meta( $id, '_EventAllDay', 'yes' );
	update_post_meta( $id, '_EventTimezone', wp_timezone_string() );
	update_post_meta( $id, '_EventDuration', strtotime( $konec ) - strtotime( $zacatek ) );

	$posun = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
	update_post_meta( $id, '_EventStartDateUTC', gmdate( 'Y-m-d H:i:s', strtotime( $zacatek ) - $posun ) );
	update_post_meta( $id, '_EventEndDateUTC', gmdate( 'Y-m-d H:i:s', strtotime( $konec ) - $posun ) );
}

/**
 * Stránka hromadného vložení.
 */
function csr_events_import_render() {
	$zalozeno = 0;
	$preskoceno = array();
	$chybne     = array();

	if ( isset( $_POST['csr_events_import_nonce'] )
		&& wp_verify_nonce( sanitize_key( $_POST['csr_events_import_nonce'] ), 'csr_events_import' )
		&& current_user_can( 'edit_posts' )
		&& csr_ma_kalendar() ) {

		$raw   = isset( $_POST['csr_events_data'] ) ? wp_unslash( $_POST['csr_events_data'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput — po řádcích níž
		$lines = preg_split( '/\R/', $raw );

		foreach ( $lines as $line ) {
			$radek = trim( $line );
			$akce  = csr_parse_event_line( $radek );

			if ( ! $akce ) {
				// Řádek s textem, ze kterého se nepodařilo přečíst datum.
				if ( '' !== $radek && '#' !== $radek[0] ) {
					$chybne[] = $radek;
				}
				continue;
			}

			$id = csr_find_event( $akce['name'], $akce['from'] );
			if ( $id ) {
				$preskoceno[] = $akce['name'];
				continue;
			}

			if ( csr_create_event( $akce ) ) {
				$zalozeno++;
			} else {
				$chybne[] = $akce['name'];
			}
		}
	}
	?>
	<div class="wrap">
		<h1>Hromadné přidání akcí</h1>

		<?php if ( ! csr_ma_kalendar() ) : ?>
			<div class="notice notice-error"><p>Kalendář akcí není k dispozici — zkontrolujte, že je zapnutý plugin <strong>The Events Calendar</strong>.</p></div>
		<?php endif; ?>

		<?php if ( $zalozeno ) : ?>
			<div class="notice notice-success"><p>Založeno akcí: <strong><?php echo (int) $zalozeno; ?></strong>. Najdete je v seznamu akcí i v kalendáři na webu.</p></div>
		<?php endif; ?>
		<?php if ( $preskoceno ) : ?>
			<div class="notice notice-info"><p>Už v kalendáři byly, nic se neměnilo: <?php echo esc_html( implode( ', ', $preskoceno ) ); ?></p></div>
		<?php endif; ?>
		<?php if ( $chybne ) : ?>
			<div class="notice notice-warning"><p>Tyhle řádky se nepodařilo přečíst (nejspíš datum): <?php echo esc_html( implode( ' / ', $chybne ) ); ?></p></div>
		<?php endif; ?>

		<p>Jedna akce na řádek, údaje oddělené svislítkem <code>|</code>:</p>
		<p><code>název | od | do | místo | kategorie</code></p>
		<p><strong>Datum</strong> zapište jako <code>13. 11. 2026</code> nebo <code>2026-11-13</code>. U jednodenního závodu nechte konec prázdný.<br>
			<strong>Kategorie</strong>: <code>ss</code> pro dlouhou dráhu, <code>st</code> pro krátkou. Podle ní se u akce ukáže barevný štítek.<br>
			Akce se zakládají jako celodenní — ISU časy startů dopředu nezveřejňuje. Vložení jde pustit opakovaně, co už v kalendáři je, se přeskočí.</p>

		<form method="post">
			<?php wp_nonce_field( 'csr_events_import', 'csr_events_import_nonce' ); ?>
			<?php echo wp_kses_post( csr_import_seed_note( 'akce', 'z mezinárodního kalendáře ISU' ) ); ?>
			<textarea name="csr_events_data" rows="18" style="width:100%;font-family:monospace" placeholder="ISU Speed Skating World Cup #1|13. 11. 2026|15. 11. 2026|Peking /CHN|ss"><?php echo esc_textarea( csr_import_seed( 'akce' ) ); ?></textarea>
			<?php submit_button( 'Vložit akce' ); ?>
		</form>
	</div>
	<?php
}
