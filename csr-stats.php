<?php
/**
 * Čísla na úvodní stránce.
 *
 * Původně to byly hodnoty napsané natvrdo v nastavení — a tedy vymyšlené.
 * Návštěvník ale nemá jak poznat, že „480 aktivních závodníků" je odhad.
 * Čísla se proto počítají z databáze webu: kolik klubů, reprezentantů,
 * dokumentů nebo fotek na webu opravdu je. Co spočítat nejde, se nechá
 * prázdné a nezobrazí.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zdroje čísel. Klíč => [popisek pro nastavení, funkce, výchozí popisek].
 *
 * @return array
 */
function csr_stat_sources() {
	return array(
		''          => array( 'Vlastní hodnota (napíšu ji sám)', null, '' ),
		'kluby'     => array( 'Počet klubů', 'csr_count_clubs', 'Registrovaných klubů' ),
		'zavodnici' => array( 'Počet reprezentantů v aktuální sezóně', 'csr_count_athletes', 'Reprezentantů' ),
		'kluby_kraje' => array( 'Počet krajů s klubem', 'csr_count_regions', 'Krajů s klubem' ),
		'dokumenty' => array( 'Počet dokumentů', 'csr_count_documents', 'Dokumentů ke stažení' ),
		'alba'      => array( 'Počet fotoalb', 'csr_count_albums', 'Fotoalb' ),
		'fotky'     => array( 'Počet fotek v galerii', 'csr_count_photos', 'Fotek v galerii' ),
		'clanky'    => array( 'Počet článků', 'csr_count_articles', 'Článků na webu' ),
		'rekordy'   => array( 'Počet českých rekordů', 'csr_count_records', 'Platných českých rekordů' ),
	);
}

/**
 * Zdroje pro rozbalovací nabídku v nastavení.
 *
 * @return array Klíč => popisek.
 */
function csr_stat_source_choices() {
	$out = array();
	foreach ( csr_stat_sources() as $key => $data ) {
		$out[ $key ] = $data[0];
	}
	return $out;
}

/** @return int Počet zveřejněných klubů. */
function csr_count_clubs() {
	$p = wp_count_posts( 'csr_club' );
	return $p ? (int) $p->publish : 0;
}

/** @return int Počet reprezentantů v nejnovější sezóně, každý člověk jednou. */
function csr_count_athletes() {
	if ( ! taxonomy_exists( CSR_TAX_SEASON ) ) {
		return 0;
	}

	/*
	 * Jeden záznam reprezentanta platí pro jednu sezónu a tým, takže
	 * prostý součet příspěvků by tutéž osobu počítal za každou sezónu
	 * (a při startu na obou drahách i za každou dráhu) znovu.
	 */
	$sezony = get_terms( array( 'taxonomy' => CSR_TAX_SEASON, 'hide_empty' => true, 'fields' => 'names' ) );
	if ( is_wp_error( $sezony ) || ! $sezony ) {
		return 0;
	}
	rsort( $sezony );

	$zaznamy = get_posts( array(
		'post_type'   => CSR_CPT_ATHLETE,
		'numberposts' => -1,
		'fields'      => 'ids',
		'tax_query'   => array( array(
			'taxonomy' => CSR_TAX_SEASON,
			'field'    => 'name',
			'terms'    => $sezony[0],
		) ),
	) );

	$jmena = array();
	foreach ( $zaznamy as $id ) {
		$jmena[ trim( (string) get_the_title( $id ) ) ] = true;
	}
	return count( $jmena );
}

/** @return int Počet krajů, ve kterých je aspoň jeden klub. */
function csr_count_regions() {
	$terms = get_terms( array( 'taxonomy' => 'csr_region', 'hide_empty' => true, 'fields' => 'ids' ) );
	return is_wp_error( $terms ) ? 0 : count( $terms );
}

/** @return int Počet dokumentů. */
function csr_count_documents() {
	$p = wp_count_posts( 'csr_document' );
	return $p ? (int) $p->publish : 0;
}

/** @return int Počet alb. */
function csr_count_albums() {
	$p = wp_count_posts( 'csr_album' );
	return $p ? (int) $p->publish : 0;
}

/**
 * Počet fotek ve všech albech.
 *
 * @return int
 */
function csr_count_photos() {
	$celkem = 0;
	foreach ( csr_get_albums() as $album ) {
		$celkem += count( csr_album_items( $album->ID ) );
	}
	return $celkem;
}

/**
 * Počet platných českých rekordů. Bere se ze stažených dat, ne z odhadu.
 *
 * @return int
 */
function csr_count_records() {
	if ( ! function_exists( 'csr_records_get' ) ) {
		return 0;
	}
	$data = csr_records_get();
	$celkem = 0;
	foreach ( (array) ( isset( $data['groups'] ) ? $data['groups'] : array() ) as $skupina ) {
		$celkem += count( (array) $skupina );
	}
	return $celkem;
}

/** @return int Počet zveřejněných článků. */
function csr_count_articles() {
	$p = wp_count_posts( 'post' );
	return $p ? (int) $p->publish : 0;
}

/**
 * Hodnota čísla podle nastaveného zdroje.
 *
 * @param int    $i      Pořadí čísla (1–4).
 * @param string $prefix Předpona klíče: stat (hero) nebo counter (úspěchy).
 * @return int|null Hodnota, nebo null když se nedá spočítat.
 */
function csr_stat_value( $i, $prefix = 'stat' ) {
	$zdroj   = (string) csr_opt( "csr_{$prefix}{$i}_source" );
	$sources = csr_stat_sources();

	if ( ! $zdroj || ! isset( $sources[ $zdroj ] ) ) {
		// Vlastní hodnota. Nula znamená „nevyplněno" a číslo se skryje.
		$rucni = (int) csr_opt( "csr_{$prefix}{$i}_value" );
		return $rucni > 0 ? $rucni : null;
	}

	$fn = $sources[ $zdroj ][1];
	if ( ! $fn || ! function_exists( $fn ) ) {
		return null;
	}
	$hodnota = (int) call_user_func( $fn );

	// Nulu nemá smysl ukazovat — buď se ještě nezadal obsah, nebo
	// se něco nepovedlo. Lepší číslo vynechat, než tvrdit „0 klubů".
	return $hodnota > 0 ? $hodnota : null;
}

/**
 * Popisek čísla. Bez vlastního se použije ten, který ke zdroji patří.
 *
 * @param int    $i      Pořadí čísla.
 * @param string $prefix Předpona klíče.
 * @return string
 */
function csr_stat_label( $i, $prefix = 'stat' ) {
	$vlastni = trim( (string) csr_opt( "csr_{$prefix}{$i}_label" ) );
	if ( $vlastni ) {
		return $vlastni;
	}
	$zdroj   = (string) csr_opt( "csr_{$prefix}{$i}_source" );
	$sources = csr_stat_sources();
	return isset( $sources[ $zdroj ] ) ? $sources[ $zdroj ][2] : '';
}
