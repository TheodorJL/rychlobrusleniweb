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

			$exists = get_posts(
				array(
					'post_type'      => 'csr_person',
					'title'          => $person['name'],
					'posts_per_page' => 1,
					'post_status'    => 'any',
					'fields'         => 'ids',
				)
			);
			if ( $exists ) {
				$skip[] = $person['name'];
				continue;
			}

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

			foreach ( array( 'role', 'club', 'email', 'phone' ) as $key ) {
				$value = 'email' === $key ? sanitize_email( $person[ $key ] ) : sanitize_text_field( $person[ $key ] );
				update_post_meta( $post_id, '_csr_person_' . $key, $value );
			}
			update_post_meta( $post_id, '_csr_person_track', csr_track_key( $person['track'] ) );

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
			if ( array_key_exists( $body, csr_bodies() ) ) {
				wp_set_object_terms( $post_id, $body, 'csr_body' );
			}
			$done++;
		}
	}
	?>
	<div class="wrap">
		<h1>Hromadné vložení lidí</h1>

		<?php if ( $done ) : ?>
			<div class="notice notice-success"><p>Vloženo záznamů: <strong><?php echo (int) $done; ?></strong>. Fotky doplňte u jednotlivých lidí jako náhledový obrázek.</p></div>
		<?php endif; ?>
		<?php if ( $skip ) : ?>
			<div class="notice notice-warning"><p>Přeskočeno (jméno už existuje): <?php echo esc_html( implode( ', ', $skip ) ); ?></p></div>
		<?php endif; ?>

		<p>Jeden člověk na řádek, údaje oddělené svislítkem <code>|</code>:</p>
		<p><code>jméno | orgán | funkce | dráha | klub | e-mail | telefon</code></p>
		<p><strong>Orgán</strong>: <code>predsednictvo</code>, <code>kontrolni-komise</code> nebo <code>predsedove</code>.<br>
			<strong>Dráha</strong> se vyplňuje jen u předsedů: <code>dlouhá</code> nebo <code>krátká</code>. Ostatní pole jsou nepovinná.</p>

		<form method="post">
			<?php wp_nonce_field( 'csr_people_import', 'csr_people_import_nonce' ); ?>
			<textarea name="csr_people_data" rows="14" style="width:100%;font-family:monospace" placeholder="Jan Novák|predsednictvo|předseda|||jan.novak@example.cz|
Petr Kulma|predsedove|předseda|dlouhá|SKR HLINSKO||"></textarea>
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
	<article class="csr-person csr-reveal">
		<div class="csr-person__photo">
			<?php if ( has_post_thumbnail( $person->ID ) ) : ?>
				<?php
				echo get_the_post_thumbnail(
					$person->ID,
					'medium_large',
					array(
						// Jméno je hned vedle v textu, popis by ho jen zopakoval.
						'alt'      => '',
						'loading'  => 'lazy',
						'decoding' => 'async',
					)
				);
				?>
			<?php else : ?>
				<span class="csr-person__silhouette" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"><circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg>
				</span>
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
