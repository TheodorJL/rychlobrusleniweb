<?php
/**
 * Databáze klubů.
 *
 * Na původní stránce byly kluby ručně vysázené v Elementoru a pět z nich
 * mělo tlačítko „Web klubu", které nikam nevedlo. Tady se tlačítko vykreslí
 * jen tehdy, když adresa opravdu existuje.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Typ obsahu a taxonomie
 * ---------------------------------------------------------------------- */

/**
 * Zaregistruje kluby a kraje.
 */
function csr_register_clubs() {
	register_post_type(
		'csr_club',
		array(
			'labels'       => array(
				'name'          => 'Kluby',
				'singular_name' => 'Klub',
				'add_new'       => 'Přidat klub',
				'add_new_item'  => 'Přidat klub',
				'edit_item'     => 'Upravit klub',
				'search_items'  => 'Hledat klub',
				'not_found'     => 'Zatím tu není žádný klub.',
				'menu_name'     => 'Kluby',
			),
			'public'       => false,
			'show_ui'      => true,
			'menu_icon'    => 'dashicons-groups',
			'menu_position'=> 26,
			'supports'     => array( 'title', 'thumbnail', 'page-attributes' ),
			'show_in_rest' => true,
		)
	);

	register_taxonomy(
		'csr_region',
		'csr_club',
		array(
			'labels'            => array(
				'name'          => 'Kraje',
				'singular_name' => 'Kraj',
				'add_new_item'  => 'Přidat kraj',
			),
			'public'            => false,
			'show_ui'           => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
		)
	);
}
add_action( 'init', 'csr_register_clubs' );

/**
 * Pole u klubu.
 *
 * @return array
 */
function csr_club_fields() {
	return array(
		'full'    => array( 'label' => 'Úplný název', 'hint' => 'Jak je klub zapsaný v rejstříku.' ),
		'ico'     => array( 'label' => 'IČO', 'hint' => 'Nepovinné. Prolinkuje se do rejstříku ARES.' ),
		'contact' => array( 'label' => 'Kontaktní osoba', 'hint' => 'Nepovinné.' ),
		'phone'   => array( 'label' => 'Telefon', 'hint' => 'Nepovinné. Na mobilu půjde vytočit.' ),
		'email'   => array( 'label' => 'E-mail', 'hint' => 'Nepovinné.' ),
		'street'  => array( 'label' => 'Ulice a číslo', 'hint' => 'Nepovinné.' ),
		'zip'     => array( 'label' => 'PSČ', 'hint' => 'Podle něj se dá doplnit kraj.' ),
		'city'    => array( 'label' => 'Obec', 'hint' => 'Nepovinné.' ),
		'web'     => array( 'label' => 'Web klubu', 'hint' => 'Bez adresy se tlačítko „Web klubu" vůbec nezobrazí.' ),
	);
}

/**
 * Kraj podle PSČ.
 *
 * @param string $zip PSČ.
 * @return string Název kraje, nebo prázdno.
 */
function csr_region_from_zip( $zip ) {
	$cislice = preg_replace( '/\D/', '', (string) $zip );
	if ( strlen( $cislice ) < 2 ) {
		return '';
	}
	$prefix = (int) substr( $cislice, 0, 2 );

	$kraje = array(
		'Praha'             => array( array( 10, 19 ) ),
		'Středočeský'       => array( array( 25, 29 ) ),
		'Jihočeský'         => array( array( 37, 39 ) ),
		'Plzeňský'          => array( array( 30, 34 ) ),
		'Karlovarský'       => array( array( 35, 36 ) ),
		'Ústecký'           => array( array( 40, 44 ) ),
		'Liberecký'         => array( array( 46, 47 ) ),
		// Náchodsko a Trutnovsko mají 54–55, ale patří ke Královéhradeckému.
		'Královéhradecký'   => array( array( 50, 52 ), array( 54, 55 ) ),
		'Pardubický'        => array( array( 53, 53 ), array( 56, 57 ) ),
		'Vysočina'          => array( array( 58, 59 ) ),
		'Jihomoravský'      => array( array( 60, 69 ) ),
		'Olomoucký'         => array( array( 77, 79 ) ),
		'Moravskoslezský'   => array( array( 70, 74 ) ),
		'Zlínský'           => array( array( 75, 76 ) ),
	);

	foreach ( $kraje as $nazev => $rozsahy ) {
		foreach ( $rozsahy as $r ) {
			if ( $prefix >= $r[0] && $prefix <= $r[1] ) {
				return $nazev;
			}
		}
	}
	return '';
}

/* -------------------------------------------------------------------------
 * Formulář u klubu
 * ---------------------------------------------------------------------- */

/**
 * Box s údaji klubu.
 */
function csr_club_metabox() {
	add_meta_box( 'csr_club_data', 'Údaje klubu', 'csr_club_metabox_render', 'csr_club', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'csr_club_metabox' );

/**
 * Vykreslí formulář klubu.
 *
 * @param WP_Post $post Klub.
 */
function csr_club_metabox_render( $post ) {
	wp_nonce_field( 'csr_club_save', 'csr_club_nonce' );
	?>
	<style>
		.csr-cf { display: grid; gap: .9rem; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); }
		.csr-cf label { display: block; font-weight: 600; margin-bottom: .2rem; }
		.csr-cf input { width: 100%; }
		.csr-cf .desc { color: #646970; font-size: 12px; margin: .2rem 0 0; }
		.csr-cf__warn { grid-column: 1 / -1; padding: .6rem .8rem; background: #fcf9e8; border-left: 4px solid #dba617; }
	</style>
	<div class="csr-cf">
		<?php foreach ( csr_club_fields() as $key => $field ) : ?>
			<div>
				<label for="csr-club-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
				<input type="text" id="csr-club-<?php echo esc_attr( $key ); ?>"
					name="csr_club_<?php echo esc_attr( $key ); ?>"
					value="<?php echo esc_attr( get_post_meta( $post->ID, '_csr_club_' . $key, true ) ); ?>">
				<?php if ( ! empty( $field['hint'] ) ) : ?>
					<p class="desc"><?php echo esc_html( $field['hint'] ); ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<div style="grid-column: 1 / -1">
			<label for="csr-club-logo">Adresa loga</label>
			<input type="text" id="csr-club-logo" name="csr_club_logo"
				value="<?php echo esc_attr( get_post_meta( $post->ID, '_csr_club_logo', true ) ); ?>">
			<p class="desc">
				Použije se, jen když klub nemá <strong>náhledový obrázek</strong> — ten má vždycky přednost.
				<?php if ( has_post_thumbnail( $post->ID ) ) : ?>
					Tenhle klub náhledový obrázek má, takže se adresa neuplatní.
				<?php endif; ?>
			</p>
		</div>

		<?php if ( ! get_post_meta( $post->ID, '_csr_club_web', true ) ) : ?>
			<p class="csr-cf__warn">
				Bez adresy webu se u klubu tlačítko <strong>„Web klubu"</strong> nezobrazí.
				Na původním webu takové tlačítko u pěti klubů viselo a nikam nevedlo.
			</p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Uloží klub a doplní kraj podle PSČ.
 *
 * @param int $post_id ID klubu.
 */
function csr_club_save( $post_id ) {
	if ( ! isset( $_POST['csr_club_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['csr_club_nonce'] ), 'csr_club_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['csr_club_logo'] ) ) {
		update_post_meta( $post_id, '_csr_club_logo', esc_url_raw( wp_unslash( $_POST['csr_club_logo'] ) ) );
	}

	foreach ( array_keys( csr_club_fields() ) as $key ) {
		if ( ! isset( $_POST[ 'csr_club_' . $key ] ) ) {
			continue;
		}
		$value = sanitize_text_field( wp_unslash( $_POST[ 'csr_club_' . $key ] ) );
		if ( 'web' === $key && $value ) {
			$value = esc_url_raw( $value );
		}
		if ( 'email' === $key && $value ) {
			$value = sanitize_email( $value );
		}
		update_post_meta( $post_id, '_csr_club_' . $key, $value );
	}

	// Kraj doplníme jen tehdy, když si ho správce nenastavil sám.
	if ( ! wp_get_object_terms( $post_id, 'csr_region', array( 'fields' => 'ids' ) ) ) {
		$kraj = csr_region_from_zip( get_post_meta( $post_id, '_csr_club_zip', true ) );
		if ( $kraj ) {
			wp_set_object_terms( $post_id, $kraj, 'csr_region' );
		}
	}
}
add_action( 'save_post_csr_club', 'csr_club_save' );

/* -------------------------------------------------------------------------
 * Přehled v administraci
 * ---------------------------------------------------------------------- */

/**
 * Sloupce v seznamu klubů.
 *
 * @param array $sloupce Původní sloupce.
 * @return array
 */
function csr_club_columns( $sloupce ) {
	$nove = array();
	foreach ( $sloupce as $klic => $popis ) {
		$nove[ $klic ] = $popis;
		if ( 'title' === $klic ) {
			$nove['csr_web']  = 'Web klubu';
			$nove['csr_mesto'] = 'Obec';
		}
	}
	return $nove;
}
add_filter( 'manage_csr_club_posts_columns', 'csr_club_columns' );

/**
 * Obsah sloupců.
 *
 * @param string $sloupec Klíč sloupce.
 * @param int    $post_id ID klubu.
 */
function csr_club_column( $sloupec, $post_id ) {
	if ( 'csr_web' === $sloupec ) {
		$web = get_post_meta( $post_id, '_csr_club_web', true );
		if ( $web ) {
			printf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $web ),
				esc_html( (string) wp_parse_url( $web, PHP_URL_HOST ) )
			);
		} else {
			echo '<span style="color:#b32d2e">chybí — tlačítko se nezobrazí</span>';
		}
		return;
	}
	if ( 'csr_mesto' === $sloupec ) {
		echo esc_html( (string) get_post_meta( $post_id, '_csr_club_city', true ) );
	}
}
add_action( 'manage_csr_club_posts_custom_column', 'csr_club_column', 10, 2 );

/**
 * Spočítá kluby bez webu.
 */
function csr_club_admin_notice() {
	$obrazovka = get_current_screen();
	if ( ! $obrazovka || 'edit-csr_club' !== $obrazovka->id ) {
		return;
	}
	$bez = 0;
	foreach ( csr_get_clubs() as $club ) {
		if ( ! get_post_meta( $club->ID, '_csr_club_web', true ) ) {
			$bez++;
		}
	}
	if ( $bez ) {
		printf(
			'<div class="notice notice-info"><p><strong>%d klubů nemá vyplněný web.</strong> U nich se tlačítko „Web klubu" nezobrazí — nevzniknou tak odkazy, které nikam nevedou.</p></div>',
			(int) $bez
		);
	}
}
add_action( 'admin_notices', 'csr_club_admin_notice' );

/* -------------------------------------------------------------------------
 * Hromadné vložení
 * ---------------------------------------------------------------------- */

/**
 * Přidá stránku pro hromadné vložení klubů.
 */
function csr_club_import_page() {
	add_submenu_page(
		'edit.php?post_type=csr_club',
		'Hromadné vložení klubů',
		'Hromadné vložení',
		'edit_posts',
		'csr-club-import',
		'csr_club_import_render'
	);
}
add_action( 'admin_menu', 'csr_club_import_page' );

/**
 * Vykreslí a zpracuje hromadné vložení.
 */
function csr_club_import_render() {
	$hotovo   = 0;
	$doplnene = 0;
	$log      = 0;
	$adresy     = 0;
	$nenalezeno = 0;

	if ( isset( $_POST['csr_club_import_nonce'] )
		&& wp_verify_nonce( sanitize_key( $_POST['csr_club_import_nonce'] ), 'csr_club_import' )
		&& current_user_can( 'edit_posts' )
		&& ! empty( $_POST['csr_club_data'] ) ) {

		$radky = explode( "\n", (string) wp_unslash( $_POST['csr_club_data'] ) );
		foreach ( $radky as $radek ) {
			$radek = trim( $radek );
			if ( '' === $radek || '#' === $radek[0] ) {
				continue;
			}
			$casti = array_map( 'trim', explode( '|', $radek ) );
			$nazev = array_shift( $casti );
			if ( ! $nazev ) {
				continue;
			}

			/*
			 * Klub stejného názvu nezakládáme podruhé, jen u něj doplníme,
			 * co chybí. Bez toho by opakované vložení souboru s nově
			 * přibylým sloupcem (logo) buď neudělalo nic, nebo kluby zdvojilo.
			 */
			$existuje = get_posts(
				array(
					'post_type'      => 'csr_club',
					'title'          => $nazev,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			if ( $existuje ) {
				$post_id = (int) $existuje[0];
				$doplnene++;
			} else {
				$post_id = wp_insert_post(
					array(
						'post_type'   => 'csr_club',
						'post_title'  => sanitize_text_field( $nazev ),
						'post_status' => 'publish',
					)
				);
				if ( is_wp_error( $post_id ) ) {
					continue;
				}
				$hotovo++;
			}

			$pocet_poli = count( csr_club_fields() );
			foreach ( array_keys( csr_club_fields() ) as $i => $key ) {
				// U existujícího klubu nepřepisujeme, co už má vyplněné.
				if ( isset( $casti[ $i ] ) && '' !== $casti[ $i ]
					&& ( ! $existuje || '' === (string) get_post_meta( $post_id, '_csr_club_' . $key, true ) ) ) {
					update_post_meta( $post_id, '_csr_club_' . $key, sanitize_text_field( $casti[ $i ] ) );
				}
			}

			/*
			 * Za posledním polem může být adresa loga. Soubor se nenahrává —
			 * obrázky ze starého webu už v knihovně médií jsou. Adresu si
			 * uložíme vždycky: když se příloha v knihovně nenajde, vykreslí
			 * se logo přímo z ní, ať to nespadne zpátky na iniciály.
			 */
			if ( ! empty( $casti[ $pocet_poli ] ) ) {
				$logo_url = esc_url_raw( $casti[ $pocet_poli ] );

				if ( $logo_url && '' === (string) get_post_meta( $post_id, '_csr_club_logo', true ) ) {
					update_post_meta( $post_id, '_csr_club_logo', $logo_url );
					$adresy++;
				}

				if ( $logo_url && ! has_post_thumbnail( $post_id ) ) {
					$logo_id = csr_attachment_from_url( $logo_url );
					if ( $logo_id ) {
						set_post_thumbnail( $post_id, $logo_id );
						$log++;
					} else {
						$nenalezeno++;
					}
				}
			}
			$kraj = csr_region_from_zip( get_post_meta( $post_id, '_csr_club_zip', true ) );
			if ( $kraj ) {
				wp_set_object_terms( $post_id, $kraj, 'csr_region' );
			}
		}
	}
	?>
	<div class="wrap">
		<h1>Hromadné vložení klubů</h1>
		<?php if ( $hotovo || $doplnene ) : ?>
			<div class="notice notice-success"><p>
				Vloženo <strong><?php echo (int) $hotovo; ?></strong> klubů.
				<?php if ( $doplnene ) : ?>
					U <strong><?php echo (int) $doplnene; ?></strong> už existujících se doplnilo, co chybělo.
				<?php endif; ?>
			</p></div>
			<?php if ( $log || $adresy ) : ?>
				<div class="notice notice-success"><p>
					<?php if ( $log ) : ?>
						Přiřazeno log z knihovny médií: <strong><?php echo (int) $log; ?></strong>.
					<?php endif; ?>
					<?php if ( $nenalezeno ) : ?>
						U <strong><?php echo (int) $nenalezeno; ?></strong> se v knihovně nenašla příloha —
						logo se vykreslí přímo z uložené adresy.
					<?php endif; ?>
				</p></div>
			<?php endif; ?>
		<?php endif; ?>
		<p>Jeden klub na řádek, hodnoty oddělte svislítkem <code>|</code> v tomto pořadí:</p>
		<p><code>Zkratka | <?php echo esc_html( implode( ' | ', wp_list_pluck( csr_club_fields(), 'label' ) ) ); ?></code></p>
		<p>Řádek začínající <code>#</code> se přeskočí. Kraj se doplní podle PSČ.</p>
		<form method="post">
			<?php wp_nonce_field( 'csr_club_import', 'csr_club_import_nonce' ); ?>
			<?php echo wp_kses_post( csr_import_seed_note( 'kluby' ) ); ?>
			<textarea name="csr_club_data" rows="12" style="width:100%;font-family:monospace"><?php echo esc_textarea( csr_import_seed( 'kluby' ) ); ?></textarea>
			<p><button type="submit" class="button button-primary">Vložit kluby</button></p>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Vykreslení
 * ---------------------------------------------------------------------- */

/**
 * Všechny kluby pro výpis.
 *
 * @return WP_Post[]
 */
function csr_get_clubs() {
	return get_posts(
		array(
			'post_type'      => 'csr_club',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		)
	);
}

/**
 * Značka s logem klubu.
 *
 * Na ostrém webu se ukázalo, že get_the_post_thumbnail() umí vrátit
 * prázdno i tehdy, když příloha existuje a soubor je dostupný — karta
 * pak zůstala prázdná. Nespoléháme na ni tedy jako na jediný zdroj:
 * když nic nevrátí, poskládáme značku z adresy přílohy, a teprve
 * nakonec sáhneme po adrese uložené při hromadném vložení.
 *
 * @param WP_Post $club Klub.
 * @return string HTML značky, nebo prázdný řetězec.
 */
function csr_club_logo_html( $club ) {
	return csr_thumb_html(
		$club->ID,
		'medium',
		array( 'alt' => 'Logo ' . $club->post_title ),
		(string) get_post_meta( $club->ID, '_csr_club_logo', true )
	);
}

/**
 * Karta jednoho klubu.
 *
 * @param WP_Post $club Klub.
 */
function csr_render_club_card( $club ) {
	$meta = array();
	foreach ( array_keys( csr_club_fields() ) as $key ) {
		$meta[ $key ] = get_post_meta( $club->ID, '_csr_club_' . $key, true );
	}

	$regions = wp_get_object_terms( $club->ID, 'csr_region', array( 'fields' => 'names' ) );
	$region  = ! is_wp_error( $regions ) && $regions ? $regions[0] : '';

	// Do vyhledávání dáme vše, podle čeho by někdo mohl hledat.
	$haystack = implode( ' ', array_filter( array( $club->post_title, $meta['full'], $meta['city'], $meta['contact'], $region ) ) );
	?>
	<article class="csr-club csr-reveal"
		data-csr-item
		data-csr-cat="<?php echo esc_attr( $region ? sanitize_title( $region ) : 'bez-kraje' ); ?>"
		data-csr-text="<?php echo esc_attr( $haystack ); ?>">

		<div class="csr-club__logo">
			<?php
			$logo = csr_club_logo_html( $club );
			if ( '' !== $logo ) {
				echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput — sestaveno v csr_club_logo_html()
			} else {
				?>
				<span class="csr-club__initials" aria-hidden="true"><?php echo esc_html( $club->post_title ); ?></span>
				<?php
			}
			?>
		</div>

		<div class="csr-club__body">
			<h3 class="csr-club__name"><?php echo esc_html( $club->post_title ); ?></h3>
			<?php if ( $meta['full'] ) : ?>
				<p class="csr-club__full"><?php echo esc_html( $meta['full'] ); ?></p>
			<?php endif; ?>

			<dl class="csr-club__rows">
				<?php if ( $meta['contact'] ) : ?>
					<div><dt>Kontakt</dt><dd><?php echo esc_html( $meta['contact'] ); ?></dd></div>
				<?php endif; ?>
				<?php if ( $meta['phone'] ) : ?>
					<div><dt>Telefon</dt>
						<dd><a href="<?php echo esc_url( csr_tel_href( $meta['phone'] ) ); ?>"><?php echo esc_html( $meta['phone'] ); ?></a></dd>
					</div>
				<?php endif; ?>
				<?php if ( $meta['email'] ) : ?>
					<div><dt>E-mail</dt>
						<dd><a href="mailto:<?php echo esc_attr( $meta['email'] ); ?>"><?php echo esc_html( $meta['email'] ); ?></a></dd>
					</div>
				<?php endif; ?>
				<?php if ( $meta['street'] || $meta['city'] ) : ?>
					<div><dt>Adresa</dt>
						<dd>
							<?php
							echo esc_html( trim( $meta['street'] . ( $meta['street'] && $meta['zip'] ? ', ' : '' ) . $meta['zip'] . ' ' . $meta['city'] ) );
							?>
						</dd>
					</div>
				<?php endif; ?>
				<?php if ( $meta['ico'] ) : ?>
					<div><dt>IČO</dt>
						<dd>
							<?php if ( csr_opt( 'csr_clubs_ares', 1 ) ) : ?>
								<a href="https://ares.gov.cz/ekonomicke-subjekty?ico=<?php echo esc_attr( preg_replace( '/\D/', '', $meta['ico'] ) ); ?>"
									target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $meta['ico'] ); ?>
								</a>
							<?php else : ?>
								<?php echo esc_html( $meta['ico'] ); ?>
							<?php endif; ?>
						</dd>
					</div>
				<?php endif; ?>
			</dl>

			<?php if ( $meta['web'] ) : ?>
				<a class="csr-club__web" href="<?php echo esc_url( $meta['web'] ); ?>" target="_blank" rel="noopener noreferrer">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
					Web klubu
				</a>
			<?php endif; ?>
		</div>
	</article>
	<?php
}

/* -------------------------------------------------------------------------
 * Doplnění log po aktualizaci šablony
 *
 * Adresy log přibyly do souboru s kluby později, takže kluby vložené dřív
 * je nemají — a čekat, že někdo hromadné vložení spustí znovu, se ukázalo
 * jako nespolehlivé. Tohle po aktualizaci šablony jednou projde už
 * existující kluby a doplní jim jen to, co mají prázdné. Nic nezakládá
 * a nic nepřepisuje.
 * ---------------------------------------------------------------------- */

/**
 * Doplní chybějící loga klubů z připravených dat.
 */
function csr_clubs_autofill_logos() {
	$verze = (string) wp_get_theme()->get( 'Version' );
	if ( get_option( 'csr_clubs_logos_done' ) === $verze ) {
		return;
	}
	// Zapisujeme hned, ať se to nezkouší dokola, i kdyby něco selhalo.
	update_option( 'csr_clubs_logos_done', $verze, false );

	$data = csr_import_seed( 'kluby' );
	if ( '' === $data ) {
		return;
	}

	$pocet_poli = count( csr_club_fields() );
	$doplneno   = 0;

	foreach ( explode( "\n", $data ) as $radek ) {
		$radek = trim( $radek );
		if ( '' === $radek || '#' === $radek[0] ) {
			continue;
		}

		$casti = array_map( 'trim', explode( '|', $radek ) );
		$nazev = array_shift( $casti );
		$logo  = isset( $casti[ $pocet_poli ] ) ? $casti[ $pocet_poli ] : '';
		if ( '' === $nazev || '' === $logo ) {
			continue;
		}

		$kluby = get_posts(
			array(
				'post_type'      => 'csr_club',
				'title'          => $nazev,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( ! $kluby ) {
			continue;
		}

		$post_id = (int) $kluby[0];
		$zmena   = false;

		if ( '' === (string) get_post_meta( $post_id, '_csr_club_logo', true ) ) {
			update_post_meta( $post_id, '_csr_club_logo', esc_url_raw( $logo ) );
			$zmena = true;
		}
		if ( ! has_post_thumbnail( $post_id ) ) {
			$logo_id = csr_attachment_from_url( $logo );
			if ( $logo_id ) {
				set_post_thumbnail( $post_id, $logo_id );
				$zmena = true;
			}
		}
		if ( $zmena ) {
			$doplneno++;
		}
	}

	if ( $doplneno ) {
		set_transient( 'csr_clubs_logos_notice', $doplneno, DAY_IN_SECONDS );
	}
}
add_action( 'admin_init', 'csr_clubs_autofill_logos' );

/**
 * Řekne správci, že se loga doplnila sama.
 */
function csr_clubs_logos_notice() {
	$pocet = get_transient( 'csr_clubs_logos_notice' );
	if ( ! $pocet ) {
		return;
	}
	delete_transient( 'csr_clubs_logos_notice' );
	printf(
		'<div class="notice notice-success is-dismissible"><p>U <strong>%d</strong> klubů se doplnilo logo z knihovny médií. Nic jiného se neměnilo.</p></div>',
		(int) $pocet
	);
}
add_action( 'admin_notices', 'csr_clubs_logos_notice' );
