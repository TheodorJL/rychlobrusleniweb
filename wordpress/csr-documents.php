<?php
/**
 * Databáze dokumentů.
 *
 * Na starém webu nebyl ani jeden z třinácti dokumentů na vlastním webu —
 * všechny visely na cizích sdílecích službách. Tahle šablona počítá
 * s nahráním souboru do WordPressu; odkaz ven je až náhradní řešení
 * a je u něj vidět, kam vede.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Typ obsahu a rubriky
 * ---------------------------------------------------------------------- */

/**
 * Zaregistruje typ obsahu „Dokumenty" a jejich rubriky.
 */
function csr_register_documents() {
	register_post_type(
		'csr_document',
		array(
			'labels'        => array(
				'name'          => 'Dokumenty',
				'singular_name' => 'Dokument',
				'add_new'       => 'Přidat dokument',
				'add_new_item'  => 'Přidat dokument',
				'edit_item'     => 'Upravit dokument',
				'search_items'  => 'Hledat dokument',
				'not_found'     => 'Zatím tu nejsou žádné dokumenty.',
				'menu_name'     => 'Dokumenty',
			),
			'public'        => false,
			'show_ui'       => true,
			'menu_icon'     => 'dashicons-media-document',
			'menu_position' => 28,
			'supports'      => array( 'title', 'page-attributes' ),
			'has_archive'   => false,
			'rewrite'       => false,
		)
	);

	register_taxonomy(
		'csr_doctype',
		'csr_document',
		array(
			'labels'            => array(
				'name'          => 'Rubriky dokumentů',
				'singular_name' => 'Rubrika',
				'add_new_item'  => 'Přidat rubriku',
			),
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => false,
		)
	);
}
add_action( 'init', 'csr_register_documents' );

/**
 * Rubriky v pořadí, v jakém se mají vypsat.
 *
 * @return array
 */
function csr_doctypes() {
	return array(
		'stanovy'     => 'Stanovy a řády',
		'registrace'  => 'Registrace',
		'souteze'     => 'Soutěže',
		'treneri'     => 'Trenéři a mládež',
		'reprezentace'=> 'Reprezentace',
		'etika'       => 'Etika',
		'pravidla-isu'=> 'Pravidla ISU',
		'antidoping'  => 'Antidoping',
	);
}

/**
 * Naplní seznam rubrik.
 */
function csr_seed_doctypes() {
	foreach ( csr_doctypes() as $slug => $name ) {
		if ( ! term_exists( $slug, 'csr_doctype' ) ) {
			wp_insert_term( $name, 'csr_doctype', array( 'slug' => $slug ) );
		}
	}
}

/**
 * Domény, které jsou vydavatelem dokumentu, ne jen úložištěm.
 *
 * U nich je odkaz ven správné řešení — kopie na svazovém webu by zastarala
 * a závazné znění vydává tahle organizace, ne svaz.
 *
 * @return array
 */
function csr_official_hosts() {
	return apply_filters(
		'csr_official_hosts',
		array(
			'antidoping.cz' => 'Antidopingový výbor ČR',
			'wada-ama.org'  => 'WADA',
			'isu.org'       => 'ISU',
			'olympic.cz'    => 'Český olympijský výbor',
			'msmt.cz'       => 'MŠMT',
		)
	);
}

/**
 * Je odkaz na oficiální zdroj vydavatele?
 *
 * @param int    $post_id ID dokumentu.
 * @param string $url     Odkaz.
 * @return string Název vydavatele, nebo prázdno.
 */
function csr_doc_publisher( $post_id, $url ) {
	// Ruční volba u dokumentu má přednost před automatickým rozpoznáním.
	$manual = get_post_meta( $post_id, '_csr_doc_publisher', true );
	if ( $manual ) {
		return $manual;
	}
	if ( ! $url ) {
		return '';
	}

	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	foreach ( csr_official_hosts() as $needle => $name ) {
		if ( $host === $needle || substr( $host, -strlen( '.' . $needle ) ) === '.' . $needle ) {
			return $name;
		}
	}
	return '';
}

/* -------------------------------------------------------------------------
 * Formulář u dokumentu
 * ---------------------------------------------------------------------- */

/**
 * Přidá box se souborem.
 */
function csr_document_metabox() {
	add_meta_box( 'csr-document', 'Soubor', 'csr_document_metabox_render', 'csr_document', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'csr_document_metabox' );

/**
 * Načte výběr souboru z knihovny médií jen na stránce dokumentu.
 *
 * @param string $hook Aktuální obrazovka.
 */
function csr_document_admin_assets( $hook ) {
	global $post_type;
	if ( 'csr_document' !== $post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	wp_enqueue_media();
}
add_action( 'admin_enqueue_scripts', 'csr_document_admin_assets' );

/**
 * Vykreslí formulář.
 *
 * @param WP_Post $post Dokument.
 */
function csr_document_metabox_render( $post ) {
	wp_nonce_field( 'csr_document_save', 'csr_document_nonce' );

	$file_id = (int) get_post_meta( $post->ID, '_csr_doc_file', true );
	$url     = get_post_meta( $post->ID, '_csr_doc_url', true );
	$date    = get_post_meta( $post->ID, '_csr_doc_date', true );
	$note    = get_post_meta( $post->ID, '_csr_doc_note', true );
	?>
	<style>
		.csr-df p.desc { margin: .2rem 0 0; color: #666; font-size: 12px; }
		.csr-df label { display: block; font-weight: 600; margin-bottom: .2rem; }
		.csr-df input[type="text"], .csr-df input[type="url"] { width: 100%; }
		.csr-df__row { margin-bottom: 1.2rem; }
		.csr-df__file { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
		.csr-df__name { font-weight: 600; }
		.csr-df__warn { padding: .6rem .8rem; background: #fcf9e8; border-left: 4px solid #dba617; }
		.csr-df__ok { padding: .6rem .8rem; background: #edfaef; border-left: 4px solid #00a32a; }
	</style>

	<div class="csr-df">
		<div class="csr-df__row">
			<label>Soubor nahraný na tomto webu</label>
			<div class="csr-df__file">
				<button type="button" class="button" id="csr-doc-pick">Vybrat soubor</button>
				<button type="button" class="button-link" id="csr-doc-clear"<?php echo $file_id ? '' : ' style="display:none"'; ?>>Odebrat</button>
				<span class="csr-df__name" id="csr-doc-name"><?php echo $file_id ? esc_html( basename( get_attached_file( $file_id ) ) ) : ''; ?></span>
			</div>
			<input type="hidden" name="csr_doc_file" id="csr-doc-file" value="<?php echo esc_attr( $file_id ); ?>">
			<p class="desc">Doporučený způsob. Soubor pak patří svazu a nezmizí, když skončí cizí služba.</p>
		</div>

		<div class="csr-df__row">
			<label for="csr-doc-url">Nebo odkaz na cizí web</label>
			<input type="url" id="csr-doc-url" name="csr_doc_url" value="<?php echo esc_attr( $url ); ?>" placeholder="https://…">
			<p class="desc">Použije se jen tehdy, když není vybraný soubor výše. U návštěvníka bude vidět, kam odkaz vede.</p>
		</div>

		<?php
		$csr_pub = csr_doc_publisher( $post->ID, $url );
		if ( ! $file_id && $url && ! $csr_pub ) :
			?>
			<p class="csr-df__warn">
				Tenhle dokument leží na <strong><?php echo esc_html( wp_parse_url( $url, PHP_URL_HOST ) ); ?></strong>.
				Svaz nad ním nemá kontrolu — až služba skončí nebo odkaz vyprší, dokument z webu zmizí.
				Pokud ho vydává jiná organizace, vyplňte níž vydavatele a tohle upozornění zmizí.
			</p>
		<?php elseif ( ! $file_id && $csr_pub ) : ?>
			<p class="csr-df__ok">
				Odkaz vede na zdroj vydavatele (<strong><?php echo esc_html( $csr_pub ); ?></strong>).
				Tak je to správně — kopie na svazovém webu by časem zastarala.
			</p>
		<?php endif; ?>

		<div class="csr-df__row">
			<label for="csr-doc-publisher">Vydavatel dokumentu</label>
			<input type="text" id="csr-doc-publisher" name="csr_doc_publisher" value="<?php echo esc_attr( get_post_meta( $post->ID, '_csr_doc_publisher', true ) ); ?>" placeholder="Antidopingový výbor ČR">
			<p class="desc">Vyplňte u dokumentů, které nevydává svaz. Odkaz ven je pak správné
				řešení — kopie by zastarala — a nepočítá se mezi soubory k přesunutí.
				U známých domén (antidoping.cz, wada-ama.org, isu.org…) se doplní sám.</p>
		</div>

		<div class="csr-df__row">
			<label for="csr-doc-date">Platnost od</label>
			<input type="text" id="csr-doc-date" name="csr_doc_date" value="<?php echo esc_attr( $date ); ?>" placeholder="1. 6. 2026">
			<p class="desc">Nepovinné. Například datum valné hromady, která dokument schválila.</p>
		</div>

		<div class="csr-df__row">
			<label for="csr-doc-note">Poznámka</label>
			<input type="text" id="csr-doc-note" name="csr_doc_note" value="<?php echo esc_attr( $note ); ?>">
			<p class="desc">Nepovinný jednořádkový popis pod názvem.</p>
		</div>
	</div>

	<script>
	jQuery(function ($) {
		var frame;
		$('#csr-doc-pick').on('click', function (e) {
			e.preventDefault();
			if (frame) { frame.open(); return; }
			frame = wp.media({ title: 'Vyberte soubor dokumentu', button: { text: 'Použít soubor' }, multiple: false });
			frame.on('select', function () {
				var file = frame.state().get('selection').first().toJSON();
				$('#csr-doc-file').val(file.id);
				$('#csr-doc-name').text(file.filename);
				$('#csr-doc-clear').show();
			});
			frame.open();
		});
		$('#csr-doc-clear').on('click', function (e) {
			e.preventDefault();
			$('#csr-doc-file').val('');
			$('#csr-doc-name').text('');
			$(this).hide();
		});
	});
	</script>
	<?php
}

/**
 * Uloží soubor a údaje.
 *
 * @param int $post_id ID dokumentu.
 */
function csr_document_save( $post_id ) {
	if ( ! isset( $_POST['csr_document_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['csr_document_nonce'] ), 'csr_document_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['csr_doc_file'] ) ) {
		update_post_meta( $post_id, '_csr_doc_file', absint( $_POST['csr_doc_file'] ) );
	}
	if ( isset( $_POST['csr_doc_url'] ) ) {
		update_post_meta( $post_id, '_csr_doc_url', esc_url_raw( wp_unslash( $_POST['csr_doc_url'] ) ) );
	}
	foreach ( array( 'date', 'note', 'publisher' ) as $key ) {
		$name = 'csr_doc_' . $key;
		if ( isset( $_POST[ $name ] ) ) {
			update_post_meta( $post_id, '_csr_doc_' . $key, sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) );
		}
	}
}
add_action( 'save_post_csr_document', 'csr_document_save' );

/* -------------------------------------------------------------------------
 * Odkud dokument bere soubor
 * ---------------------------------------------------------------------- */

/**
 * Údaje o souboru dokumentu.
 *
 * @param int $post_id ID dokumentu.
 * @return array Klíče url, local, ext, size, host.
 */
function csr_document_file( $post_id ) {
	$file_id = (int) get_post_meta( $post_id, '_csr_doc_file', true );

	if ( $file_id ) {
		$path = get_attached_file( $file_id );
		$mime = get_post_mime_type( $file_id );
		$ext  = $path ? strtoupper( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';
		return array(
			'url'       => wp_get_attachment_url( $file_id ),
			'local'     => true,
			'ext'       => $ext ? $ext : strtoupper( (string) $mime ),
			'size'      => ( $path && file_exists( $path ) ) ? size_format( filesize( $path ) ) : '',
			'host'      => '',
			'publisher' => '',
		);
	}

	$url = get_post_meta( $post_id, '_csr_doc_url', true );
	if ( ! $url ) {
		return array( 'url' => '', 'local' => false, 'ext' => '', 'size' => '', 'host' => '', 'publisher' => '' );
	}

	// U cizího odkazu neznáme velikost ani typ — jen doménu.
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$ext  = pathinfo( $path, PATHINFO_EXTENSION );
	return array(
		'url'       => $url,
		'local'     => false,
		'ext'       => $ext ? strtoupper( $ext ) : '',
		'size'      => '',
		'host'      => (string) wp_parse_url( $url, PHP_URL_HOST ),
		'publisher' => csr_doc_publisher( $post_id, $url ),
	);
}

/* -------------------------------------------------------------------------
 * Přehled v administraci
 * ---------------------------------------------------------------------- */

/**
 * Sloupce v seznamu dokumentů.
 *
 * @param array $columns Původní sloupce.
 * @return array
 */
function csr_document_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['csr_file'] = 'Soubor';
		}
	}
	return $new;
}
add_filter( 'manage_csr_document_posts_columns', 'csr_document_columns' );

/**
 * Obsah sloupce se souborem. Cizí úložiště je vidět na první pohled.
 *
 * @param string $column  Klíč sloupce.
 * @param int    $post_id ID dokumentu.
 */
function csr_document_column( $column, $post_id ) {
	if ( 'csr_file' !== $column ) {
		return;
	}
	$file = csr_document_file( $post_id );

	if ( ! $file['url'] ) {
		echo '<span style="color:#b32d2e">chybí soubor i odkaz</span>';
		return;
	}
	if ( $file['local'] ) {
		printf(
			'<span style="color:#00694e">✓ na tomto webu</span><br><small>%s%s</small>',
			esc_html( $file['ext'] ),
			$file['size'] ? ' · ' . esc_html( $file['size'] ) : ''
		);
		return;
	}
	if ( $file['publisher'] ) {
		printf(
			'<span style="color:#00694e">↗ zdroj vydavatele</span><br><small>%s</small>',
			esc_html( $file['publisher'] )
		);
		return;
	}
	printf(
		'<span style="color:#996800">⚠ cizí úložiště</span><br><small>%s</small>',
		esc_html( $file['host'] )
	);
}
add_action( 'manage_csr_document_posts_custom_column', 'csr_document_column', 10, 2 );

/**
 * Upozorní v seznamu dokumentů, kolik jich visí mimo web.
 */
function csr_document_admin_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-csr_document' !== $screen->id ) {
		return;
	}

	$external = 0;
	foreach ( csr_get_documents() as $doc ) {
		$file = csr_document_file( $doc->ID );
		if ( $file['url'] && ! $file['local'] && ! $file['publisher'] ) {
			$external++;
		}
	}
	if ( ! $external ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p><strong>%d dokumentů svazu leží na cizích úložištích.</strong> Až služba skončí nebo odkaz vyprší, zmizí z webu. Nahrajte soubory do knihovny médií a vyberte je u dokumentu. (Dokumenty s vyplněným vydavatelem se nepočítají — u těch je odkaz ven správně.)</p></div>',
		(int) $external
	);
}
add_action( 'admin_notices', 'csr_document_admin_notice' );

/* -------------------------------------------------------------------------
 * Hromadný vklad
 * ---------------------------------------------------------------------- */

/**
 * Přidá stránku pro hromadné vložení dokumentů.
 */
function csr_documents_import_page() {
	add_submenu_page(
		'edit.php?post_type=csr_document',
		'Hromadné vložení dokumentů',
		'Hromadné vložení',
		'edit_posts',
		'csr-documents-import',
		'csr_documents_import_render'
	);
}
add_action( 'admin_menu', 'csr_documents_import_page' );

/**
 * Vykreslí a zpracuje hromadný vklad.
 */
function csr_documents_import_render() {
	$done = 0;
	$skip = array();

	if ( isset( $_POST['csr_documents_import_nonce'] )
		&& wp_verify_nonce( sanitize_key( $_POST['csr_documents_import_nonce'] ), 'csr_documents_import' )
		&& current_user_can( 'edit_posts' ) ) {

		csr_seed_doctypes();
		$raw   = isset( $_POST['csr_documents_data'] ) ? wp_unslash( $_POST['csr_documents_data'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput — po řádcích níž
		$lines = preg_split( '/\R/', $raw );

		foreach ( $lines as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( '' === $parts[0] || 0 === strpos( $parts[0], '#' ) ) {
				continue;
			}

			$exists = get_posts(
				array(
					'post_type'      => 'csr_document',
					'title'          => $parts[0],
					'posts_per_page' => 1,
					'post_status'    => 'any',
					'fields'         => 'ids',
				)
			);
			if ( $exists ) {
				$skip[] = $parts[0];
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'   => 'csr_document',
					'post_title'  => sanitize_text_field( $parts[0] ),
					'post_status' => 'publish',
				)
			);
			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, '_csr_doc_url', isset( $parts[1] ) ? esc_url_raw( $parts[1] ) : '' );
			update_post_meta( $post_id, '_csr_doc_date', isset( $parts[3] ) ? sanitize_text_field( $parts[3] ) : '' );

			$type = isset( $parts[2] ) ? sanitize_title( $parts[2] ) : '';
			if ( array_key_exists( $type, csr_doctypes() ) ) {
				wp_set_object_terms( $post_id, $type, 'csr_doctype' );
			}
			$done++;
		}
	}
	?>
	<div class="wrap">
		<h1>Hromadné vložení dokumentů</h1>

		<?php if ( $done ) : ?>
			<div class="notice notice-success"><p>Vloženo dokumentů: <strong><?php echo (int) $done; ?></strong>.</p></div>
		<?php endif; ?>
		<?php if ( $skip ) : ?>
			<div class="notice notice-warning"><p>Přeskočeno (dokument s tímto názvem už existuje): <?php echo esc_html( implode( ', ', $skip ) ); ?></p></div>
		<?php endif; ?>

		<p>Jeden dokument na řádek:</p>
		<p><code>název | odkaz | rubrika | platnost od</code></p>
		<p>Rubriky: <?php echo esc_html( implode( ', ', array_keys( csr_doctypes() ) ) ); ?>.
			Odkaz i další pole jsou nepovinné.</p>
		<p><strong>Tohle je jen první krok.</strong> Vložením se založí záznamy s odkazy ven.
			Pak u každého dokumentu stáhněte soubor, nahrajte ho do knihovny médií a vyberte jej —
			teprve tím se dostane pod kontrolu svazu.</p>

		<form method="post">
			<?php wp_nonce_field( 'csr_documents_import', 'csr_documents_import_nonce' ); ?>
			<textarea name="csr_documents_data" rows="14" style="width:100%;font-family:monospace" placeholder="Stanovy schválené na VH 1.6.2026|https://…|stanovy|1. 6. 2026"></textarea>
			<?php submit_button( 'Vložit dokumenty' ); ?>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Vykreslení
 * ---------------------------------------------------------------------- */

/**
 * Dokumenty, volitelně jen z jedné rubriky.
 *
 * @param string $type Zkratka rubriky, nebo prázdno pro všechny.
 * @return WP_Post[]
 */
function csr_get_documents( $type = '' ) {
	$args = array(
		'post_type'      => 'csr_document',
		'posts_per_page' => -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
	);
	if ( $type ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'csr_doctype',
				'field'    => 'slug',
				'terms'    => $type,
			),
		);
	}
	return get_posts( $args );
}

/**
 * Na kterou rubriku je stránka omezená.
 *
 * @param int $page_id ID stránky.
 * @return string
 */
function csr_docs_page_type( $page_id ) {
	$type = get_post_meta( $page_id, '_csr_docs_type', true );
	return $type ? $type : '';
}

/* -------------------------------------------------------------------------
 * Nastavení u stránky
 * ---------------------------------------------------------------------- */

/**
 * Box u stránky s výběrem rubriky.
 */
function csr_docs_page_metabox() {
	add_meta_box( 'csr_docs_settings', 'Dokumenty — co se má vypsat', 'csr_docs_page_metabox_render', 'page', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'csr_docs_page_metabox' );

/**
 * Vykreslí výběr rubriky.
 *
 * @param WP_Post $post Upravovaná stránka.
 */
function csr_docs_page_metabox_render( $post ) {
	wp_nonce_field( 'csr_docs_page_save', 'csr_docs_page_nonce' );

	if ( CSR_DOCS_TEMPLATE !== get_post_meta( $post->ID, '_wp_page_template', true ) ) {
		echo '<p class="description">Vyberte nahoře šablonu <strong>„ČSR — Dokumenty"</strong> a stránku uložte. Pak se tu objeví výběr rubriky.</p>';
		return;
	}

	$current = csr_docs_page_type( $post->ID );
	// Nabízíme všechny rubriky, které v administraci existují, ne jen výchozí.
	$terms = get_terms( array( 'taxonomy' => 'csr_doctype', 'hide_empty' => false ) );

	echo '<p><label for="csr_docs_type"><strong>Vypsat rubriku</strong></label>';
	echo '<select id="csr_docs_type" name="csr_docs_type" style="width:100%">';
	echo '<option value=""' . selected( $current, '', false ) . '>Všechny dokumenty</option>';
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $term->slug ),
				selected( $current, $term->slug, false ),
				esc_html( $term->name )
			);
		}
	}
	echo '</select></p>';
	echo '<p class="description">Díky tomu obslouží jedna šablona i stránky jako <em>Pravidla a předpisy ISU</em>, <em>Smlouvy</em> nebo <em>Archiv</em> — každá ukáže svou rubriku.</p>';
}

/**
 * Uloží výběr rubriky u stránky.
 *
 * @param int $post_id ID stránky.
 */
function csr_docs_page_save( $post_id ) {
	if ( ! isset( $_POST['csr_docs_page_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['csr_docs_page_nonce'] ), 'csr_docs_page_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['csr_docs_type'] ) ) {
		update_post_meta( $post_id, '_csr_docs_type', sanitize_title( wp_unslash( $_POST['csr_docs_type'] ) ) );
	}
}
add_action( 'save_post_page', 'csr_docs_page_save' );

/**
 * Jeden řádek se souborem.
 *
 * @param WP_Post $doc Dokument.
 */
function csr_render_document( $doc ) {
	$file = csr_document_file( $doc->ID );
	$date = get_post_meta( $doc->ID, '_csr_doc_date', true );
	$note = get_post_meta( $doc->ID, '_csr_doc_note', true );

	$terms = wp_get_object_terms( $doc->ID, 'csr_doctype', array( 'fields' => 'names' ) );
	$type  = ! is_wp_error( $terms ) && $terms ? $terms[0] : '';

	// Dokument bez souboru i bez odkazu nemá návštěvníkovi co nabídnout.
	if ( ! $file['url'] ) {
		return;
	}

	$haystack = implode( ' ', array_filter( array( $doc->post_title, $note, $type ) ) );
	?>
	<li class="csr-doc csr-reveal"
		data-csr-item
		data-csr-cat="<?php echo esc_attr( $type ? sanitize_title( $type ) : 'ostatni' ); ?>"
		data-csr-text="<?php echo esc_attr( $haystack ); ?>">

		<a class="csr-doc__link" href="<?php echo esc_url( $file['url'] ); ?>"
			<?php echo $file['local'] ? 'download' : 'target="_blank" rel="noopener noreferrer"'; ?>>

			<span class="csr-doc__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/></svg>
				<?php if ( $file['ext'] ) : ?>
					<span class="csr-doc__ext"><?php echo esc_html( $file['ext'] ); ?></span>
				<?php endif; ?>
			</span>

			<span class="csr-doc__body">
				<span class="csr-doc__title">
					<?php echo esc_html( $doc->post_title ); ?>
					<?php
					// Odznak s příponou je uvnitř aria-hidden ikony, odečítač by ho minul.
					$csr_said = array_filter( array( $file['ext'], $file['size'] ) );
					if ( $csr_said ) :
						?>
						<span class="screen-reader-text">(<?php echo esc_html( implode( ', ', $csr_said ) ); ?>)</span>
					<?php endif; ?>
				</span>
				<?php if ( $note ) : ?>
					<span class="csr-doc__note"><?php echo esc_html( $note ); ?></span>
				<?php endif; ?>
				<span class="csr-doc__meta">
					<?php if ( $type ) : ?>
						<span class="csr-doc__type"><?php echo esc_html( $type ); ?></span>
					<?php endif; ?>
					<?php if ( $date ) : ?>
						<span>Platí od <?php echo esc_html( $date ); ?></span>
					<?php endif; ?>
					<?php if ( $file['size'] ) : ?>
						<span><?php echo esc_html( $file['size'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! $file['local'] && $file['publisher'] ) : ?>
						<span class="csr-doc__source">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>
							<?php echo esc_html( $file['publisher'] ); ?>
						</span>
					<?php elseif ( ! $file['local'] && $file['host'] ) : ?>
						<span class="csr-doc__ext-host">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>
							<?php echo esc_html( $file['host'] ); ?>
						</span>
					<?php endif; ?>
				</span>
			</span>

			<span class="csr-doc__action" aria-hidden="true">
				<?php if ( $file['local'] ) : ?>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v12M7 11l5 5 5-5M4 20h16"/></svg>
				<?php else : ?>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
				<?php endif; ?>
			</span>
		</a>
	</li>
	<?php
}
