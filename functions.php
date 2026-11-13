<?php
/**
 * Potomkovská šablona ČSR.
 *
 * Tenhle soubor jen napojí balíček. Veškerá logika je v csr-home-functions.php
 * a v souborech, které si načítá sám.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Načte styl potomkovské šablony za styl rodičovské.
 *
 * GeneratePress si svoje CSS řeší sám; tohle je jen pro drobné úpravy,
 * které si do style.css dopíšete.
 */
function csr_child_enqueue_style() {
	$path = get_stylesheet_directory() . '/style.css';
	wp_enqueue_style(
		'csr-child',
		get_stylesheet_uri(),
		array( 'generate-style' ),
		file_exists( $path ) ? filemtime( $path ) : null
	);
}
add_action( 'wp_enqueue_scripts', 'csr_child_enqueue_style', 30 );

/**
 * Při první aktivaci převezme nastavení z rodičovské šablony.
 *
 * WordPress vede nastavení vzhledu zvlášť pro každou šablonu. Bez tohohle
 * by po přepnutí na potomkovskou šablonu zmizelo přiřazení menu, logo
 * i Doplňkové CSS — a web by na první pohled vypadal rozbitě, i když by
 * všechno bylo v pořádku.
 *
 * Běží jen jednou. Co je v potomkovské šabloně už nastavené, se nepřepisuje.
 */
function csr_child_prevzit_nastaveni() {
	if ( get_option( 'csr_child_nastaveni_prevzato' ) ) {
		return;
	}

	$rodic = get_option( 'theme_mods_' . get_template() );
	if ( is_array( $rodic ) ) {
		$potomek = get_option( 'theme_mods_' . get_stylesheet() );
		$potomek = is_array( $potomek ) ? $potomek : array();
		update_option( 'theme_mods_' . get_stylesheet(), array_merge( $rodic, $potomek ) );
	}

	update_option( 'csr_child_nastaveni_prevzato', 1 );
}
add_action( 'after_switch_theme', 'csr_child_prevzit_nastaveni' );

// Celý balíček ČSR.
require_once get_stylesheet_directory() . '/csr-home-functions.php';
