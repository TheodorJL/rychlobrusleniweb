<?php
/**
 * Výsledky hledání a detail závodu.
 *
 * Ani jedno není stránka, takže se k nim šablona nedá přiřadit ručně —
 * nasazuje se filtrem, stejně jako u detailu článku a alba.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nasadí naši šablonu na výsledky hledání.
 *
 * @param string $template Vybraná šablona.
 * @return string
 */
function csr_search_template( $template ) {
	if ( ! is_search() || ! csr_opt( 'csr_search_enable', 1 ) ) {
		return $template;
	}
	$file = get_stylesheet_directory() . '/' . CSR_SEARCH_TEMPLATE;
	return file_exists( $file ) ? $file : $template;
}
add_filter( 'template_include', 'csr_search_template', 99 );

/**
 * Nasadí naši šablonu na detail závodu z The Events Calendar.
 *
 * @param string $template Vybraná šablona.
 * @return string
 */
function csr_event_template( $template ) {
	if ( ! is_singular( 'tribe_events' ) || ! csr_opt( 'csr_event_enable', 1 ) ) {
		return $template;
	}
	$file = get_stylesheet_directory() . '/' . CSR_EVENT_TEMPLATE;
	return file_exists( $file ) ? $file : $template;
}
add_filter( 'template_include', 'csr_event_template', 99 );

/* -------------------------------------------------------------------------
 * Údaje o závodu
 * ---------------------------------------------------------------------- */

/**
 * Podrobnosti závodu z The Events Calendar.
 *
 * Plugin má vlastní funkce; sáhneme na ně jen když existují, aby šablona
 * fungovala i po jeho vypnutí.
 *
 * @param int $id ID závodu.
 * @return array
 */
function csr_event_details( $id ) {
	$out = array(
		'start'    => 0,
		'end'      => 0,
		'cely_den' => false,
		'misto'    => '',
		'adresa'   => '',
		'web'      => '',
		'poradatel'=> '',
	);

	if ( function_exists( 'tribe_get_start_date' ) ) {
		$out['start'] = (int) get_post_meta( $id, '_EventStartDateUTC', true )
			? strtotime( (string) get_post_meta( $id, '_EventStartDate', true ) )
			: 0;
	}
	if ( ! $out['start'] ) {
		$out['start'] = strtotime( (string) get_post_meta( $id, '_EventStartDate', true ) );
	}
	$out['end']      = strtotime( (string) get_post_meta( $id, '_EventEndDate', true ) );
	$out['cely_den'] = 'yes' === get_post_meta( $id, '_EventAllDay', true );
	$out['web']      = (string) get_post_meta( $id, '_EventURL', true );

	if ( function_exists( 'tribe_get_venue' ) ) {
		$out['misto']  = (string) tribe_get_venue( $id );
		$out['adresa'] = function_exists( 'tribe_get_full_address' ) ? (string) tribe_get_full_address( $id ) : '';
	}
	if ( function_exists( 'tribe_get_organizer' ) ) {
		$out['poradatel'] = (string) tribe_get_organizer( $id );
	}
	return $out;
}

/**
 * Nejbližší další závody — pro odkazy pod detailem.
 *
 * @param int $exclude ID právě zobrazeného závodu.
 * @param int $count   Kolik jich vrátit.
 * @return WP_Post[]
 */
function csr_next_events( $exclude, $count = 3 ) {
	if ( ! post_type_exists( 'tribe_events' ) ) {
		return array();
	}
	return get_posts(
		array(
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $count,
			'post__not_in'   => array( (int) $exclude ),
			'meta_key'       => '_EventStartDate', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_EventStartDate',
					'value'   => current_time( 'Y-m-d H:i:s' ),
					'compare' => '>=',
					'type'    => 'DATETIME',
				),
			),
		)
	);
}

/**
 * Popisek typu výsledku hledání.
 *
 * @param WP_Post $post Nalezená položka.
 * @return string
 */
function csr_search_kind( $post ) {
	$map = array(
		'post'         => 'Článek',
		'page'         => 'Stránka',
		'tribe_events' => 'Závod',
		'csr_album'    => 'Fotoalbum',
		'csr_document' => 'Dokument',
		'csr_club'     => 'Klub',
		'csr_athlete'  => 'Reprezentant',
		'csr_person'   => 'Člověk ve svazu',
		'csr_result'   => 'Výsledky',
		'csr_infofeed' => 'Oznámení',
	);
	return isset( $map[ $post->post_type ] ) ? $map[ $post->post_type ] : '';
}
