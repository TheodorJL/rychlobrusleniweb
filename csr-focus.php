<?php
/**
 * Výřez náhledu — která část fotky zůstane vidět po ořezu.
 *
 * Karty článků, fotky reprezentantů i akce ořezávají náhledový obrázek do
 * pevného poměru stran. Fotky svazu mají ale formáty od úzkých na výšku po
 * panoramata a ořez vždycky podle středu z nich často nechal jen kus ledu.
 * U příspěvku se proto dá klepnutím určit bod, který musí zůstat vidět;
 * šablona ho pak předá jako object-position.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Typy obsahu, u kterých jde výřez nastavit.
 *
 * @return string[]
 */
function csr_focus_post_types() {
	return (array) apply_filters(
		'csr_focus_post_types',
		array( 'post', CSR_CPT_ATHLETE, 'csr_person', CSR_CPT_FEED, 'tribe_events' )
	);
}

/**
 * Převede zápis „x y" na dvojici čísel v rozsahu 0–100.
 *
 * @param string $raw Uložená nebo odeslaná hodnota.
 * @return float[]|null Null, když hodnota nedává smysl.
 */
function csr_focus_parse( $raw ) {
	if ( ! preg_match( '/^\s*(\d{1,3}(?:\.\d+)?)\s+(\d{1,3}(?:\.\d+)?)\s*$/', (string) $raw, $m ) ) {
		return null;
	}
	return array(
		round( min( 100, (float) $m[1] ), 1 ),
		round( min( 100, (float) $m[2] ), 1 ),
	);
}

/**
 * Očistí hodnotu před uložením. Střed se neukládá — je to výchozí stav.
 *
 * @param string $raw Hodnota z formuláře nebo z editoru.
 * @return string
 */
function csr_focus_sanitize( $raw ) {
	$bod = csr_focus_parse( $raw );
	if ( ! $bod || ( 50.0 === $bod[0] && 50.0 === $bod[1] ) ) {
		return '';
	}
	return $bod[0] . ' ' . $bod[1];
}

/**
 * Výřez jako hodnota pro object-position / background-position.
 *
 * @param int $post_id ID příspěvku.
 * @return string Třeba „30% 20%", nebo prázdno, když není nastavený.
 */
function csr_focus_css( $post_id ) {
	$bod = csr_focus_parse( get_post_meta( $post_id, '_csr_focus', true ) );
	return $bod ? $bod[0] . '% ' . $bod[1] . '%' : '';
}

/**
 * Zaregistruje metadata, ať je blokový editor umí uložit.
 */
function csr_focus_register_meta() {
	foreach ( csr_focus_post_types() as $typ ) {
		register_post_meta(
			$typ,
			'_csr_focus',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'csr_focus_sanitize',
				'auth_callback'     => 'csr_focus_can_edit',
			)
		);
	}
}
add_action( 'init', 'csr_focus_register_meta', 20 );

/**
 * Smí uživatel výřez měnit?
 *
 * @param bool   $allowed  Výchozí rozhodnutí.
 * @param string $meta_key Klíč.
 * @param int    $post_id  ID příspěvku.
 * @return bool
 */
function csr_focus_can_edit( $allowed, $meta_key, $post_id ) {
	return current_user_can( 'edit_post', (int) $post_id );
}

/**
 * Přidá box do postranního panelu editoru.
 */
function csr_focus_metabox() {
	foreach ( csr_focus_post_types() as $typ ) {
		if ( post_type_exists( $typ ) && post_type_supports( $typ, 'thumbnail' ) ) {
			add_meta_box( 'csr-focus', 'Výřez náhledu', 'csr_focus_metabox_render', $typ, 'side', 'low' );
		}
	}
}
add_action( 'add_meta_boxes', 'csr_focus_metabox' );

/**
 * Vykreslí box s fotkou, bodem a ukázkami ořezu.
 *
 * @param WP_Post $post Příspěvek.
 */
function csr_focus_metabox_render( $post ) {
	wp_nonce_field( 'csr_focus_save', 'csr_focus_nonce' );

	$bod   = csr_focus_parse( get_post_meta( $post->ID, '_csr_focus', true ) );
	$x     = $bod ? $bod[0] : 50;
	$y     = $bod ? $bod[1] : 50;
	$thumb = (int) get_post_thumbnail_id( $post->ID );
	$src   = $thumb ? (string) wp_get_attachment_image_url( $thumb, 'large' ) : '';
	$pozice = $x . '% ' . $y . '%';

	// Poměry, do kterých šablona fotky opravdu ořezává.
	$pomery = array(
		'16 / 10' => 'Karta článku',
		'16 / 9'  => 'Hlavní článek',
		'1 / 1'   => 'Čtverec',
		'3 / 4'   => 'Na výšku',
	);
	?>
	<style>
		.csr-focus__stage{position:relative;cursor:crosshair;touch-action:none;user-select:none;line-height:0;border-radius:6px;overflow:hidden;background:#f0f3f6}
		.csr-focus__img{display:block;width:100%;height:auto;pointer-events:none}
		.csr-focus__dot{position:absolute;width:22px;height:22px;margin:-11px 0 0 -11px;border-radius:50%;border:2px solid #fff;background:rgba(0,69,123,.55);box-shadow:0 0 0 2px #00457b,0 2px 8px rgba(0,0,0,.4);outline:none}
		.csr-focus__dot:focus-visible{box-shadow:0 0 0 2px #00457b,0 0 0 5px rgba(56,182,240,.7)}
		.csr-focus__previews{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}
		.csr-focus__preview{margin:0}
		.csr-focus__preview img{display:block;width:100%;object-fit:cover;border-radius:4px;background:#f0f3f6}
		.csr-focus__preview figcaption{font-size:11px;color:#646970;margin-top:2px}
		.csr-focus__reset{margin-top:8px}
	</style>
	<div class="csr-focus" data-csr-focus>
		<p class="csr-focus__empty"<?php echo $src ? ' hidden' : ''; ?>>Nejdřív nastavte náhledový obrázek. Pak tu klepnete na místo, které musí zůstat vidět.</p>

		<div class="csr-focus__stage"<?php echo $src ? '' : ' hidden'; ?>>
			<img class="csr-focus__img" src="<?php echo esc_url( $src ); ?>" alt="">
			<span class="csr-focus__dot" tabindex="0" role="slider" aria-label="Místo, které zůstane vidět"
				style="left:<?php echo esc_attr( $x ); ?>%;top:<?php echo esc_attr( $y ); ?>%"></span>
		</div>

		<p class="description csr-focus__hint"<?php echo $src ? '' : ' hidden'; ?>>Klepněte nebo přetáhněte bod na to podstatné — obličej, cílovou pásku, medaili. Při ořezu zůstane tohle místo vidět. Doladit jde i šipkami.</p>

		<div class="csr-focus__previews"<?php echo $src ? '' : ' hidden'; ?>>
			<?php foreach ( $pomery as $pomer => $popis ) : ?>
				<figure class="csr-focus__preview">
					<img src="<?php echo esc_url( $src ); ?>" alt="" style="aspect-ratio:<?php echo esc_attr( $pomer ); ?>;object-position:<?php echo esc_attr( $pozice ); ?>">
					<figcaption><?php echo esc_html( $popis ); ?></figcaption>
				</figure>
			<?php endforeach; ?>
		</div>

		<input type="hidden" name="csr_focus" value="<?php echo esc_attr( $bod ? $bod[0] . ' ' . $bod[1] : '' ); ?>">
		<button type="button" class="button-link csr-focus__reset"<?php echo $bod ? '' : ' hidden'; ?>>Vrátit na střed</button>
	</div>
	<?php
}

/**
 * Uloží výřez z formuláře (klasický editor a ukládání metaboxů).
 *
 * @param int $post_id ID příspěvku.
 */
function csr_focus_save( $post_id ) {
	if ( ! isset( $_POST['csr_focus_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['csr_focus_nonce'] ), 'csr_focus_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$hodnota = csr_focus_sanitize( isset( $_POST['csr_focus'] ) ? sanitize_text_field( wp_unslash( $_POST['csr_focus'] ) ) : '' );
	if ( '' === $hodnota ) {
		delete_post_meta( $post_id, '_csr_focus' );
	} else {
		update_post_meta( $post_id, '_csr_focus', $hodnota );
	}
}
add_action( 'save_post', 'csr_focus_save' );

/**
 * Načte skript boxu jen v editoru příspěvků, kde box je.
 *
 * @param string $hook Stránka administrace.
 */
function csr_focus_admin_assets( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, csr_focus_post_types(), true ) ) {
		return;
	}

	$cesta = get_stylesheet_directory() . '/assets/js/csr-focus.js';
	wp_enqueue_script(
		'csr-focus',
		get_stylesheet_directory_uri() . '/assets/js/csr-focus.js',
		array(),
		file_exists( $cesta ) ? (string) filemtime( $cesta ) : null,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'csr_focus_admin_assets' );
