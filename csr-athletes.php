<?php
/**
 * Databáze reprezentantů.
 *
 * Závodník se zadá jednou (jméno, fotka, rok narození, klub) a zaškrtnutím
 * sezóny a týmu se objeví na příslušných soupiskách. Stránka soupisky pak
 * jen vybere kombinaci sezóna + tým.
 *
 * Přidává do administrace:
 *   Reprezentanti                 — seznam, přidávání, editace
 *   Reprezentanti → Sezóny        — 2025-2026, 2026-2027…
 *   Reprezentanti → Týmy          — SS Junioři, ST Senioři…
 *   Reprezentanti → Hromadné přidání — vložení celé soupisky najednou
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CSR_CPT_ATHLETE = 'csr_athlete';
const CSR_TAX_SEASON  = 'csr_season';
const CSR_TAX_SQUAD   = 'csr_squad';

/**
 * Role v týmu. Klíč se ukládá do meta pole.
 */
function csr_athlete_roles() {
	return array(
		'zavodnik'  => 'Závodník',
		'trener'    => 'Trenér',
		'realizace' => 'Realizační tým',
	);
}

/* =========================================================================
 * 1. TYP OBSAHU A TAXONOMIE
 * ====================================================================== */

/**
 * Zaregistruje typ obsahu „Reprezentanti" a jeho taxonomie.
 */
function csr_register_athletes() {
	register_post_type(
		CSR_CPT_ATHLETE,
		array(
			'labels' => array(
				'name'               => 'Reprezentanti',
				'singular_name'      => 'Reprezentant',
				'add_new'            => 'Přidat reprezentanta',
				'add_new_item'       => 'Přidat reprezentanta',
				'edit_item'          => 'Upravit reprezentanta',
				'new_item'           => 'Nový reprezentant',
				'view_item'          => 'Zobrazit reprezentanta',
				'search_items'       => 'Hledat reprezentanty',
				'not_found'          => 'Zatím tu nikdo není.',
				'not_found_in_trash' => 'V koši nic není.',
				'all_items'          => 'Všichni reprezentanti',
				'menu_name'          => 'Reprezentanti',
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'menu_position'       => 21,
			'menu_icon'           => 'dashicons-groups',
			// menu_order slouží k ručnímu řazení soupisky.
			'supports'            => array( 'title', 'thumbnail', 'page-attributes' ),
		)
	);

	register_taxonomy(
		CSR_TAX_SEASON,
		CSR_CPT_ATHLETE,
		array(
			'labels' => array(
				'name'          => 'Sezóny',
				'singular_name' => 'Sezóna',
				'add_new_item'  => 'Přidat sezónu',
				'menu_name'     => 'Sezóny',
			),
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => false,
		)
	);

	register_taxonomy(
		CSR_TAX_SQUAD,
		CSR_CPT_ATHLETE,
		array(
			'labels' => array(
				'name'          => 'Týmy',
				'singular_name' => 'Tým',
				'add_new_item'  => 'Přidat tým',
				'menu_name'     => 'Týmy',
			),
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => false,
		)
	);
}
add_action( 'init', 'csr_register_athletes' );

/**
 * Při první aktivaci založí výchozí týmy, ať se nemusí vypisovat ručně.
 */
function csr_seed_squads() {
	if ( get_option( 'csr_squads_seeded' ) ) {
		return;
	}
	if ( ! taxonomy_exists( CSR_TAX_SQUAD ) ) {
		return;
	}

	$defaults = array(
		'SS – Junioři',
		'SS – Senioři',
		'SS – Sledovaní',
		'ST – Junioři',
		'ST – Senioři',
		'ST – Sledovaní',
	);
	foreach ( $defaults as $name ) {
		if ( ! term_exists( $name, CSR_TAX_SQUAD ) ) {
			wp_insert_term( $name, CSR_TAX_SQUAD );
		}
	}

	update_option( 'csr_squads_seeded', 1 );
}
add_action( 'init', 'csr_seed_squads', 20 );

/* =========================================================================
 * 2. POLE U REPREZENTANTA
 * ====================================================================== */

/**
 * Přidá box s ročníkem, klubem a rolí.
 */
function csr_athlete_metabox() {
	add_meta_box(
		'csr_athlete_details',
		'Údaje o reprezentantovi',
		'csr_athlete_metabox_render',
		CSR_CPT_ATHLETE,
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'csr_athlete_metabox' );

/**
 * Vykreslí obsah boxu.
 *
 * @param WP_Post $post Upravovaný reprezentant.
 */
function csr_athlete_metabox_render( $post ) {
	wp_nonce_field( 'csr_athlete_save', 'csr_athlete_nonce' );

	$year = get_post_meta( $post->ID, '_csr_year', true );
	$club = get_post_meta( $post->ID, '_csr_club', true );
	$role = get_post_meta( $post->ID, '_csr_role', true );
	$role = $role ? $role : 'zavodnik';
	?>
	<style>
		.csr-fields { display: grid; gap: 16px; max-width: 560px; }
		.csr-fields label { display: block; font-weight: 600; margin-bottom: 4px; }
		.csr-fields input[type="text"],
		.csr-fields input[type="number"],
		.csr-fields select { width: 100%; }
		.csr-fields .description { margin-top: 4px; }
	</style>
	<div class="csr-fields">
		<div>
			<label for="csr_year">Rok narození</label>
			<input type="number" id="csr_year" name="csr_year" min="1900" max="2100"
			       value="<?php echo esc_attr( $year ); ?>" placeholder="2007">
			<p class="description">U trenérů nechte prázdné.</p>
		</div>
		<div>
			<label for="csr_club">Klub</label>
			<input type="text" id="csr_club" name="csr_club"
			       value="<?php echo esc_attr( $club ); ?>" placeholder="BK Náchod">
		</div>
		<div>
			<label for="csr_role">Role</label>
			<select id="csr_role" name="csr_role">
				<?php foreach ( csr_athlete_roles() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $role, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description">Trenéři a realizační tým se na soupisce vypisují zvlášť, pod závodníky.</p>
		</div>
	</div>
	<p class="description" style="margin-top:16px">
		Fotku nahrajte vpravo jako <strong>Náhledový obrázek</strong>. Ideálně na výšku,
		poměr 3 : 4 (např. 900 × 1200 px). Bez fotky se zobrazí zástupná silueta.
		Pořadí na soupisce se řídí polem <strong>Pořadí</strong> v boxu Atributy stránky.
	</p>
	<?php
}

/**
 * Uloží pole reprezentanta.
 *
 * @param int $post_id ID příspěvku.
 */
function csr_athlete_save( $post_id ) {
	if ( ! isset( $_POST['csr_athlete_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['csr_athlete_nonce'] ) ), 'csr_athlete_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$year = isset( $_POST['csr_year'] ) ? absint( $_POST['csr_year'] ) : 0;
	update_post_meta( $post_id, '_csr_year', $year ? $year : '' );

	$club = isset( $_POST['csr_club'] ) ? sanitize_text_field( wp_unslash( $_POST['csr_club'] ) ) : '';
	update_post_meta( $post_id, '_csr_club', $club );

	$role  = isset( $_POST['csr_role'] ) ? sanitize_key( wp_unslash( $_POST['csr_role'] ) ) : 'zavodnik';
	$roles = csr_athlete_roles();
	update_post_meta( $post_id, '_csr_role', isset( $roles[ $role ] ) ? $role : 'zavodnik' );
}
add_action( 'save_post_' . CSR_CPT_ATHLETE, 'csr_athlete_save' );

/* =========================================================================
 * 3. PŘEHLEDNÝ SEZNAM V ADMINISTRACI
 * ====================================================================== */

/**
 * Sloupce v seznamu reprezentantů.
 *
 * @param array $cols Původní sloupce.
 */
function csr_athlete_columns( $cols ) {
	$new = array(
		'cb'         => isset( $cols['cb'] ) ? $cols['cb'] : '',
		'csr_photo'  => 'Foto',
		'title'      => 'Jméno',
		'csr_year'   => 'Ročník',
		'csr_club'   => 'Klub',
		'csr_role'   => 'Role',
	);
	// Sloupce taxonomií doplní WordPress sám (show_admin_column).
	foreach ( $cols as $key => $label ) {
		if ( ! isset( $new[ $key ] ) && 'date' !== $key ) {
			$new[ $key ] = $label;
		}
	}
	$new['date'] = isset( $cols['date'] ) ? $cols['date'] : 'Datum';
	return $new;
}
add_filter( 'manage_' . CSR_CPT_ATHLETE . '_posts_columns', 'csr_athlete_columns' );

/**
 * Obsah vlastních sloupců.
 *
 * @param string $col     Klíč sloupce.
 * @param int    $post_id ID příspěvku.
 */
function csr_athlete_column_content( $col, $post_id ) {
	if ( 'csr_photo' === $col ) {
		if ( has_post_thumbnail( $post_id ) ) {
			echo get_the_post_thumbnail( $post_id, array( 44, 44 ), array( 'style' => 'width:44px;height:44px;object-fit:cover;border-radius:6px' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo '<span style="display:inline-block;width:44px;height:44px;border-radius:6px;background:#e7eef6"></span>';
		}
	}
	if ( 'csr_year' === $col ) {
		echo esc_html( get_post_meta( $post_id, '_csr_year', true ) );
	}
	if ( 'csr_club' === $col ) {
		echo esc_html( get_post_meta( $post_id, '_csr_club', true ) );
	}
	if ( 'csr_role' === $col ) {
		$roles = csr_athlete_roles();
		$role  = get_post_meta( $post_id, '_csr_role', true );
		echo esc_html( isset( $roles[ $role ] ) ? $roles[ $role ] : 'Závodník' );
	}
}
add_action( 'manage_' . CSR_CPT_ATHLETE . '_posts_custom_column', 'csr_athlete_column_content', 10, 2 );

/**
 * Seznam řadíme podle pořadí a jména, ne podle data vložení.
 *
 * @param WP_Query $query Dotaz administrace.
 */
function csr_athlete_admin_order( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( CSR_CPT_ATHLETE !== $query->get( 'post_type' ) ) {
		return;
	}
	if ( ! $query->get( 'orderby' ) ) {
		$query->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
	}
}
add_action( 'pre_get_posts', 'csr_athlete_admin_order' );

/* =========================================================================
 * 4. HROMADNÉ PŘIDÁNÍ
 * ====================================================================== */

/**
 * Přidá stránku pro vložení celé soupisky najednou.
 */
function csr_bulk_add_menu() {
	add_submenu_page(
		'edit.php?post_type=' . CSR_CPT_ATHLETE,
		'Hromadné přidání',
		'Hromadné přidání',
		'edit_posts',
		'csr-bulk-add',
		'csr_bulk_add_render'
	);
}
add_action( 'admin_menu', 'csr_bulk_add_menu' );

/**
 * Najde v knihovně médií přílohu podle adresy obrázku.
 *
 * Na stránkách bývá odkaz na zmenšeninu („…-450x600.jpg"), zatímco
 * v knihovně je uložený původní soubor. Proto zkoušíme i adresu
 * bez rozměru na konci a obě varianty protokolu.
 *
 * @param string $url Adresa obrázku.
 * @return int ID přílohy, nebo 0.
 */
function csr_attachment_from_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return 0;
	}

	$kandidati = array( $url );

	// Rozměr odřízneme jen na konci názvu, těsně před příponou —
	// uvnitř názvu může být „-300x300" součástí původního souboru.
	$bez_rozmeru = preg_replace( '#-\d+x\d+(\.[A-Za-z0-9]+)$#', '$1', $url );
	if ( $bez_rozmeru !== $url ) {
		$kandidati[] = $bez_rozmeru;
	}

	foreach ( $kandidati as $adresa ) {
		if ( 0 === strpos( $adresa, 'http://' ) ) {
			$kandidati[] = 'https://' . substr( $adresa, 7 );
		} elseif ( 0 === strpos( $adresa, 'https://' ) ) {
			$kandidati[] = 'http://' . substr( $adresa, 8 );
		}
	}

	foreach ( array_unique( $kandidati ) as $adresa ) {
		$id = attachment_url_to_postid( $adresa );
		if ( $id ) {
			return (int) $id;
		}
	}
	return 0;
}

/**
 * Najde termín podle názvu, a když neexistuje, založí ho.
 *
 * Sezóny i týmy jsou hierarchické taxonomie — u těch wp_set_object_terms()
 * název sám nezaloží a tiše by se nepřiřadilo nic.
 *
 * @param string $taxonomy Taxonomie.
 * @param string $name     Název termínu.
 * @return int ID termínu, nebo 0.
 */
function csr_term_id_by_name( $taxonomy, $name ) {
	$name = trim( (string) $name );
	if ( '' === $name || ! taxonomy_exists( $taxonomy ) ) {
		return 0;
	}

	$term = get_term_by( 'name', $name, $taxonomy );
	if ( ! $term ) {
		$term = get_term_by( 'slug', sanitize_title( $name ), $taxonomy );
	}
	if ( $term && ! is_wp_error( $term ) ) {
		return (int) $term->term_id;
	}

	$new = wp_insert_term( $name, $taxonomy );
	return ( ! is_wp_error( $new ) && isset( $new['term_id'] ) ) ? (int) $new['term_id'] : 0;
}

/**
 * Rozebere jeden řádek soupisky na jméno, ročník, klub a roli.
 * Formát: Jméno | rok | klub | role
 *
 * @param string $line Řádek k rozebrání.
 * @return array|null Pole s údaji, nebo null u prázdného řádku.
 */
function csr_parse_roster_line( $line ) {
	$line = trim( $line );
	// Prázdný řádek a poznámka za mřížkou se přeskočí — datové soubory
	// v balíčku jsou komentované a nikoho nemá napadnout je čistit ručně.
	if ( '' === $line || 0 === strpos( $line, '#' ) ) {
		return null;
	}

	// Podporujeme svislítko, tabulátor i středník jako oddělovač.
	$parts = preg_split( '/\s*[|;\t]\s*/', $line );
	$name  = isset( $parts[0] ) ? trim( $parts[0] ) : '';
	if ( '' === $name ) {
		return null;
	}

	$year = isset( $parts[1] ) ? absint( $parts[1] ) : 0;
	$club = isset( $parts[2] ) ? trim( $parts[2] ) : '';
	$role = isset( $parts[3] ) ? sanitize_key( $parts[3] ) : '';
	// Sezóna a tým na řádku mají přednost před výběrem nad polem. Díky tomu
	// jde vložit i několik soupisek najednou, ne jednu po druhé.
	$season_name = isset( $parts[4] ) ? trim( $parts[4] ) : '';
	$squad_name  = isset( $parts[5] ) ? trim( $parts[5] ) : '';
	$foto        = isset( $parts[6] ) ? trim( $parts[6] ) : '';

	// Roli poznáme i ze slova, ne jen z klíče.
	if ( ! array_key_exists( $role, csr_athlete_roles() ) ) {
		$haystack = mb_strtolower( $line );
		if ( false !== mb_strpos( $haystack, 'trenér' ) || false !== mb_strpos( $haystack, 'trener' ) ) {
			$role = 'trener';
		} elseif ( false !== mb_strpos( $haystack, 'realiza' ) ) {
			$role = 'realizace';
		} else {
			$role = 'zavodnik';
		}
	}

	// Když je ve třetím sloupci role místo klubu, klub vyprázdníme.
	if ( $club && array_key_exists( sanitize_key( $club ), csr_athlete_roles() ) ) {
		$club = '';
	}

	return array(
		'name'   => $name,
		'year'   => $year,
		'club'   => $club,
		'role'   => $role,
		'season' => $season_name,
		'squad'  => $squad_name,
		'foto'   => $foto,
	);
}

/**
 * Vykreslí a zpracuje formulář hromadného přidání.
 */
function csr_bulk_add_render() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Nemáte oprávnění přidávat reprezentanty.' );
	}

	$done = array();
	$skipped = array();

	$smazano = 0;
	if ( isset( $_POST['csr_bulk_nonce'] )
		&& wp_verify_nonce( sanitize_key( wp_unslash( $_POST['csr_bulk_nonce'] ) ), 'csr_bulk_add' ) ) {

		$raw    = isset( $_POST['csr_roster'] ) ? sanitize_textarea_field( wp_unslash( $_POST['csr_roster'] ) ) : '';
		$season = isset( $_POST['csr_season_id'] ) ? absint( $_POST['csr_season_id'] ) : 0;
		$squad  = isset( $_POST['csr_squad_id'] ) ? absint( $_POST['csr_squad_id'] ) : 0;
		$fotek     = 0;
		$bez_fotky = array();
		$order  = 0;

		if ( ! empty( $_POST['csr_roster_reset'] ) ) {
			$smazano = csr_smazat_reprezentanty();
		}

		foreach ( preg_split( '/\R/', $raw ) as $line ) {
			$row = csr_parse_roster_line( $line );
			if ( ! $row ) {
				continue;
			}

			// Sezóna a tým z řádku mají přednost před výběrem nad polem.
			$radek_season = ! empty( $row['season'] ) ? csr_term_id_by_name( CSR_TAX_SEASON, $row['season'] ) : 0;
			$radek_squad  = ! empty( $row['squad'] ) ? csr_term_id_by_name( CSR_TAX_SQUAD, $row['squad'] ) : 0;
			$pouzit_season = $radek_season ? $radek_season : $season;
			$pouzit_squad  = $radek_squad ? $radek_squad : $squad;

			/*
			 * Jeden záznam = jeden člověk v jedné sezóně a jednom týmu.
			 * Dřív byl každý člověk jen jednou a sezóny i týmy se k němu
			 * přisypávaly — kdo byl jeden rok junior a druhý senior, se pak
			 * objevil na obou soupiskách obou let. Dvojice sezóna + tým
			 * se z toho nedala přečíst.
			 */
			$existing = csr_find_roster_entry( $row['name'], $pouzit_season, $pouzit_squad );

			if ( $existing ) {
				$post_id   = $existing;
				$skipped[] = $row['name'];
			} else {
				$post_id = wp_insert_post(
					array(
						'post_type'   => CSR_CPT_ATHLETE,
						'post_title'  => $row['name'],
						'post_status' => 'publish',
						'menu_order'  => $order,
					)
				);
				if ( is_wp_error( $post_id ) || ! $post_id ) {
					continue;
				}
				$done[] = $row['name'];
			}

			foreach ( array( '_csr_year' => $row['year'], '_csr_club' => $row['club'], '_csr_role' => $row['role'] ) as $klic => $hodnota ) {
				if ( '' !== (string) $hodnota && '' === (string) get_post_meta( $post_id, $klic, true ) ) {
					update_post_meta( $post_id, $klic, $hodnota );
				}
			}

			// Každý záznam nese právě jednu sezónu a právě jeden tým.
			if ( $pouzit_season ) {
				wp_set_object_terms( $post_id, array( $pouzit_season ), CSR_TAX_SEASON );
			}
			if ( $pouzit_squad ) {
				wp_set_object_terms( $post_id, array( $pouzit_squad ), CSR_TAX_SQUAD );
			}

			/*
			 * Fotku hledáme v knihovně médií podle adresy. Nic se nestahuje
			 * ani nekopíruje — obrázky ze starého webu tam už jsou.
			 * Existujícímu reprezentantovi fotku nepřepisujeme.
			 */
			if ( ! empty( $row['foto'] ) && ! has_post_thumbnail( $post_id ) ) {
				$foto_id = csr_attachment_from_url( $row['foto'] );
				if ( $foto_id ) {
					set_post_thumbnail( $post_id, $foto_id );
					$fotek++;
				} else {
					$bez_fotky[] = $row['name'];
				}
			}

			$order++;
		}
	}

	$seasons = get_terms( array( 'taxonomy' => CSR_TAX_SEASON, 'hide_empty' => false ) );
	$squads  = get_terms( array( 'taxonomy' => CSR_TAX_SQUAD, 'hide_empty' => false ) );
	?>
	<div class="wrap">
		<h1>Hromadné přidání reprezentantů</h1>

		<?php if ( $done ) : ?>
			<div class="notice notice-success"><p>
				Přidáno <strong><?php echo count( $done ); ?></strong>:
				<?php echo esc_html( implode( ', ', $done ) ); ?>
			</p></div>
		<?php endif; ?>

		<?php if ( $smazano ) : ?>
			<div class="notice notice-warning"><p>
				Smazáno <strong><?php echo (int) $smazano; ?></strong> starých záznamů.
			</p></div>
		<?php endif; ?>

		<?php if ( $fotek ) : ?>
			<div class="notice notice-success"><p>
				Přiřazeno fotek z knihovny médií: <strong><?php echo (int) $fotek; ?></strong>.
			</p></div>
		<?php endif; ?>

		<?php if ( ! empty( $bez_fotky ) ) : ?>
			<div class="notice notice-warning"><p>
				U <strong><?php echo count( $bez_fotky ); ?></strong> se fotka v knihovně médií nenašla —
				doplňte ji u nich ručně jako <em>Náhledový obrázek</em>:
				<?php echo esc_html( implode( ', ', $bez_fotky ) ); ?>
			</p></div>
		<?php endif; ?>

		<?php if ( $skipped ) : ?>
			<div class="notice notice-info"><p>
				Tenhle záznam už v té sezóně a týmu byl, jen se doplnilo, co chybělo
				(<strong><?php echo count( $skipped ); ?></strong>):
				<?php echo esc_html( implode( ', ', $skipped ) ); ?>
			</p></div>
		<?php endif; ?>

		<p>Vložte soupisku, každý člověk na vlastní řádek ve formátu:</p>
		<p><code>Jméno Příjmení | rok narození | klub | role</code></p>
		<p class="description">
			Role je nepovinná — když ji vynecháte, počítá se závodník. Stačí napsat
			„trenér". Oddělovat můžete svislítkem, středníkem nebo tabulátorem
			(takže jde vložit i sloupce z tabulky).
		</p>

		<form method="post">
			<?php wp_nonce_field( 'csr_bulk_add', 'csr_bulk_nonce' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="csr_season_id">Sezóna</label></th>
					<td>
						<select name="csr_season_id" id="csr_season_id">
							<option value="0">— nezařazovat —</option>
							<?php foreach ( $seasons as $term ) : ?>
								<option value="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if ( empty( $seasons ) ) : ?>
							<p class="description">Zatím nemáte žádnou sezónu — založte ji v <em>Reprezentanti → Sezóny</em>.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="csr_squad_id">Tým</label></th>
					<td>
						<select name="csr_squad_id" id="csr_squad_id">
							<option value="0">— nezařazovat —</option>
							<?php foreach ( $squads as $term ) : ?>
								<option value="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="csr_roster">Soupiska</label></th>
					<td>
						<?php echo wp_kses_post( csr_import_seed_note( 'reprezentanti' ) ); ?>
						<textarea name="csr_roster" id="csr_roster" rows="14" class="large-text code"
							placeholder="Sára Hlušková | 2007 | BK Náchod&#10;Jiří Macháček | | | trenér&#10;&#10;Sezónu a tým lze uvést i na řádku a vložit tak víc soupisek najednou:&#10;Sára Hlušková | 2007 | BK Náchod | | 2026-2027 | SS – Junioři"><?php echo esc_textarea( csr_import_seed( 'reprezentanti' ) ); ?></textarea>
					</td>
				</tr>
			</table>

			<p>
				<label>
					<input type="checkbox" name="csr_roster_reset" value="1">
					<strong>Nejdřív smazat všechny reprezentanty</strong>
				</label><br>
				<span class="description">
					Smaže <em>všechny</em> záznamy a vloží je znovu podle pole výš. Použijte,
					když jsou uložení po starém — jeden člověk se všemi sezónami naráz.
					Jiný obsah se nemaže.
				</span>
			</p>
			<?php submit_button( 'Přidat reprezentanty' ); ?>
		</form>

		<p class="description">
			Fotky se doplňují až potom — u každého člověka v <em>Reprezentanti</em>
			jako náhledový obrázek.
		</p>
	</div>
	<?php
}

/* =========================================================================
 * 5. NAČTENÍ SOUPISKY PRO ŠABLONU
 * ====================================================================== */

/**
 * Vrátí reprezentanty pro danou sezónu a tým, rozdělené podle role.
 *
 * @param int $season_id ID sezóny (0 = bez omezení).
 * @param int $squad_id  ID týmu (0 = bez omezení).
 * @return array ['zavodnici' => WP_Post[], 'stab' => WP_Post[]]
 */
function csr_get_roster( $season_id = 0, $squad_id = 0 ) {
	$tax_query = array();

	if ( $season_id ) {
		$tax_query[] = array(
			'taxonomy' => CSR_TAX_SEASON,
			'field'    => 'term_id',
			'terms'    => (int) $season_id,
		);
	}
	if ( $squad_id ) {
		$tax_query[] = array(
			'taxonomy' => CSR_TAX_SQUAD,
			'field'    => 'term_id',
			'terms'    => (int) $squad_id,
		);
	}
	if ( count( $tax_query ) > 1 ) {
		$tax_query['relation'] = 'AND';
	}

	$args = array(
		'post_type'      => CSR_CPT_ATHLETE,
		'post_status'    => 'publish',
		'posts_per_page' => 200,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
	);
	if ( $tax_query ) {
		$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}

	$people    = get_posts( $args );
	$zavodnici = array();
	$stab      = array();

	foreach ( $people as $person ) {
		$role = get_post_meta( $person->ID, '_csr_role', true );
		if ( 'trener' === $role || 'realizace' === $role ) {
			$stab[] = $person;
		} else {
			$zavodnici[] = $person;
		}
	}

	return array(
		'zavodnici' => $zavodnici,
		'stab'      => $stab,
	);
}

/* =========================================================================
 * 6. NASTAVENÍ STRÁNKY SOUPISKY
 * ====================================================================== */

/**
 * Box se výběrem sezóny a týmu. Ukáže se jen u stránek s šablonou soupisky.
 */
function csr_roster_page_metabox() {
	add_meta_box(
		'csr_roster_settings',
		'Soupiska — co se má vypsat',
		'csr_roster_page_metabox_render',
		'page',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'csr_roster_page_metabox' );

/**
 * Vykreslí výběr sezóny a týmu.
 *
 * @param WP_Post $post Upravovaná stránka.
 */
function csr_roster_page_metabox_render( $post ) {
	$template = get_post_meta( $post->ID, '_wp_page_template', true );
	wp_nonce_field( 'csr_roster_page_save', 'csr_roster_page_nonce' );

	$season  = (int) get_post_meta( $post->ID, '_csr_page_season', true );
	$squad   = (int) get_post_meta( $post->ID, '_csr_page_squad', true );
	$odhad   = $squad ? 0 : csr_roster_guess_squad( $post->ID );
	$odhad_s = $season ? 0 : csr_roster_guess_season( $post->ID );
	$intro   = get_post_meta( $post->ID, '_csr_page_intro', true );
	$seasons = get_terms( array( 'taxonomy' => CSR_TAX_SEASON, 'hide_empty' => false ) );
	$squads  = get_terms( array( 'taxonomy' => CSR_TAX_SQUAD, 'hide_empty' => false ) );
	?>
	<p>
		<label for="csr_page_season"><strong>Sezóna</strong></label><br>
		<select name="csr_page_season" id="csr_page_season" style="width:100%">
			<option value="0">
				<?php
				$termin_s = $odhad_s ? get_term( $odhad_s, CSR_TAX_SEASON ) : null;
				echo $termin_s && ! is_wp_error( $termin_s )
					? esc_html( 'Podle menu — ' . $termin_s->name )
					: '— všechny —';
				?>
			</option>
			<?php foreach ( $seasons as $term ) : ?>
				<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $season, $term->term_id ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="csr_page_squad"><strong>Tým</strong></label><br>
		<select name="csr_page_squad" id="csr_page_squad" style="width:100%">
			<option value="0">
				<?php
				$nazev_odhadu = $odhad ? get_term( $odhad, CSR_TAX_SQUAD ) : null;
				echo $nazev_odhadu && ! is_wp_error( $nazev_odhadu )
					? esc_html( 'Podle názvu stránky — ' . $nazev_odhadu->name )
					: '— všechny —';
				?>
			</option>
			<?php foreach ( $squads as $term ) : ?>
				<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $squad, $term->term_id ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="csr_page_intro"><strong>Popisek pod nadpisem</strong></label><br>
		<textarea name="csr_page_intro" id="csr_page_intro" rows="3" style="width:100%"><?php echo esc_textarea( $intro ); ?></textarea>
	</p>
	<?php if ( CSR_ROSTER_TEMPLATE !== $template ) : ?>
		<p class="description">Uplatní se, až stránka dostane šablonu <strong>„ČSR — Soupiska reprezentace"</strong>.</p>
	<?php else : ?>
		<p class="description">
			Kdo se vypíše, řídí kombinace sezóny a týmu. Tým se odvodí z názvu
			stránky a sezóna z toho, pod kterou položkou menu stránka visí
			(„Sezóna 2025-2026"). Vybrat je potřeba jen tam, kde to nesedí.
			Lidi zadáváte v <em>Reprezentanti</em>.
		</p>
	<?php endif; ?>
	<?php
}

/**
 * Odhadne tým podle názvu stránky.
 *
 * Názvy nejsou psané jednotně — „SS – Junioři", „SS – .Junioři.",
 * „Speed skating – Junioři". Porovnávat je slovo od slova je křehké,
 * tak z obojího uděláme klíč disciplína + kategorie.
 *
 * @param int $page_id ID stránky.
 * @return int ID termínu, nebo 0.
 */
function csr_roster_guess_squad( $page_id ) {
	$klic = csr_squad_key( get_the_title( $page_id ) );
	if ( '' === $klic ) {
		return 0;
	}

	$terms = get_terms( array( 'taxonomy' => CSR_TAX_SQUAD, 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) {
		return 0;
	}

	foreach ( $terms as $term ) {
		if ( csr_squad_key( $term->name ) === $klic ) {
			return (int) $term->term_id;
		}
	}
	return 0;
}

/**
 * Klíč týmu: disciplína a kategorie, nezávisle na zápisu.
 *
 * @param string $text Název stránky nebo týmu.
 * @return string Například „ss|juniori", nebo prázdný řetězec.
 */
function csr_squad_key( $text ) {
	$t = csr_fold( $text );

	$disciplina = '';
	if ( preg_match( '/(^|[^a-z])st([^a-z]|$)/', $t ) || false !== strpos( $t, 'short' ) || false !== strpos( $t, 'kratk' ) ) {
		$disciplina = 'st';
	} elseif ( preg_match( '/(^|[^a-z])ss([^a-z]|$)/', $t ) || false !== strpos( $t, 'speed' ) || false !== strpos( $t, 'dlouh' ) ) {
		$disciplina = 'ss';
	}

	$kategorie = '';
	if ( false !== strpos( $t, 'junior' ) ) {
		$kategorie = 'juniori';
	} elseif ( false !== strpos( $t, 'senior' ) ) {
		$kategorie = 'seniori';
	} elseif ( false !== strpos( $t, 'sledovan' ) ) {
		$kategorie = 'sledovani';
	}

	if ( '' === $disciplina || '' === $kategorie ) {
		return '';
	}
	return $disciplina . '|' . $kategorie;
}

/**
 * Uloží nastavení stránky soupisky.
 *
 * @param int $post_id ID stránky.
 */
function csr_roster_page_save( $post_id ) {
	if ( ! isset( $_POST['csr_roster_page_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['csr_roster_page_nonce'] ) ), 'csr_roster_page_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['csr_page_season'] ) ) {
		update_post_meta( $post_id, '_csr_page_season', absint( $_POST['csr_page_season'] ) );
	}
	if ( isset( $_POST['csr_page_squad'] ) ) {
		update_post_meta( $post_id, '_csr_page_squad', absint( $_POST['csr_page_squad'] ) );
	}
	if ( isset( $_POST['csr_page_intro'] ) ) {
		update_post_meta( $post_id, '_csr_page_intro', sanitize_textarea_field( wp_unslash( $_POST['csr_page_intro'] ) ) );
	}
}
add_action( 'save_post_page', 'csr_roster_page_save' );

/**
 * Vykreslí jednu kartu člověka na soupisce.
 *
 * @param WP_Post $person Reprezentant nebo člen realizačního týmu.
 * @param bool    $small  Menší varianta pro realizační tým.
 */
function csr_render_person_card( $person, $small = false ) {
	$year  = get_post_meta( $person->ID, '_csr_year', true );
	$club  = get_post_meta( $person->ID, '_csr_club', true );
	$role  = get_post_meta( $person->ID, '_csr_role', true );
	$roles = csr_athlete_roles();
	$photo = csr_thumb_html( $person->ID, 'medium_large', array( 'alt' => '' ) );
	?>
	<article class="csr-person<?php echo $small ? ' csr-person--small' : ''; ?> csr-reveal">
		<div class="csr-person__photo">
			<?php if ( $photo ) : ?>
				<?php echo $photo; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php else : ?>
				<div class="csr-person__ph" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"><circle cx="12" cy="8.5" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg>
				</div>
			<?php endif; ?>
		</div>
		<div class="csr-person__body">
			<h3 class="csr-person__name"><?php echo esc_html( get_the_title( $person ) ); ?></h3>
			<p class="csr-person__meta">
				<?php if ( $year ) : ?>
					<span class="csr-person__year"><?php echo esc_html( $year ); ?></span>
				<?php endif; ?>
				<?php if ( $club ) : ?>
					<span class="csr-person__club"><?php echo esc_html( $club ); ?></span>
				<?php elseif ( $small && isset( $roles[ $role ] ) ) : ?>
					<span class="csr-person__club"><?php echo esc_html( $roles[ $role ] ); ?></span>
				<?php endif; ?>
			</p>
		</div>
	</article>
	<?php
}

/**
 * Smaže všechny reprezentanty.
 *
 * Slouží k opravě, když jsou záznamy uložené po starém — jeden člověk
 * se všemi sezónami naráz. Tenhle typ obsahu zakládá jen hromadné
 * vložení, takže se nemaže nic, co by z něj nešlo znovu vytvořit.
 *
 * @return int Počet smazaných záznamů.
 */
function csr_smazat_reprezentanty() {
	$vsichni = get_posts(
		array(
			'post_type'      => CSR_CPT_ATHLETE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	$smazano = 0;
	foreach ( $vsichni as $id ) {
		if ( wp_delete_post( (int) $id, true ) ) {
			$smazano++;
		}
	}
	return $smazano;
}

/**
 * Najde záznam téhož člověka v téže sezóně a témže týmu.
 *
 * @param string $jmeno  Jméno.
 * @param int    $season ID sezóny.
 * @param int    $squad  ID týmu.
 * @return int ID záznamu, nebo 0.
 */
function csr_find_roster_entry( $jmeno, $season, $squad ) {
	$args = array(
		'post_type'      => CSR_CPT_ATHLETE,
		'title'          => $jmeno,
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	);

	$tax = array();
	if ( $season ) {
		$tax[] = array( 'taxonomy' => CSR_TAX_SEASON, 'field' => 'term_id', 'terms' => (int) $season );
	}
	if ( $squad ) {
		$tax[] = array( 'taxonomy' => CSR_TAX_SQUAD, 'field' => 'term_id', 'terms' => (int) $squad );
	}
	if ( count( $tax ) > 1 ) {
		$tax['relation'] = 'AND';
	}
	if ( $tax ) {
		$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}

	$nalezeni = get_posts( $args );
	return $nalezeni ? (int) $nalezeni[0] : 0;
}

/**
 * Odhadne sezónu podle toho, kde stránka visí v menu.
 *
 * Pro každou sezónu je vlastní stránka, ale jmenují se stejně —
 * „SS – Junioři" je jich šest. V názvu sezóna není, zato v menu ano:
 * stránky visí pod položkou „Sezóna 2025-2026". Odtud ji vezmeme.
 *
 * @param int $page_id ID stránky.
 * @return int ID termínu sezóny, nebo 0.
 */
function csr_roster_guess_season( $page_id ) {
    $menus = wp_get_nav_menus();
    if ( is_wp_error( $menus ) || ! $menus ) {
        return 0;
    }

    foreach ( $menus as $menu ) {
        $polozky = wp_get_nav_menu_items( $menu->term_id );
        if ( ! $polozky ) {
            continue;
        }

        $podle_id = array();
        foreach ( $polozky as $polozka ) {
            $podle_id[ (int) $polozka->ID ] = $polozka;
        }

        foreach ( $polozky as $polozka ) {
            if ( (int) $polozka->object_id !== (int) $page_id ) {
                continue;
            }

            $rodic = isset( $podle_id[ (int) $polozka->menu_item_parent ] ) ? $podle_id[ (int) $polozka->menu_item_parent ] : null;
            while ( $rodic ) {
                if ( preg_match( '#(20\d{2})\s*[-–—/]\s*(20\d{2})#u', $rodic->title, $shoda ) ) {
                    return csr_season_term_id( $shoda[1] . '-' . $shoda[2] );
                }
                $rodic = isset( $podle_id[ (int) $rodic->menu_item_parent ] ) ? $podle_id[ (int) $rodic->menu_item_parent ] : null;
            }
        }
    }

    return 0;
}

/**
 * Najde sezónu podle zápisu „2025-2026".
 *
 * @param string $zapis Sezóna.
 * @return int ID termínu, nebo 0.
 */
function csr_season_term_id( $zapis ) {
    $term = get_term_by( 'slug', $zapis, CSR_TAX_SEASON );
    if ( ! $term ) {
        $term = get_term_by( 'name', $zapis, CSR_TAX_SEASON );
    }
    return $term && ! is_wp_error( $term ) ? (int) $term->term_id : 0;
}
