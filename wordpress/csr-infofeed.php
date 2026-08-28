<?php
/**
 * InfoFeed — proud krátkých oznámení s odkazy na dokumenty.
 *
 * Nahrazuje ruční skládání Elementor widgetů. Položka se zadá jednou:
 * nadpis, zdroj (štítek), odkaz na dokument, volitelně druhý odkaz a obrázek.
 *
 * Přidává do administrace:
 *   InfoFeed                    — seznam a přidávání položek
 *   InfoFeed → Zdroje           — štítky ČSR, ISU, ADV, NSA…
 *   InfoFeed → Hromadné přidání — vložení více položek najednou
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CSR_CPT_FEED   = 'csr_infofeed';
const CSR_TAX_SOURCE = 'csr_source';

/* =========================================================================
 * 1. TYP OBSAHU
 * ====================================================================== */

/**
 * Zaregistruje typ obsahu InfoFeed a taxonomii zdrojů.
 */
function csr_register_infofeed() {
	register_post_type(
		CSR_CPT_FEED,
		array(
			'labels' => array(
				'name'          => 'InfoFeed',
				'singular_name' => 'Položka InfoFeedu',
				'add_new'       => 'Přidat položku',
				'add_new_item'  => 'Přidat položku InfoFeedu',
				'edit_item'     => 'Upravit položku',
				'search_items'  => 'Hledat v InfoFeedu',
				'not_found'     => 'Zatím tu nic není.',
				'all_items'     => 'Všechny položky',
				'menu_name'     => 'InfoFeed',
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'menu_position'       => 22,
			'menu_icon'           => 'dashicons-megaphone',
			'supports'            => array( 'title', 'editor', 'thumbnail' ),
		)
	);

	register_taxonomy(
		CSR_TAX_SOURCE,
		CSR_CPT_FEED,
		array(
			'labels' => array(
				'name'          => 'Zdroje',
				'singular_name' => 'Zdroj',
				'add_new_item'  => 'Přidat zdroj',
				'menu_name'     => 'Zdroje',
			),
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => false,
		)
	);
}
add_action( 'init', 'csr_register_infofeed' );

/**
 * Při první aktivaci založí štítky, které web reálně používá.
 */
function csr_seed_sources() {
	if ( get_option( 'csr_sources_seeded' ) ) {
		return;
	}
	if ( ! taxonomy_exists( CSR_TAX_SOURCE ) ) {
		return;
	}

	foreach ( array( 'ČSR', 'ISU', 'ADV', 'NSA', 'Short track', 'Speed skating', 'Reprezentace', 'Svaz' ) as $name ) {
		if ( ! term_exists( $name, CSR_TAX_SOURCE ) ) {
			wp_insert_term( $name, CSR_TAX_SOURCE );
		}
	}

	update_option( 'csr_sources_seeded', 1 );
}
add_action( 'init', 'csr_seed_sources', 20 );

/* =========================================================================
 * 2. POLE POLOŽKY
 * ====================================================================== */

/**
 * Box s odkazy a ikonou.
 */
function csr_feed_metabox() {
	add_meta_box( 'csr_feed_details', 'Odkazy a vzhled', 'csr_feed_metabox_render', CSR_CPT_FEED, 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'csr_feed_metabox' );

/**
 * Vykreslí pole položky InfoFeedu.
 *
 * @param WP_Post $post Upravovaná položka.
 */
function csr_feed_metabox_render( $post ) {
	wp_nonce_field( 'csr_feed_save', 'csr_feed_nonce' );

	$l1_url   = get_post_meta( $post->ID, '_csr_link1_url', true );
	$l1_label = get_post_meta( $post->ID, '_csr_link1_label', true );
	$l2_url   = get_post_meta( $post->ID, '_csr_link2_url', true );
	$l2_label = get_post_meta( $post->ID, '_csr_link2_label', true );
	$icon     = get_post_meta( $post->ID, '_csr_icon', true );
	$icon     = $icon ? $icon : 'dokument';
	?>
	<style>
		.csr-fields { display: grid; gap: 16px; max-width: 640px; }
		.csr-fields label { display: block; font-weight: 600; margin-bottom: 4px; }
		.csr-fields input[type="text"], .csr-fields input[type="url"], .csr-fields select { width: 100%; }
		.csr-fields .csr-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
	</style>
	<div class="csr-fields">
		<div class="csr-pair">
			<div>
				<label for="csr_link1_url">Odkaz na dokument</label>
				<input type="url" id="csr_link1_url" name="csr_link1_url" value="<?php echo esc_attr( $l1_url ); ?>" placeholder="https://…">
			</div>
			<div>
				<label for="csr_link1_label">Text odkazu</label>
				<input type="text" id="csr_link1_label" name="csr_link1_label" value="<?php echo esc_attr( $l1_label ); ?>" placeholder="Dokument naleznete zde">
			</div>
		</div>

		<div class="csr-pair">
			<div>
				<label for="csr_link2_url">Druhý odkaz (nepovinné)</label>
				<input type="url" id="csr_link2_url" name="csr_link2_url" value="<?php echo esc_attr( $l2_url ); ?>" placeholder="https://…">
			</div>
			<div>
				<label for="csr_link2_label">Text druhého odkazu</label>
				<input type="text" id="csr_link2_label" name="csr_link2_label" value="<?php echo esc_attr( $l2_label ); ?>" placeholder="Soubor výsledků">
			</div>
		</div>

		<div>
			<label for="csr_icon">Ikona</label>
			<select id="csr_icon" name="csr_icon">
				<?php foreach ( csr_icon_choices() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $icon, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description">Použije se, jen když položka nemá náhledový obrázek.</p>
		</div>
	</div>

	<p class="description" style="margin-top:16px">
		<strong>Zdroj</strong> (štítek ČSR / ISU / ADV…) se vybírá vpravo v boxu <em>Zdroje</em>.
		Text v editoru nahoře je nepovinný — slouží jako krátký popis pod nadpisem.
	</p>
	<?php
}

/**
 * Uloží pole položky InfoFeedu.
 *
 * @param int $post_id ID položky.
 */
function csr_feed_save( $post_id ) {
	if ( ! isset( $_POST['csr_feed_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['csr_feed_nonce'] ) ), 'csr_feed_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( array( 'csr_link1_url', 'csr_link2_url' ) as $field ) {
		$value = isset( $_POST[ $field ] ) ? esc_url_raw( trim( wp_unslash( $_POST[ $field ] ) ) ) : '';
		update_post_meta( $post_id, '_' . $field, $value );
	}
	foreach ( array( 'csr_link1_label', 'csr_link2_label' ) as $field ) {
		$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		update_post_meta( $post_id, '_' . $field, $value );
	}

	$icon  = isset( $_POST['csr_icon'] ) ? sanitize_key( wp_unslash( $_POST['csr_icon'] ) ) : 'dokument';
	$icons = csr_icon_choices();
	update_post_meta( $post_id, '_csr_icon', isset( $icons[ $icon ] ) ? $icon : 'dokument' );
}
add_action( 'save_post_' . CSR_CPT_FEED, 'csr_feed_save' );

/* =========================================================================
 * 3. SEZNAM V ADMINISTRACI
 * ====================================================================== */

/**
 * Sloupce seznamu InfoFeedu.
 *
 * @param array $cols Původní sloupce.
 */
function csr_feed_columns( $cols ) {
	$new = array(
		'cb'         => isset( $cols['cb'] ) ? $cols['cb'] : '',
		'title'      => 'Nadpis',
		'csr_link'   => 'Odkaz',
	);
	foreach ( $cols as $key => $label ) {
		if ( ! isset( $new[ $key ] ) && 'date' !== $key ) {
			$new[ $key ] = $label;
		}
	}
	$new['date'] = isset( $cols['date'] ) ? $cols['date'] : 'Datum';
	return $new;
}
add_filter( 'manage_' . CSR_CPT_FEED . '_posts_columns', 'csr_feed_columns' );

/**
 * Obsah sloupce s odkazem — hlídá i prázdné odkazy.
 *
 * @param string $col     Klíč sloupce.
 * @param int    $post_id ID položky.
 */
function csr_feed_column_content( $col, $post_id ) {
	if ( 'csr_link' !== $col ) {
		return;
	}
	$url = get_post_meta( $post_id, '_csr_link1_url', true );
	if ( ! $url ) {
		echo '<span style="color:#b32d2e">chybí odkaz</span>';
		return;
	}
	$host = wp_parse_url( $url, PHP_URL_HOST );
	printf(
		'<a href="%s" target="_blank" rel="noopener">%s</a>',
		esc_url( $url ),
		esc_html( $host ? $host : $url )
	);
}
add_action( 'manage_' . CSR_CPT_FEED . '_posts_custom_column', 'csr_feed_column_content', 10, 2 );

/* =========================================================================
 * 4. HROMADNÉ PŘIDÁNÍ
 * ====================================================================== */

/**
 * Stránka pro vložení více položek najednou.
 */
function csr_feed_bulk_menu() {
	add_submenu_page(
		'edit.php?post_type=' . CSR_CPT_FEED,
		'Hromadné přidání',
		'Hromadné přidání',
		'edit_posts',
		'csr-feed-bulk',
		'csr_feed_bulk_render'
	);
}
add_action( 'admin_menu', 'csr_feed_bulk_menu' );

/**
 * Vykreslí a zpracuje hromadné vkládání položek.
 */
function csr_feed_bulk_render() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Nemáte oprávnění přidávat položky.' );
	}

	$added = array();

	if ( isset( $_POST['csr_feed_bulk_nonce'] )
		&& wp_verify_nonce( sanitize_key( wp_unslash( $_POST['csr_feed_bulk_nonce'] ) ), 'csr_feed_bulk' ) ) {

		$raw    = isset( $_POST['csr_items'] ) ? sanitize_textarea_field( wp_unslash( $_POST['csr_items'] ) ) : '';
		$source = isset( $_POST['csr_source_id'] ) ? absint( $_POST['csr_source_id'] ) : 0;

		foreach ( preg_split( '/\R/', $raw ) as $line ) {
			$line = trim( $line );
			// Poznámka za mřížkou se přeskočí.
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}

			$parts = preg_split( '/\s*\|\s*/', $line );
			$title = isset( $parts[0] ) ? trim( $parts[0] ) : '';
			if ( '' === $title ) {
				continue;
			}
			// URL může obsahovat zalomení řádku z kopírování — odstraníme bílé znaky.
			$url   = isset( $parts[1] ) ? preg_replace( '/\s+/', '', $parts[1] ) : '';
			$label = isset( $parts[2] ) ? trim( $parts[2] ) : '';

			$post_id = wp_insert_post(
				array(
					'post_type'   => CSR_CPT_FEED,
					'post_title'  => $title,
					'post_status' => 'publish',
				)
			);
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			update_post_meta( $post_id, '_csr_link1_url', esc_url_raw( $url ) );
			update_post_meta( $post_id, '_csr_link1_label', $label ? $label : 'Dokument naleznete zde' );
			update_post_meta( $post_id, '_csr_icon', 'dokument' );

			if ( $source ) {
				wp_set_object_terms( $post_id, array( $source ), CSR_TAX_SOURCE, true );
			}
			$added[] = $title;
		}
	}

	$sources = get_terms( array( 'taxonomy' => CSR_TAX_SOURCE, 'hide_empty' => false ) );
	?>
	<div class="wrap">
		<h1>Hromadné přidání do InfoFeedu</h1>

		<?php if ( $added ) : ?>
			<div class="notice notice-success"><p>
				Přidáno <strong><?php echo count( $added ); ?></strong> položek.
			</p></div>
		<?php endif; ?>

		<p>Každá položka na vlastní řádek ve formátu:</p>
		<p><code>Nadpis | odkaz | text odkazu</code></p>
		<p class="description">
			Text odkazu je nepovinný — bez něj se použije „Dokument naleznete zde".
			Fotku a druhý odkaz doplníte u položky dodatečně.
		</p>

		<form method="post">
			<?php wp_nonce_field( 'csr_feed_bulk', 'csr_feed_bulk_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="csr_source_id">Zdroj (štítek)</label></th>
					<td>
						<select name="csr_source_id" id="csr_source_id">
							<option value="0">— nezařazovat —</option>
							<?php foreach ( $sources as $term ) : ?>
								<option value="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="csr_items">Položky</label></th>
					<td>
						<textarea name="csr_items" id="csr_items" rows="14" class="large-text code"
							placeholder="Výsledky ze soutěže Přeborník Vysočiny | https://… | Dokument naleznete zde&#10;Dlouhá dráha – kvóty na ZOH 2026 | https://…"></textarea>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Přidat položky' ); ?>
		</form>
	</div>
	<?php
}

/* =========================================================================
 * 5. VYKRESLENÍ KARTY
 * ====================================================================== */

/**
 * Je odkaz mimo tento web?
 *
 * @param string $url Adresa.
 */
function csr_is_external( $url ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! $host ) {
		return false;
	}
	return wp_parse_url( home_url(), PHP_URL_HOST ) !== $host;
}

/**
 * Vykreslí jednu položku InfoFeedu.
 *
 * @param WP_Post $item Položka.
 */
function csr_render_feed_item( $item ) {
	$sources = get_the_terms( $item->ID, CSR_TAX_SOURCE );
	$badge   = ( $sources && ! is_wp_error( $sources ) ) ? $sources[0]->name : '';
	$slugs   = ( $sources && ! is_wp_error( $sources ) ) ? implode( ' ', wp_list_pluck( $sources, 'slug' ) ) : '';
	$thumb   = get_the_post_thumbnail( $item->ID, 'medium_large', array( 'loading' => 'lazy', 'alt' => '' ) );
	$text    = trim( wp_strip_all_tags( $item->post_content ) );

	$links = array();
	for ( $i = 1; $i <= 2; $i++ ) {
		$url = get_post_meta( $item->ID, "_csr_link{$i}_url", true );
		if ( ! $url ) {
			continue;
		}
		$links[] = array(
			'url'   => $url,
			'label' => get_post_meta( $item->ID, "_csr_link{$i}_label", true ) ?: 'Dokument naleznete zde',
			'ext'   => csr_is_external( $url ),
		);
	}
	?>
	<article class="csr-feeditem csr-reveal"
	         data-csr-source="<?php echo esc_attr( $slugs ); ?>"
	         data-csr-text="<?php echo esc_attr( mb_strtolower( get_the_title( $item ) . ' ' . $text . ' ' . $badge ) ); ?>">

		<div class="csr-feeditem__top">
			<?php if ( $thumb ) : ?>
				<?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php else : ?>
				<svg class="csr-feeditem__ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo csr_icon_paths( get_post_meta( $item->ID, '_csr_icon', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></svg>
			<?php endif; ?>

			<?php if ( $badge ) : ?>
				<span class="csr-chip csr-feeditem__badge"><?php echo esc_html( $badge ); ?></span>
			<?php endif; ?>
		</div>

		<div class="csr-feeditem__body">
			<time class="csr-feeditem__date" datetime="<?php echo esc_attr( get_the_date( 'c', $item ) ); ?>">
				<?php echo esc_html( get_the_date( 'j. n. Y', $item ) ); ?>
			</time>

			<h3 class="csr-feeditem__title"><?php echo esc_html( get_the_title( $item ) ); ?></h3>

			<?php if ( $text ) : ?>
				<p class="csr-feeditem__text"><?php echo esc_html( $text ); ?></p>
			<?php endif; ?>

			<?php if ( $links ) : ?>
				<div class="csr-feeditem__links">
					<?php foreach ( $links as $i => $link ) : ?>
						<a class="csr-feedlink<?php echo 0 === $i ? ' csr-feedlink--main' : ''; ?><?php echo $link['ext'] ? ' csr-feedlink--ext' : ''; ?>"
						   href="<?php echo esc_url( $link['url'] ); ?>"
						   <?php echo $link['ext'] ? 'target="_blank" rel="noopener"' : ''; ?>>
							<?php echo esc_html( $link['label'] ); ?>
							<?php if ( $link['ext'] ) : ?>
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-8 8M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>
							<?php else : ?>
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</article>
	<?php
}
