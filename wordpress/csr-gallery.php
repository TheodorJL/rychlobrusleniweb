<?php
/**
 * Fotogalerie — alba místo nadpisů s prázdnými widgety.
 *
 * Na původní stránce bylo 15 nadpisů, ale jen 6 z nich mělo pod sebou fotky.
 * Zbylých 10 používalo widget „Gallery" z Elementoru Pro, který bez licence
 * nevykreslí nic — návštěvník viděl nadpis a pod ním prázdno.
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
 * Zaregistruje alba.
 */
function csr_register_albums() {
	register_post_type(
		'csr_album',
		array(
			'labels'       => array(
				'name'               => 'Fotogalerie',
				'singular_name'      => 'Album',
				'add_new'            => 'Přidat album',
				'add_new_item'       => 'Přidat album',
				'edit_item'          => 'Upravit album',
				'search_items'       => 'Hledat album',
				'not_found'          => 'Zatím tu není žádné album.',
				'menu_name'          => 'Fotogalerie',
			),
			'public'       => true,
			'has_archive'  => false,
			'menu_icon'    => 'dashicons-format-gallery',
			'menu_position'=> 27,
			'rewrite'      => array( 'slug' => 'galerie' ),
			'supports'     => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'show_in_rest' => true,
		)
	);

	register_taxonomy(
		'csr_album_type',
		'csr_album',
		array(
			'labels'            => array(
				'name'          => 'Rubriky alba',
				'singular_name' => 'Rubrika',
				'add_new_item'  => 'Přidat rubriku',
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'galerie-rubrika' ),
		)
	);
}
add_action( 'init', 'csr_register_albums' );

/**
 * Výchozí rubriky.
 *
 * @return array
 */
function csr_album_types() {
	return array(
		'zavody'       => 'Závody',
		'reprezentace' => 'Reprezentace',
		'nabory'       => 'Nábory a akce',
		'ostatni'      => 'Ostatní',
	);
}

/**
 * Doplní výchozí rubriky, když ještě žádné nejsou.
 */
function csr_seed_album_types() {
	foreach ( csr_album_types() as $slug => $name ) {
		if ( ! term_exists( $slug, 'csr_album_type' ) ) {
			wp_insert_term( $name, 'csr_album_type', array( 'slug' => $slug ) );
		}
	}
}
add_action( 'admin_init', 'csr_seed_album_types' );

/* -------------------------------------------------------------------------
 * Obsah alba
 * ---------------------------------------------------------------------- */

/**
 * Položky alba — ID příloh v pořadí, ve kterém je správce seřadil.
 *
 * @param int $album_id ID alba.
 * @return int[]
 */
function csr_album_items( $album_id ) {
	$raw = get_post_meta( $album_id, '_csr_album_items', true );
	if ( ! $raw ) {
		return array();
	}
	// Pořadí je uložené jako seznam ID, ne dotazem — správce ho určuje sám.
	$ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );
	return array_values( array_unique( $ids ) );
}

/**
 * Rozdělí položky na fotky a videa.
 *
 * @param int[] $ids ID příloh.
 * @return array{fotky:int[],videa:int[]}
 */
function csr_album_split( $ids ) {
	$fotky = array();
	$videa = array();
	foreach ( $ids as $id ) {
		$mime = (string) get_post_mime_type( $id );
		if ( 0 === strpos( $mime, 'video/' ) ) {
			$videa[] = $id;
		} elseif ( 0 === strpos( $mime, 'image/' ) ) {
			$fotky[] = $id;
		}
	}
	return array( 'fotky' => $fotky, 'videa' => $videa );
}

/**
 * Kolik fotek v albu nemá vyplněný alternativní text.
 *
 * @param int[] $ids ID příloh.
 * @return int
 */
function csr_album_missing_alt( $ids ) {
	$chybi = 0;
	foreach ( $ids as $id ) {
		if ( 0 !== strpos( (string) get_post_mime_type( $id ), 'image/' ) ) {
			continue;
		}
		if ( '' === trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ) {
			$chybi++;
		}
	}
	return $chybi;
}

/**
 * Datum alba, na které se dá spolehnout i při řazení.
 *
 * @param int $album_id ID alba.
 * @return string Formát Y-m-d, nebo prázdno.
 */
function csr_album_date( $album_id ) {
	return (string) get_post_meta( $album_id, '_csr_album_date', true );
}

/**
 * Popisek data pro čtenáře.
 *
 * @param int $album_id ID alba.
 * @return string
 */
function csr_album_date_label( $album_id ) {
	$datum = csr_album_date( $album_id );
	if ( ! $datum ) {
		return '';
	}
	$cas = strtotime( $datum );
	return $cas ? wp_date( 'j. F Y', $cas ) : '';
}

/**
 * Alba, volitelně jen z jedné rubriky.
 *
 * @param string $type Zkratka rubriky, nebo prázdno.
 * @return WP_Post[]
 */
function csr_get_albums( $type = '' ) {
	$args = array(
		'post_type'      => 'csr_album',
		'posts_per_page' => -1,
		'meta_key'       => '_csr_album_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'orderby'        => array( 'meta_value' => 'DESC', 'date' => 'DESC' ),
	);
	if ( $type ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'csr_album_type',
				'field'    => 'slug',
				'terms'    => $type,
			),
		);
	}
	return get_posts( $args );
}

/**
 * Obrázek na obálku alba — buď náhledový obrázek, nebo první fotka.
 *
 * @param int $album_id ID alba.
 * @return int ID přílohy, nebo 0.
 */
function csr_album_cover( $album_id ) {
	$id = (int) get_post_thumbnail_id( $album_id );
	if ( $id ) {
		return $id;
	}
	$casti = csr_album_split( csr_album_items( $album_id ) );
	return $casti['fotky'] ? (int) $casti['fotky'][0] : 0;
}

/* -------------------------------------------------------------------------
 * Formulář u alba
 * ---------------------------------------------------------------------- */

/**
 * Přidá box s výběrem fotek.
 */
function csr_album_metabox() {
	add_meta_box( 'csr_album_media', 'Fotky a videa', 'csr_album_metabox_render', 'csr_album', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'csr_album_metabox' );

/**
 * Vykreslí výběr fotek a pole alba.
 *
 * @param WP_Post $post Album.
 */
function csr_album_metabox_render( $post ) {
	wp_nonce_field( 'csr_album_save', 'csr_album_nonce' );
	wp_enqueue_media();

	$ids     = csr_album_items( $post->ID );
	$datum   = csr_album_date( $post->ID );
	$autor   = (string) get_post_meta( $post->ID, '_csr_album_author', true );
	$misto   = (string) get_post_meta( $post->ID, '_csr_album_place', true );
	$chybi   = csr_album_missing_alt( $ids );
	?>
	<style>
		.csr-al__row { margin-bottom: 1rem; }
		.csr-al__row label { display: block; font-weight: 600; margin-bottom: .25rem; }
		.csr-al__row input[type="text"], .csr-al__row input[type="date"] { width: 100%; max-width: 26rem; }
		.csr-al__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: .5rem; margin: .75rem 0; }
		.csr-al__item { position: relative; aspect-ratio: 1; background: #f0f0f1; border-radius: 4px; overflow: hidden; }
		.csr-al__item img { width: 100%; height: 100%; object-fit: cover; display: block; }
		.csr-al__item.is-video { display: grid; place-items: center; font-size: 2rem; }
		.csr-al__item.no-alt::after {
			content: "bez popisu"; position: absolute; inset: auto 0 0 0;
			background: #dba617; color: #1d2327; font-size: 11px; text-align: center; padding: 2px;
		}
		.csr-al__warn { padding: .6rem .8rem; background: #fcf9e8; border-left: 4px solid #dba617; }
		.csr-al__ok { padding: .6rem .8rem; background: #edfaef; border-left: 4px solid #00a32a; }
	</style>

	<div class="csr-al__row">
		<label for="csr-album-date">Datum akce</label>
		<input type="date" id="csr-album-date" name="csr_album_date" value="<?php echo esc_attr( $datum ); ?>">
		<p class="description">Podle něj se alba řadí. Bez data album spadne na konec výpisu.</p>
	</div>

	<div class="csr-al__row">
		<label for="csr-album-place">Místo</label>
		<input type="text" id="csr-album-place" name="csr_album_place" value="<?php echo esc_attr( $misto ); ?>" placeholder="Erfurt (SRN)">
	</div>

	<div class="csr-al__row">
		<label for="csr-album-author">Autor fotografií</label>
		<input type="text" id="csr-album-author" name="csr_album_author" value="<?php echo esc_attr( $autor ); ?>" placeholder="Radovan Syrotiuk">
		<p class="description">Vypíše se pod nadpisem alba. Prázdné pole se nezobrazí.</p>
	</div>

	<p>
		<button type="button" class="button button-primary" id="csr-album-pick">Vybrat fotky a videa</button>
		<button type="button" class="button" id="csr-album-clear">Vyprázdnit</button>
		<span id="csr-album-count"><?php echo (int) count( $ids ); ?> položek</span>
	</p>

	<div class="csr-al__grid" id="csr-album-grid">
		<?php foreach ( $ids as $id ) : ?>
			<?php
			$mime  = (string) get_post_mime_type( $id );
			$video = 0 === strpos( $mime, 'video/' );
			$alt   = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
			$tridy = 'csr-al__item' . ( $video ? ' is-video' : '' ) . ( ( ! $video && '' === $alt ) ? ' no-alt' : '' );
			?>
			<div class="<?php echo esc_attr( $tridy ); ?>">
				<?php if ( $video ) : ?>
					<span aria-hidden="true">▶</span>
				<?php else : ?>
					<?php echo wp_get_attachment_image( $id, 'thumbnail', false, array( 'alt' => '' ) ); ?>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<input type="hidden" id="csr-album-items" name="csr_album_items" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">

	<?php if ( $ids && $chybi ) : ?>
		<p class="csr-al__warn">
			<strong><?php echo (int) $chybi; ?></strong> z <?php echo (int) count( $ids ); ?> fotek nemá vyplněný
			<strong>alternativní text</strong>. Nevidomí návštěvníci se o nich nedozvědí a vyhledávače je nenajdou.
			Doplňte ho v Médiích u každé fotky — WordPress si ho pamatuje, takže to stačí jednou.
		</p>
	<?php elseif ( $ids ) : ?>
		<p class="csr-al__ok">Všechny fotky mají vyplněný alternativní text.</p>
	<?php endif; ?>

	<script>
	( function () {
		var pick  = document.getElementById( 'csr-album-pick' );
		var clear = document.getElementById( 'csr-album-clear' );
		var pole  = document.getElementById( 'csr-album-items' );
		var mriz  = document.getElementById( 'csr-album-grid' );
		var pocet = document.getElementById( 'csr-album-count' );
		var ramec;

		function vykresli( polozky ) {
			mriz.innerHTML = '';
			polozky.forEach( function ( p ) {
				var box = document.createElement( 'div' );
				box.className = 'csr-al__item';
				if ( p.type === 'video' ) {
					box.className += ' is-video';
					box.innerHTML = '<span aria-hidden="true">&#9654;</span>';
				} else {
					// Popis fotky bereme z knihovny médií, nezadává se tady znovu.
					if ( ! p.alt ) { box.className += ' no-alt'; }
					var img = document.createElement( 'img' );
					img.src = ( p.sizes && p.sizes.thumbnail ) ? p.sizes.thumbnail.url : p.url;
					img.alt = '';
					box.appendChild( img );
				}
				mriz.appendChild( box );
			} );
			pocet.textContent = polozky.length + ' položek';
		}

		pick.addEventListener( 'click', function () {
			if ( ! ramec ) {
				ramec = wp.media( {
					title: 'Vyberte fotky a videa alba',
					button: { text: 'Vložit do alba' },
					library: { type: [ 'image', 'video' ] },
					multiple: 'add'
				} );
				ramec.on( 'select', function () {
					var polozky = ramec.state().get( 'selection' ).toJSON();
					pole.value = polozky.map( function ( p ) { return p.id; } ).join( ',' );
					vykresli( polozky );
				} );
			}
			// Předvybereme, co už v albu je, aby se výběr při druhém otevření neztratil.
			ramec.on( 'open', function () {
				var vyber = ramec.state().get( 'selection' );
				vyber.reset();
				( pole.value ? pole.value.split( ',' ) : [] ).forEach( function ( id ) {
					var p = wp.media.attachment( id );
					p.fetch();
					vyber.add( p );
				} );
			} );
			ramec.open();
		} );

		clear.addEventListener( 'click', function () {
			pole.value = '';
			mriz.innerHTML = '';
			pocet.textContent = '0 položek';
		} );
	}() );
	</script>
	<?php
}

/**
 * Uloží album.
 *
 * @param int $post_id ID alba.
 */
function csr_album_save( $post_id ) {
	if ( ! isset( $_POST['csr_album_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['csr_album_nonce'] ), 'csr_album_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['csr_album_items'] ) ) {
		$syrove = sanitize_text_field( wp_unslash( $_POST['csr_album_items'] ) );
		$ids    = array_filter( array_map( 'absint', explode( ',', $syrove ) ) );
		update_post_meta( $post_id, '_csr_album_items', implode( ',', $ids ) );
	}
	foreach ( array( 'date', 'place', 'author' ) as $klic ) {
		if ( isset( $_POST[ 'csr_album_' . $klic ] ) ) {
			update_post_meta(
				$post_id,
				'_csr_album_' . $klic,
				sanitize_text_field( wp_unslash( $_POST[ 'csr_album_' . $klic ] ) )
			);
		}
	}
}
add_action( 'save_post_csr_album', 'csr_album_save' );

/* -------------------------------------------------------------------------
 * Přehled v administraci
 * ---------------------------------------------------------------------- */

/**
 * Sloupce v seznamu alb.
 *
 * @param array $sloupce Původní sloupce.
 * @return array
 */
function csr_album_columns( $sloupce ) {
	$nove = array();
	foreach ( $sloupce as $klic => $popis ) {
		$nove[ $klic ] = $popis;
		if ( 'title' === $klic ) {
			$nove['csr_pocet'] = 'Fotek';
			$nove['csr_datum'] = 'Datum akce';
			$nove['csr_alt']   = 'Popisy fotek';
		}
	}
	return $nove;
}
add_filter( 'manage_csr_album_posts_columns', 'csr_album_columns' );

/**
 * Obsah sloupců.
 *
 * @param string $sloupec Klíč sloupce.
 * @param int    $post_id ID alba.
 */
function csr_album_column( $sloupec, $post_id ) {
	$ids = csr_album_items( $post_id );

	if ( 'csr_pocet' === $sloupec ) {
		$casti = csr_album_split( $ids );
		$texty = array();
		if ( $casti['fotky'] ) {
			$texty[] = count( $casti['fotky'] ) . '×  foto';
		}
		if ( $casti['videa'] ) {
			$texty[] = count( $casti['videa'] ) . '× video';
		}
		echo $texty ? esc_html( implode( ', ', $texty ) ) : '<span style="color:#b32d2e">prázdné</span>';
		return;
	}

	if ( 'csr_datum' === $sloupec ) {
		$popis = csr_album_date_label( $post_id );
		echo $popis ? esc_html( $popis ) : '<span style="color:#996800">nevyplněno — album spadne na konec</span>';
		return;
	}

	if ( 'csr_alt' === $sloupec ) {
		if ( ! $ids ) {
			echo '—';
			return;
		}
		$chybi = csr_album_missing_alt( $ids );
		if ( $chybi ) {
			printf( '<span style="color:#996800">⚠ chybí u %d</span>', (int) $chybi );
		} else {
			echo '<span style="color:#00694e">✓ všechny</span>';
		}
	}
}
add_action( 'manage_csr_album_posts_custom_column', 'csr_album_column', 10, 2 );

/**
 * Upozorní na alba bez fotek a na chybějící popisy.
 */
function csr_album_admin_notice() {
	$obrazovka = get_current_screen();
	if ( ! $obrazovka || 'edit-csr_album' !== $obrazovka->id ) {
		return;
	}

	$prazdna = 0;
	$bez_alt = 0;
	foreach ( csr_get_albums() as $album ) {
		$ids = csr_album_items( $album->ID );
		if ( ! $ids ) {
			$prazdna++;
			continue;
		}
		$bez_alt += csr_album_missing_alt( $ids );
	}

	if ( $prazdna ) {
		printf(
			'<div class="notice notice-error"><p><strong>%d alb nemá ani jednu fotku.</strong> Návštěvník uvidí nadpis a pod ním prázdno. Buď fotky doplňte, nebo album přepněte na koncept.</p></div>',
			(int) $prazdna
		);
	}
	if ( $bez_alt ) {
		printf(
			'<div class="notice notice-warning"><p><strong>%d fotek nemá popis (alternativní text).</strong> Doplňte ho v Médiích — nevidomí návštěvníci ani vyhledávače se jinak o obsahu fotky nedozvědí.</p></div>',
			(int) $bez_alt
		);
	}
}
add_action( 'admin_notices', 'csr_album_admin_notice' );

/* -------------------------------------------------------------------------
 * Vykreslení
 * ---------------------------------------------------------------------- */

/**
 * Přepne detail alba na naši šablonu.
 *
 * @param string $template Cesta k šabloně.
 * @return string
 */
function csr_album_template( $template ) {
	if ( ! is_singular( 'csr_album' ) ) {
		return $template;
	}
	$soubor = get_stylesheet_directory() . '/' . CSR_ALBUM_TEMPLATE;
	return file_exists( $soubor ) ? $soubor : $template;
}
add_filter( 'template_include', 'csr_album_template', 99 );

/**
 * Vykreslí jednu položku galerie.
 *
 * @param int $id    ID přílohy.
 * @param int $index Pořadí (pro lightbox).
 */
function csr_render_media( $id, $index ) {
	$mime  = (string) get_post_mime_type( $id );
	$video = 0 === strpos( $mime, 'video/' );
	$plna  = wp_get_attachment_image_url( $id, 'full' );
	$meta  = wp_get_attachment_metadata( $id );
	$popis = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
	$titul = trim( (string) get_the_title( $id ) );

	// Poměr stran držíme, aby se portréty neořezávaly do čtverce jako dřív.
	$sirka = ! empty( $meta['width'] ) ? (int) $meta['width'] : 4;
	$vyska = ! empty( $meta['height'] ) ? (int) $meta['height'] : 3;

	if ( $video ) :
		?>
		<li class="csr-shot csr-shot--video">
			<video class="csr-shot__video" controls preload="metadata"
				<?php if ( $plna ) : ?>src="<?php echo esc_url( wp_get_attachment_url( $id ) ); ?>"<?php endif; ?>></video>
		</li>
		<?php
		return;
	endif;
	?>
	<li class="csr-shot" style="--csr-ratio: <?php echo esc_attr( $sirka . ' / ' . $vyska ); ?>">
		<button type="button" class="csr-shot__btn"
			data-csr-shot
			data-csr-full="<?php echo esc_url( (string) $plna ); ?>"
			data-csr-index="<?php echo (int) $index; ?>"
			<?php if ( $popis ) : ?>data-csr-caption="<?php echo esc_attr( $popis ); ?>"<?php endif; ?>>
			<?php
			echo wp_get_attachment_image(
				$id,
				'medium_large',
				false,
				array(
					// Prázdný alt jen tehdy, když opravdu žádný není — nevymýšlíme si ho.
					'alt'     => $popis,
					'loading' => $index < 6 ? 'eager' : 'lazy',
					'class'   => 'csr-shot__img',
				)
			);
			?>
			<span class="screen-reader-text">
				<?php echo esc_html( $popis ? $popis : 'Zvětšit fotku ' . ( $index + 1 ) ); ?>
			</span>
		</button>
	</li>
	<?php
	unset( $titul );
}

/* -------------------------------------------------------------------------
 * Hromadné vložení alb
 * ---------------------------------------------------------------------- */

/**
 * Podstránka pro hromadné založení alb.
 */
function csr_albums_import_page() {
	add_submenu_page(
		'edit.php?post_type=csr_album',
		'Hromadné přidání',
		'Hromadné přidání',
		'edit_posts',
		'csr-albums-import',
		'csr_albums_import_render'
	);
}
add_action( 'admin_menu', 'csr_albums_import_page' );

/**
 * Formulář hromadného přidání alb.
 *
 * Fotky se nenahrávají — hledají se v knihovně médií podle adresy.
 * Obrázky ze starého webu tam už jsou, takže se nic nekopíruje
 * a nevznikají duplikáty.
 */
function csr_albums_import_render() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Na tohle nemáte oprávnění.' );
	}

	$zalozeno = 0;
	$fotek    = 0;
	$nenalez  = array();

	if ( isset( $_POST['csr_albums_import_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['csr_albums_import_nonce'] ), 'csr_albums_import' ) ) {
		csr_seed_album_types();
		$raw = isset( $_POST['csr_albums_data'] ) ? sanitize_textarea_field( wp_unslash( $_POST['csr_albums_data'] ) ) : '';

		$poradi = 0;
		foreach ( preg_split( '/\R/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}

			$parts  = array_map( 'trim', explode( '|', $line ) );
			$nazev  = $parts[0];
			$rubrika = isset( $parts[1] ) ? sanitize_title( $parts[1] ) : '';
			$datum  = isset( $parts[2] ) ? $parts[2] : '';
			$fotky  = isset( $parts[3] ) ? $parts[3] : '';

			if ( '' === $nazev ) {
				continue;
			}

			// Album stejného názvu nezakládáme podruhé.
			$existuje = get_posts(
				array(
					'post_type'      => 'csr_album',
					'title'          => $nazev,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			if ( $existuje ) {
				continue;
			}

			$poradi += 10;
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'csr_album',
					'post_title'  => $nazev,
					'post_status' => 'publish',
					'menu_order'  => $poradi,
				)
			);
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}
			$zalozeno++;

			if ( $datum ) {
				update_post_meta( $post_id, '_csr_album_date', sanitize_text_field( $datum ) );
			}
			if ( $rubrika && term_exists( $rubrika, 'csr_album_type' ) ) {
				wp_set_object_terms( $post_id, $rubrika, 'csr_album_type' );
			}

			// Adresy fotek → ID příloh z knihovny médií.
			$ids = array();
			foreach ( preg_split( '/\s*,\s*/', $fotky ) as $url ) {
				$url = trim( $url );
				if ( '' === $url ) {
					continue;
				}
				$id = csr_attachment_from_url( $url );
				if ( $id ) {
					$ids[] = $id;
					$fotek++;
				} else {
					$nenalez[] = $url;
				}
			}
			$ids = array_values( array_unique( $ids ) );
			if ( $ids ) {
				update_post_meta( $post_id, '_csr_album_items', implode( ',', $ids ) );
				// První fotka poslouží jako náhled alba.
				set_post_thumbnail( $post_id, $ids[0] );
			}
		}
	}
	?>
	<div class="wrap">
		<h1>Hromadné přidání alb</h1>

		<?php if ( $zalozeno ) : ?>
			<div class="notice notice-success"><p>
				Založeno alb: <strong><?php echo (int) $zalozeno; ?></strong>,
				přiřazeno fotek z knihovny médií: <strong><?php echo (int) $fotek; ?></strong>.
			</p></div>
		<?php endif; ?>

		<?php if ( $nenalez ) : ?>
			<div class="notice notice-warning"><p>
				<strong><?php echo count( $nenalez ); ?></strong>
				<?php echo 1 === count( $nenalez ) ? 'fotka se v knihovně médií nenašla' : 'fotek se v knihovně médií nenašlo'; ?>.
				Doplňte je u alba ručně. Prvních deset:<br>
				<code style="display:block;white-space:pre-wrap"><?php echo esc_html( implode( "\n", array_slice( $nenalez, 0, 10 ) ) ); ?></code>
			</p></div>
		<?php endif; ?>

		<p>Fotky se <strong>nenahrávají</strong> — hledají se v knihovně médií podle adresy.
			Obrázky ze starého webu v ní už jsou, takže se nic nekopíruje a nevznikají duplikáty.
			Když je na stránce odkaz na zmenšeninu, zkusí se i původní soubor.</p>

		<form method="post">
			<?php wp_nonce_field( 'csr_albums_import', 'csr_albums_import_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="csr_albums_data">Alba</label></th>
					<td>
						<textarea name="csr_albums_data" id="csr_albums_data" rows="12" class="large-text code"
						          placeholder="Název alba|rubrika|datum|adresa1, adresa2, adresa3"></textarea>
						<p class="description">
							Jedno album na řádek. Rubrika je jedna z:
							<?php echo esc_html( implode( ', ', array_keys( csr_album_types() ) ) ); ?>.
							Datum ve tvaru <code>2024-11-03</code>. Adresy fotek oddělte čárkami.
							Album se stejným názvem se přeskočí, takže vložení jde spustit znovu.
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Založit alba' ); ?>
		</form>
	</div>
	<?php
}
