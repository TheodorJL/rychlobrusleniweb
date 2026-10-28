<?php
/**
 * Kontakty — údaje svazu na jednom místě.
 *
 * Na původní stránce byl e-mail zapsaný jako href="http://info@speedskating.cz",
 * což prohlížeč bere jako adresu webu. Kliknutí vedlo na neexistující server.
 * Telefony byly jen text, takže se na mobilu nedaly vytočit.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Odkaz na e-mail.
 *
 * @param string $email E-mailová adresa.
 * @return string Prázdno, pokud adresa nedává smysl.
 */
function csr_mail_href( $email ) {
	$email = sanitize_email( (string) $email );
	return $email ? 'mailto:' . $email : '';
}

/**
 * Telefon po trojicích, ať se dá přečíst i zkontrolovat.
 *
 * @param string $phone Telefon v libovolném zápisu.
 * @return string
 */
function csr_format_phone( $phone ) {
	$cislice = preg_replace( '/\D+/', '', (string) $phone );
	if ( ! $cislice ) {
		return '';
	}

	// České číslo s předvolbou: +420 601 234 567
	if ( 12 === strlen( $cislice ) && '420' === substr( $cislice, 0, 3 ) ) {
		return '+420 ' . trim( chunk_split( substr( $cislice, 3 ), 3, ' ' ) );
	}
	// Devět číslic bez předvolby: 601 234 567
	if ( 9 === strlen( $cislice ) ) {
		return trim( chunk_split( $cislice, 3, ' ' ) );
	}
	// Cokoli jiného necháme, jak to správce napsal.
	return (string) $phone;
}

/**
 * Text, ve kterém zůstal markdown.
 *
 * Na kontaktech je doslova „[www.speedskating.cz](https://www.speedskating.cz)".
 * Někdo vložil do editoru markdown a WordPress ho nezpracoval.
 *
 * @param string $text Text k prověření.
 * @return bool
 */
function csr_has_markdown( $text ) {
	return (bool) preg_match( '/\[[^\]]+\]\((?:https?:)?\/\/[^)]+\)/i', (string) $text );
}

/**
 * Lidé pro výpis „Na koho se obrátit".
 *
 * Dřív se braly všichni, kdo mají vyplněný e-mail nebo telefon — a to je
 * celé předsednictvo, kontrolní komise i všichni předsedové klubů, tedy
 * dvaadvacet jmen včetně dvojích záznamů jednoho člověka. Kdo chce jen
 * napsat na svaz, si z takového seznamu nevybere. Vypisují se proto jen
 * lidé označení u sebe jako kontaktní osoba svazu.
 *
 * @param string $body Zkratka orgánu, nebo prázdno pro všechny.
 * @return WP_Post[]
 */
function csr_contact_people( $body = '' ) {
	$args = array(
		'post_type'      => 'csr_person',
		'posts_per_page' => -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
	);
	if ( $body ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array( 'taxonomy' => 'csr_body', 'field' => 'slug', 'terms' => $body ),
		);
	}

	$lide = get_posts( $args );

	// Do kontaktů patří jen ten, na koho se dá opravdu obrátit.
	$maji_spojeni = array_values(
		array_filter(
			$lide,
			static function ( $person ) {
				return get_post_meta( $person->ID, '_csr_person_email', true )
					|| get_post_meta( $person->ID, '_csr_person_phone', true );
			}
		)
	);

	$oznaceni = array_values(
		array_filter(
			$maji_spojeni,
			static function ( $person ) {
				return '1' === (string) get_post_meta( $person->ID, '_csr_person_contact', true );
			}
		)
	);

	// Dokud není označený nikdo, je lepší ukázat všechny než prázdno.
	return $oznaceni ? $oznaceni : $maji_spojeni;
}

/**
 * Odkaz na mapu z poštovní adresy.
 *
 * @param string $address Adresa i s novými řádky.
 * @return string
 */
function csr_map_href( $address ) {
	$address = trim( wp_strip_all_tags( (string) $address ) );
	if ( ! $address ) {
		return '';
	}
	return 'https://mapy.com/zakladni?q=' . rawurlencode( str_replace( "\n", ', ', $address ) );
}

/* -------------------------------------------------------------------------
 * Strukturovaná data
 * ---------------------------------------------------------------------- */

/**
 * Doplní adresu, telefon a e-mail do dat pro vyhledávače.
 *
 * Rank Math sám vypisuje jen název a logo. Bez adresy se svaz v mapách
 * a v postranním panelu Googlu neukáže.
 */
function csr_contact_schema() {
	if ( ! is_page_template( CSR_CONTACT_TEMPLATE ) ) {
		return;
	}

	$address = trim( (string) csr_opt( 'csr_footer_address', '' ) );
	$email   = sanitize_email( (string) csr_opt( 'csr_footer_email', '' ) );
	$phone   = csr_format_phone( csr_opt( 'csr_contact_phone', '' ) );
	$ico     = trim( (string) csr_opt( 'csr_contact_ico', '' ) );

	$data = array(
		'@context' => 'https://schema.org',
		'@type'    => 'SportsOrganization',
		'name'     => get_bloginfo( 'name' ),
		'url'      => home_url( '/' ),
	);

	if ( $address ) {
		$radky = array_values( array_filter( array_map( 'trim', explode( "\n", $address ) ) ) );
		$psc   = '';
		$mesto = '';
		if ( count( $radky ) > 1 && preg_match( '/^(\d{3}\s?\d{2})\s+(.+)$/u', $radky[1], $m ) ) {
			$psc   = $m[1];
			$mesto = $m[2];
		}
		$data['address'] = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $radky[0],
				'postalCode'      => $psc,
				'addressLocality' => $mesto,
				'addressCountry'  => 'CZ',
			)
		);
	}
	if ( $email ) {
		$data['email'] = $email;
	}
	if ( $phone ) {
		$data['telephone'] = $phone;
	}
	if ( $ico ) {
		$data['taxID'] = $ico;
	}

	printf(
		'<script type="application/ld+json">%s</script>',
		wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
	);
}
add_action( 'wp_footer', 'csr_contact_schema' );

/* -------------------------------------------------------------------------
 * Upozornění na markdown v obsahu
 * ---------------------------------------------------------------------- */

/**
 * Najde stránky, ve kterých zůstal nezpracovaný markdown.
 */
function csr_markdown_notice() {
	$obrazovka = get_current_screen();
	if ( ! $obrazovka || 'edit-page' !== $obrazovka->id || ! current_user_can( 'edit_pages' ) ) {
		return;
	}

	$nalezene = array();
	foreach ( get_posts( array( 'post_type' => 'page', 'posts_per_page' => -1 ) ) as $page ) {
		if ( csr_has_markdown( $page->post_content ) ) {
			$nalezene[] = $page;
		}
	}
	if ( ! $nalezene ) {
		return;
	}

	echo '<div class="notice notice-warning"><p><strong>Na těchto stránkách je vidět nezpracovaný markdown</strong> — návštěvník čte doslova <code>[text](adresa)</code> místo odkazu:</p><ul style="list-style:disc;margin-left:1.5em">';
	foreach ( $nalezene as $page ) {
		printf(
			'<li><a href="%s">%s</a></li>',
			esc_url( (string) get_edit_post_link( $page->ID ) ),
			esc_html( get_the_title( $page ) )
		);
	}
	echo '</ul><p>Vložte odkaz tlačítkem v editoru, ne psaním hranatých závorek.</p></div>';
}
add_action( 'admin_notices', 'csr_markdown_notice' );
