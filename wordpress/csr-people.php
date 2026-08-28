<?php
/**
 * Lidé ve svazu.
 *
 * Struktura svazu byla na webu jen jako 22 obrázků s prázdným alt — jména
 * nikde v textu. Tady je z toho běžný obsah: jeden záznam na člověka,
 * jméno a funkce jako text, fotka zvlášť.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Typ obsahu a orgány
 * ---------------------------------------------------------------------- */

/**
 * Zaregistruje typ obsahu „Lidé" a taxonomii orgánů svazu.
 */
function csr_register_people() {
	register_post_type(
		'csr_person',
		array(
			'labels'        => array(
				'name'          => 'Lidé',
				'singular_name' => 'Člověk',
				'add_new'       => 'Přidat člověka',
				'add_new_item'  => 'Přidat člověka',
				'edit_item'     => 'Upravit',
				'search_items'  => 'Hledat',
				'not_found'     => 'Zatím tu nikdo není.',
				'menu_name'     => 'Lidé',
			),
			'public'        => false,
			'show_ui'       => true,
			'menu_icon'     => 'dashicons-businessperson',
			'menu_position' => 27,
			'supports'      => array( 'title', 'thumbnail', 'page-attributes' ),
			'has_archive'   => false,
			'rewrite'       => false,
		)
	);

	register_taxonomy(
		'csr_body',
		'csr_person',
		array(
			'labels'            => array(
				'name'          => 'Orgány',
				'singular_name' => 'Orgán',
				'add_new_item'  => 'Přidat orgán',
			),
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => false,
		)
	);
}
add_action( 'init', 'csr_register_people' );

/**
 * Orgány svazu v pořadí, v jakém se mají vypsat.
 *
 * @return array
 */
function csr_bodies() {
	return array(
		'predsednictvo'    => 'Předsednictvo',
		'kontrolni-komise' => 'Kontrolní komise',
		'predsedove'       => 'Předsedové oddílů a klubů',
	);
}

/**
 * Naplní seznam orgánů.
 */
function csr_seed_bodies() {
	foreach ( csr_bodies() as $slug => $name ) {
		if ( ! term_exists( $slug, 'csr_body' ) ) {
			wp_insert_term( $name, 'csr_body', array( 'slug' => $slug ) );
		}
	}
}

/**
 * Pole u člověka.
 *
 * @return array
 */
function csr_person_fields() {
	return array(
		'role'  => array( 'label' => 'Funkce', 'hint' => 'Například „předseda" nebo „člen"' ),
		'club'  => array( 'label' => 'Klub', 'hint' => 'Zkratka klubu, například „BK ŽĎÁR"' ),
		'email' => array( 'label' => 'E-mail', 'hint' => 'Nepovinné' ),
		'phone' => array( 'label' => 'Telefon', 'hint' => 'Nepovinné' ),
	);
}

/**
 * Dráhy pro rozdělení předsedů.
 *
 * @return array
 */
function csr_tracks() {
	return array(
		''       => 'Neuvedeno',
		'dlouha' => 'Dlouhá dráha',
		'kratka' => 'Krátká dráha',
	);
}

/* -------------------------------------------------------------------------
 * Formulář
 * ---------------------------------------------------------------------- */

/**
 * Přidá box s údaji.
 */
function csr_person_metabox() {
	add_meta_box( 'csr-person', 'Údaje', 'csr_person_metabox_render', 'csr_person', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'csr_person_metabox' );

/**
 * Vykreslí formulář.
 *
 * @param WP_Post $post Člověk.
 */
function csr_person_metabox_render( $post ) {
	wp_nonce_field( 'csr_person_save', 'csr_person_nonce' );
	$track = get_post_meta( $post->ID, '_csr_person_track', true );

	echo '<style>.csr-pf{display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:1rem}.csr-pf label{display:block;font-weight:600;margin-bottom:.2rem}.csr-pf input,.csr-pf select{width:100%}.csr-pf p{margin:.2rem 0 0;color:#666;font-size:12px}</style>';
	echo '<div class="csr-pf">';
	foreach ( csr_person_fields() as $key => $field ) {
		printf(
			'<div><label for="csr_person_%1$s">%2$s</label><input type="text" id="csr_person_%1$s" name="csr_person_%1$s" value="%3$s">%4$s</div>',
			esc_attr( $key ),
			esc_html( $field['label'] ),
			esc_attr( get_post_meta( $post->ID, '_csr_person_' . $key, true ) ),
			$field['hint'] ? '<p>' . esc_html( $field['hint'] ) . '</p>' : ''
		);
	}

	echo '<div><label for="csr_person_track">Dráha</label><select id="csr_person_track" name="csr_person_track">';
	foreach ( csr_tracks() as $value => $label ) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $value ),
			selected( $track, $value, false ),
			esc_html( $label )
		);
	}
	echo '</select><p>Rozděluje předsedy klubů na dvě skupiny.</p></div>';
	echo '</div>';
	echo '<p style="margin-top:1rem"><strong>Fotka</strong> se nastavuje vpravo jako náhledový obrázek. Nejlépe vypadá portrét na výšku, ideálně <em>bez jména vypsaného v obrázku</em> — jméno vypíše šablona jako text.</p>';
}

/**
 * Uloží údaje.
 *
 * @param int $post_id ID záznamu.
 */
function csr_person_save( $post_id ) {
	if ( ! isset( $_POST['csr_person_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['csr_person_nonce'] ), 'csr_person_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( array_keys( csr_person_fields() ) as $key ) {
		$name = 'csr_person_' . $key;
		if ( ! isset( $_POST[ $name ] ) ) {
			continue;
		}
		$raw   = wp_unslash( $_POST[ $name ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput — čistí se hned níž
		$value = 'email' === $key ? sanitize_email( trim( $raw ) ) : sanitize_text_field( $raw );
		update_post_meta( $post_id, '_csr_person_' . $key, $value );
	}

	if ( isset( $_POST['csr_person_track'] ) ) {
		$track = sanitize_key( wp_unslash( $_POST['csr_person_track'] ) );
		update_post_meta( $post_id, '_csr_person_track', array_key_exists( $track, csr_tracks() ) ? $track : '' );
	}
}
add_action( 'save_post_csr_person', 'csr_person_save' );

/* -------------------------------------------------------------------------
 * Přehled v administraci
 * ---------------------------------------------------------------------- */

/**
 * Sloupce v seznamu lidí.
 *
 * @param array $columns Původní sloupce.
 * @return array
 */
function csr_person_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['csr_photo'] = 'Fotka';
			$new['csr_role']  = 'Funkce';
		}
	}
	return $new;
}
add_filter( 'manage_csr_person_posts_columns', 'csr_person_columns' );

/**
 * Obsah sloupců.
 *
 * @param string $column  Klíč sloupce.
 * @param int    $post_id ID záznamu.
 */
function csr_person_column( $column, $post_id ) {
	if ( 'csr_photo' === $column ) {
		if ( has_post_thumbnail( $post_id ) ) {
			echo get_the_post_thumbnail( $post_id, array( 40, 56 ), array( 'style' => 'object-fit:cover;border-radius:4px' ) );
		} else {
			echo '<span style="color:#b32d2e">chybí</span>';
		}
	}
	if ( 'csr_role' === $column ) {
		$role = get_post_meta( $post_id, '_csr_person_role', true );
		$club = get_post_meta( $post_id, '_csr_person_club', true );
		echo $role ? esc_html( $role ) : '<span style="color:#b32d2e">chybí</span>';
		if ( $club ) {
			echo '<br><small>' . esc_html( $club ) . '</small>';
		}
	}
}
add_action( 'manage_csr_person_posts_custom_column', 'csr_person_column', 10, 2 );

/* -------------------------------------------------------------------------
 * Hromadný vklad
 * ---------------------------------------------------------------------- */

/**
 * Přidá stránku pro hromadné vložení lidí.
 */
function csr_people_import_page() {
	add_submenu_page(
		'edit.php?post_type=csr_person',
		'Hromadné vložení lidí',
		'Hromadné vložení',
		'edit_posts',
		'csr-people-import',
		'csr_people_import_render'
	);
}
add_action( 'admin_menu', 'csr_people_import_page' );

/**
 * Rozebere jeden řádek.
 *
 * @param string $line Řádek oddělený svislítky.
 * @return array|null
 */
function csr_parse_person_line( $line ) {
	$line = trim( $line );
	// Poznámka za mřížkou není člověk.
	if ( '' === $line || 0 === strpos( $line, '#' ) ) {
		return null;
	}
	$parts = array_map( 'trim', explode( '|', $line ) );
	if ( '' === $parts[0] ) {
		return null;
	}
	return array(
		'name'  => $parts[0],
		'body'  => isset( $parts[1] ) ? $parts[1] : '',
		'role'  => isset( $parts[2] ) ? $parts[2] : '',
		'track' => isset( $parts[3] ) ? $parts[3] : '',
		'club'  => isset( $parts[4] ) ? $parts[4] : '',
		'email' => isset( $parts[5] ) ? $parts[5] : '',
		'phone' => isset( $parts[6] ) ? $parts[6] : '',
	);
}

/**
 * Převede zápis dráhy na klíč. Bere „dlouhá", „dlouha", „LT" i „DD".
 *
 * @param string $text Zápis od uživatele.
 * @return string
 */
function csr_track_key( $text ) {
	$text = strtolower( trim( $text ) );
	if ( '' === $text ) {
		return '';
	}
	if ( 0 === strpos( $text, 'dlouh' ) || 'lt' === $text || 'dd' === $text || 'ss' === $text ) {
		return 'dlouha';
	}
	if ( 0 === strpos( $text, 'kr' ) || 'st' === $text ) {
		return 'kratka';
	}
	return '';
}

/**
 * Iniciály ze jména — místo fotky. Nikdo z lidí ve svazu fotku nemá
 * a dvaadvacet stejných siluet vedle sebe vypadá jako chyba.
 *
 * @param string $jmeno Celé jméno.
 * @return string Jedno až dvě písmena.
 */
function csr_person_initials( $jmeno ) {
	$slova = preg_split( '/\s+/u', trim( (string) $jmeno ), -1, PREG_SPLIT_NO_EMPTY );
	if ( ! $slova ) {
		return '';
	}

	$prijmeni = csr_person_surname( $jmeno );
	$prvni    = $slova[0];
	if ( $prijmeni === $prvni ) {
		return csr_first_letter( $prvni );
	}
	return csr_first_letter( $prvni ) . csr_first_letter( $prijmeni );
}

/**
 * První písmeno slova velkým, i s diakritikou.
 *
 * @param string $slovo Slovo.
 * @return string
 */
function csr_first_letter( $slovo ) {
	$slovo = (string) $slovo;
	if ( '' === $slovo ) {
		return '';
	}
	$znak = function_exists( 'mb_substr' ) ? mb_substr( $slovo, 0, 1, 'UTF-8' ) : substr( $slovo, 0, 1 );
	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $znak, 'UTF-8' ) : strtoupper( $znak );
}

/**
 * Najde člověka podle jména v rámci jednoho orgánu.
 *
 * @param string $jmeno Jméno.
 * @param string $body  Slug orgánu, prázdné = hledat všude.
 * @return int ID záznamu, nebo 0.
 */
function csr_find_person( $jmeno, $body ) {
	$args = array(
		'post_type'      => 'csr_person',
		'title'          => $jmeno,
		'posts_per_page' => 1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	);
	if ( $body ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'csr_body',
				'field'    => 'slug',
				'terms'    => $body,
			),
		);
	}
	$nalezeni = get_posts( $args );
	return $nalezeni ? (int) $nalezeni[0] : 0;
}

/**
 * Příjmení ze jména — poslední slovo, zkratky jako „ml." se přeskočí.
 *
 * @param string $jmeno Celé jméno.
 * @return string
 */
function csr_person_surname( $jmeno ) {
	$slova = preg_split( '/\s+/u', trim( (string) $jmeno ), -1, PREG_SPLIT_NO_EMPTY );
	while ( $slova && preg_match( '/^(ml|st|jr|sr)\.?$/iu', end( $slova ) ) ) {
		array_pop( $slova );
	}
	return $slova ? (string) end( $slova ) : '';
}

/**
 * Vykreslí a zpracuje hromadný vklad.
 */
function csr_people_import_render() {
	$done = 0;
	$skip = array();

	if ( isset( $_POST['csr_people_import_nonce'] )
		&& wp_verify_nonce( sanitize_key( $_POST['csr_people_import_nonce'] ), 'csr_people_import' )
		&& current_user_can( 'edit_posts' ) ) {

		csr_seed_bodies();
		$raw   = isset( $_POST['csr_people_data'] ) ? wp_unslash( $_POST['csr_people_data'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput — po řádcích níž
		$lines = preg_split( '/\R/', $raw );

		foreach ( $lines as $line ) {
			$person = csr_parse_person_line( $line );
			if ( ! $person ) {
				continue;
			}

			// Orgán se hledá podle názvu i podle zkratky.
			$body = sanitize_title( $person['body'] );
			if ( ! array_key_exists( $body, csr_bodies() ) ) {
				foreach ( csr_bodies() as $slug => $name ) {
					if ( sanitize_title( $name ) === $body ) {
						$body = $slug;
						break;
					}
				}
			}
			$body = array_key_exists( $body, csr_bodies() ) ? $body : '';

			/*
			 * Hledáme v rámci orgánu, ne v celém seznamu: Ondřej Jílek je
			 * v kontrolní komisi i předsedou BZK Praha a jsou to dva různé
			 * záznamy s jinou funkcí.
			 */
			$post_id = csr_find_person( $person['name'], $body );

			// Lidé zavedení dřív mají místo jména jen příjmení („Valt").
			// Doplníme jim celé jméno, ať se nezaloží podruhé.
			if ( ! $post_id ) {
				$prijmeni = csr_person_surname( $person['name'] );
				$post_id  = $prijmeni ? csr_find_person( $prijmeni, $body ) : 0;
				if ( $post_id ) {
					wp_update_post(
						array(
							'ID'         => $post_id,
							'post_title' => sanitize_text_field( $person['name'] ),
						)
					);
				}
			}

			if ( $post_id ) {
				$skip[] = $person['name'];
			} else {
				$post_id = wp_insert_post(
					array(
						'post_type'   => 'csr_person',
						'post_title'  => sanitize_text_field( $person['name'] ),
						'post_status' => 'publish',
					)
				);
				if ( is_wp_error( $post_id ) ) {
					continue;
				}
				$done++;
			}

			// U existujícího člověka doplňujeme jen to, co má prázdné.
			foreach ( array( 'role', 'club', 'email', 'phone' ) as $key ) {
				$value = 'email' === $key ? sanitize_email( $person[ $key ] ) : sanitize_text_field( $person[ $key ] );
				if ( '' !== $value && '' === (string) get_post_meta( $post_id, '_csr_person_' . $key, true ) ) {
					update_post_meta( $post_id, '_csr_person_' . $key, $value );
				}
			}
			$track = csr_track_key( $person['track'] );
			if ( '' !== $track && '' === (string) get_post_meta( $post_id, '_csr_person_track', true ) ) {
				update_post_meta( $post_id, '_csr_person_track', $track );
			}
			if ( $body ) {
				wp_set_object_terms( $post_id, $body, 'csr_body' );
			}
		}
	}
	?>
	<div class="wrap">
		<h1>Hromadné vložení lidí</h1>

		<?php if ( $done ) : ?>
			<div class="notice notice-success"><p>Vloženo záznamů: <strong><?php echo (int) $done; ?></strong>. Fotky doplňte u jednotlivých lidí jako náhledový obrázek.</p></div>
		<?php endif; ?>
		<?php if ( $skip ) : ?>
			<div class="notice notice-info"><p>Už existovali, doplnilo se jen to, co měli prázdné: <?php echo esc_html( implode( ', ', $skip ) ); ?></p></div>
		<?php endif; ?>

		<p>Jeden člověk na řádek, údaje oddělené svislítkem <code>|</code>:</p>
		<p><code>jméno | orgán | funkce | dráha | klub | e-mail | telefon</code></p>
		<p><strong>Orgán</strong>: <code>predsednictvo</code>, <code>kontrolni-komise</code> nebo <code>predsedove</code>.<br>
			<strong>Dráha</strong> se vyplňuje jen u předsedů: <code>dlouhá</code> nebo <code>krátká</code>. Ostatní pole jsou nepovinná.</p>

		<form method="post">
			<?php wp_nonce_field( 'csr_people_import', 'csr_people_import_nonce' ); ?>
			<?php echo wp_kses_post( csr_import_seed_note( 'lide' ) ); ?>
			<textarea name="csr_people_data" rows="14" style="width:100%;font-family:monospace" placeholder="Jan Novák|predsednictvo|předseda|||jan.novak@example.cz|
Petr Kulma|predsedove|předseda|dlouhá|SKR HLINSKO||"><?php echo esc_textarea( csr_import_seed( 'lide' ) ); ?></textarea>
			<?php submit_button( 'Vložit lidi' ); ?>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Vykreslení
 * ---------------------------------------------------------------------- */

/**
 * Lidé v jednom orgánu.
 *
 * @param string $body  Zkratka orgánu.
 * @param string $track Volitelně jen jedna dráha.
 * @return WP_Post[]
 */
function csr_get_people( $body, $track = null ) {
	$args = array(
		'post_type'      => 'csr_person',
		'posts_per_page' => -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'csr_body',
				'field'    => 'slug',
				'terms'    => $body,
			),
		),
	);
	if ( null !== $track ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_csr_person_track',
				'value'   => $track,
				'compare' => '=',
			),
		);
	}
	return get_posts( $args );
}

/**
 * Karta jednoho člověka.
 *
 * @param WP_Post $person Člověk.
 */
function csr_render_person( $person ) {
	$role  = get_post_meta( $person->ID, '_csr_person_role', true );
	$club  = get_post_meta( $person->ID, '_csr_person_club', true );
	$email = get_post_meta( $person->ID, '_csr_person_email', true );
	$phone = get_post_meta( $person->ID, '_csr_person_phone', true );

	// Když je jméno vypsané přímo v obrázku, text se schová před oči,
	// ale zůstane pro odečítače a vyhledávače.
	$hidden = csr_opt( 'csr_people_names_in_photo', 0 ) ? ' csr-person__text--sr' : '';
	?>
	<?php $foto = csr_thumb_html( $person->ID, 'medium_large', array( 'alt' => '' ) ); ?>
	<article class="csr-person csr-reveal<?php echo '' !== $foto ? '' : ' csr-person--bezfotky'; ?>">
		<div class="csr-person__photo">
			<?php if ( '' !== $foto ) : ?>
				<?php echo $foto; // phpcs:ignore WordPress.Security.EscapeOutput — sestaveno v csr_thumb_html() ?>
			<?php else : ?>
				<span class="csr-person__mono" aria-hidden="true"><?php echo esc_html( csr_person_initials( $person->post_title ) ); ?></span>
			<?php endif; ?>
		</div>

		<div class="csr-person__text<?php echo esc_attr( $hidden ); ?>">
			<h3 class="csr-person__name"><?php echo esc_html( $person->post_title ); ?></h3>
			<?php if ( $role ) : ?>
				<p class="csr-person__role"><?php echo esc_html( $role ); ?></p>
			<?php endif; ?>
			<?php if ( $club ) : ?>
				<p class="csr-person__club"><?php echo esc_html( $club ); ?></p>
			<?php endif; ?>
			<?php if ( $email || $phone ) : ?>
				<p class="csr-person__contact">
					<?php if ( $email ) : ?>
						<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
					<?php endif; ?>
					<?php if ( $phone ) : ?>
						<a href="<?php echo esc_url( csr_tel_href( $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
	</article>
	<?php
}

/* -------------------------------------------------------------------------
 * Doplnění lidí po aktualizaci šablony
 *
 * Předsednictvo a kontrolní komise byly na webu zavedené jen příjmením,
 * bez funkce a kontaktu, a u předsedů klubů chyběla dráha. Čekat, že se
 * hromadné vložení spustí ručně, se ukázalo jako nespolehlivé. Tohle
 * jednou projde už existující lidi a doplní jim, co mají prázdné.
 * Nikoho nezakládá a nic nepřepisuje.
 * ---------------------------------------------------------------------- */

/**
 * Doplní chybějící údaje lidí z připravených dat.
 */
function csr_people_autofill() {
	$verze = (string) wp_get_theme()->get( 'Version' );
	if ( get_option( 'csr_people_filled' ) === $verze ) {
		return;
	}
	update_option( 'csr_people_filled', $verze, false );

	$data = csr_import_seed( 'lide' );
	if ( '' === $data ) {
		return;
	}

	csr_seed_bodies();
	$doplneno = 0;

	foreach ( preg_split( '/\R/', $data ) as $radek ) {
		$osoba = csr_parse_person_line( $radek );
		if ( ! $osoba ) {
			continue;
		}

		$body = csr_body_slug( $osoba['body'] );
		$id   = csr_find_person( $osoba['name'], $body );

		// Lidé zavedení jen příjmením — a taky s překlepem v něm.
		if ( ! $id ) {
			$id = csr_find_person_by_surname( $osoba['name'], $body );
			if ( $id ) {
				wp_update_post(
					array(
						'ID'         => $id,
						'post_title' => sanitize_text_field( $osoba['name'] ),
					)
				);
			}
		}

		/*
		 * U předsedů klubů je spolehlivější klíč klub než jméno — u dvou
		 * se jméno na webu a v podkladech liší a přejmenovat člověka
		 * podle nejistého zdroje nechceme. Doplníme jen prázdná pole.
		 */
		if ( ! $id && '' !== $osoba['club'] ) {
			$id = csr_find_person_by_club( $osoba['club'], $body );
		}

		if ( ! $id ) {
			continue;
		}

		$zmena = false;
		foreach ( array( 'role', 'club', 'email', 'phone' ) as $klic ) {
			$hodnota = 'email' === $klic ? sanitize_email( $osoba[ $klic ] ) : sanitize_text_field( $osoba[ $klic ] );
			if ( '' !== $hodnota && '' === (string) get_post_meta( $id, '_csr_person_' . $klic, true ) ) {
				update_post_meta( $id, '_csr_person_' . $klic, $hodnota );
				$zmena = true;
			}
		}
		$draha = csr_track_key( $osoba['track'] );
		if ( '' !== $draha && '' === (string) get_post_meta( $id, '_csr_person_track', true ) ) {
			update_post_meta( $id, '_csr_person_track', $draha );
			$zmena = true;
		}
		if ( $body ) {
			wp_set_object_terms( $id, $body, 'csr_body' );
		}
		if ( $zmena ) {
			$doplneno++;
		}
	}

	if ( $doplneno ) {
		set_transient( 'csr_people_notice', $doplneno, DAY_IN_SECONDS );
	}
}
add_action( 'admin_init', 'csr_people_autofill' );

/**
 * Slug orgánu z názvu i ze zkratky.
 *
 * @param string $nazev Zapsaný orgán.
 * @return string Slug, nebo prázdný řetězec.
 */
function csr_body_slug( $nazev ) {
	$slug = sanitize_title( $nazev );
	if ( array_key_exists( $slug, csr_bodies() ) ) {
		return $slug;
	}
	foreach ( csr_bodies() as $klic => $jmeno ) {
		if ( sanitize_title( $jmeno ) === $slug ) {
			return $klic;
		}
	}
	return '';
}

/**
 * Najde člověka podle příjmení — i když je v něm přehozené písmeno.
 *
 * Na webu je „Cheml" místo „Chmel". Porovnáváme proto i seřazená
 * písmena, ale jen v rámci jednoho orgánu, kde je lidí pár.
 *
 * @param string $jmeno Celé jméno z dat.
 * @param string $body  Slug orgánu.
 * @return int ID záznamu, nebo 0.
 */
function csr_find_person_by_surname( $jmeno, $body ) {
	$prijmeni = csr_person_surname( $jmeno );
	if ( '' === $prijmeni || '' === $body ) {
		return 0;
	}

	$id = csr_find_person( $prijmeni, $body );
	if ( $id ) {
		return $id;
	}

	$otisk = csr_letter_fingerprint( $prijmeni );
	foreach ( csr_get_people( $body ) as $clovek ) {
		$slova = preg_split( '/\s+/u', $clovek->post_title, -1, PREG_SPLIT_NO_EMPTY );
		if ( count( $slova ) > 1 ) {
			continue; // Celé jméno už je vyplněné, tomu se nepleteme.
		}
		if ( csr_letter_fingerprint( $clovek->post_title ) === $otisk ) {
			return (int) $clovek->ID;
		}
	}
	return 0;
}

/**
 * Najde člověka podle klubu, který zastupuje.
 *
 * @param string $klub Název klubu.
 * @param string $body Slug orgánu.
 * @return int ID záznamu, nebo 0.
 */
function csr_find_person_by_club( $klub, $body ) {
	if ( '' === $body ) {
		return 0;
	}
	$hledany = csr_fold( $klub );
	foreach ( csr_get_people( $body ) as $clovek ) {
		if ( csr_fold( (string) get_post_meta( $clovek->ID, '_csr_person_club', true ) ) === $hledany ) {
			return (int) $clovek->ID;
		}
	}
	return 0;
}

/**
 * Písmena slova seřazená — pro porovnání přes překlep.
 *
 * @param string $slovo Slovo.
 * @return string
 */
function csr_letter_fingerprint( $slovo ) {
	$slovo = csr_fold( $slovo );
	$znaky = preg_split( '//u', $slovo, -1, PREG_SPLIT_NO_EMPTY );
	sort( $znaky );
	return implode( '', $znaky );
}

/**
 * Řekne správci, že se lidé doplnili sami.
 */
function csr_people_notice() {
	$pocet = get_transient( 'csr_people_notice' );
	if ( ! $pocet ) {
		return;
	}
	delete_transient( 'csr_people_notice' );
	printf(
		'<div class="notice notice-success is-dismissible"><p>U <strong>%d</strong> lidí ve svazu se doplnily chybějící údaje. Nic vyplněného se nepřepisovalo.</p></div>',
		(int) $pocet
	);
}
add_action( 'admin_notices', 'csr_people_notice' );
