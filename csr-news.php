<?php
/**
 * Výpis novinek za sezónu.
 *
 * Nahrazuje widget „Posts" z Elementor Pro, který se na webu nevykresluje.
 * Články se berou přímo z WordPressu — buď podle data (sezóna běží od
 * 1. 7. do 30. 6.), nebo podle kategorie.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Odhadne počáteční rok sezóny z názvu stránky („2025-2026" → 2025).
 * Díky tomu stačí u stávajících stránek přepnout šablonu a je hotovo.
 *
 * @param string $title Název stránky.
 * @return int Rok, nebo 0 když se nic nenašlo.
 */
function csr_guess_season_year( $title ) {
	if ( preg_match( '/(20\d{2})\s*[-–—\/]\s*20\d{2}/u', $title, $m ) ) {
		return (int) $m[1];
	}
	if ( preg_match( '/\b(20\d{2})\b/', $title, $m ) ) {
		return (int) $m[1];
	}
	return 0;
}

/**
 * Hranice sezóny. Sezóna na ledě běží od července do června.
 *
 * @param int $year Počáteční rok.
 * @return array ['od' => 'YYYY-MM-DD', 'do' => 'YYYY-MM-DD']
 */
function csr_season_range( $year ) {
	return array(
		'od' => sprintf( '%d-07-01', $year ),
		'do' => sprintf( '%d-06-30', $year + 1 ),
	);
}

/**
 * Sestaví dotaz na články sezónní stránky.
 *
 * @param int $page_id ID stránky.
 * @param int $paged   Číslo stránky výpisu.
 * @return WP_Query
 */
function csr_season_query( $page_id, $paged = 1 ) {
	$mode = get_post_meta( $page_id, '_csr_news_mode', true );
	$mode = $mode ? $mode : 'season';

	$args = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => max( 1, (int) csr_opt( 'csr_season_per_page' ) ),
		'paged'          => max( 1, (int) $paged ),
	);

	if ( 'category' === $mode ) {
		$cat = (int) get_post_meta( $page_id, '_csr_news_cat', true );
		if ( $cat ) {
			$args['cat'] = $cat;
		}
	} elseif ( 'season' === $mode ) {
		$year = (int) get_post_meta( $page_id, '_csr_news_year', true );
		if ( ! $year ) {
			$year = csr_guess_season_year( get_the_title( $page_id ) );
		}
		if ( $year ) {
			$range = csr_season_range( $year );
			$args['date_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_date_query
				array(
					'after'     => $range['od'],
					'before'    => $range['do'],
					'inclusive' => true,
				),
			);
		}
	}

	return new WP_Query( $args );
}

/* =========================================================================
 * NASTAVENÍ STRÁNKY
 * ====================================================================== */

/**
 * Box s výběrem, co se má na stránce vypsat.
 */
function csr_news_page_metabox() {
	add_meta_box( 'csr_news_settings', 'Novinky — co se má vypsat', 'csr_news_page_metabox_render', 'page', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'csr_news_page_metabox' );

/**
 * Vykreslí nastavení sezónní stránky.
 *
 * @param WP_Post $post Upravovaná stránka.
 */
function csr_news_page_metabox_render( $post ) {
	wp_nonce_field( 'csr_news_page_save', 'csr_news_page_nonce' );

	if ( CSR_NEWS_TEMPLATE !== get_post_meta( $post->ID, '_wp_page_template', true ) ) {
		echo '<p class="description">Vyberte nahoře šablonu <strong>„ČSR — Novinky sezóny"</strong> a stránku uložte. Pak se tu objeví nastavení výpisu.</p>';
		return;
	}

	$mode  = get_post_meta( $post->ID, '_csr_news_mode', true );
	$mode  = $mode ? $mode : 'season';
	$year  = (int) get_post_meta( $post->ID, '_csr_news_year', true );
	$cat   = (int) get_post_meta( $post->ID, '_csr_news_cat', true );
	$intro = get_post_meta( $post->ID, '_csr_news_intro', true );

	$guess = csr_guess_season_year( $post->post_title );
	?>
	<p>
		<label for="csr_news_mode"><strong>Vybírat články</strong></label><br>
		<select name="csr_news_mode" id="csr_news_mode" style="width:100%">
			<option value="season" <?php selected( $mode, 'season' ); ?>>podle sezóny (data vydání)</option>
			<option value="category" <?php selected( $mode, 'category' ); ?>>podle kategorie</option>
			<option value="all" <?php selected( $mode, 'all' ); ?>>všechny články</option>
		</select>
	</p>
	<p>
		<label for="csr_news_year"><strong>Počáteční rok sezóny</strong></label><br>
		<input type="number" name="csr_news_year" id="csr_news_year" min="2000" max="2100" style="width:100%"
		       value="<?php echo esc_attr( $year ? $year : '' ); ?>"
		       placeholder="<?php echo esc_attr( $guess ? $guess : '2025' ); ?>">
		<span class="description">
			<?php if ( $guess ) : ?>
				Prázdné = odvodí se z názvu stránky (<strong><?php echo esc_html( $guess ); ?></strong>).
			<?php endif; ?>
			Sezóna běží od 1. 7. do 30. 6. následujícího roku.
		</span>
	</p>
	<p>
		<label for="csr_news_cat"><strong>Kategorie</strong></label><br>
		<?php
		wp_dropdown_categories(
			array(
				'name'             => 'csr_news_cat',
				'id'               => 'csr_news_cat',
				'selected'         => $cat,
				'show_option_none' => '— žádná —',
				'option_none_value' => 0,
				'hide_empty'       => false,
				'class'            => 'widefat',
			)
		);
		?>
		<span class="description">Použije se jen při výběru „podle kategorie".</span>
	</p>
	<p>
		<label for="csr_news_intro"><strong>Popisek pod nadpisem</strong></label><br>
		<textarea name="csr_news_intro" id="csr_news_intro" rows="3" style="width:100%"><?php echo esc_textarea( $intro ); ?></textarea>
	</p>
	<?php
}

/**
 * Uloží nastavení sezónní stránky.
 *
 * @param int $post_id ID stránky.
 */
function csr_news_page_save( $post_id ) {
	if ( ! isset( $_POST['csr_news_page_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['csr_news_page_nonce'] ) ), 'csr_news_page_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['csr_news_mode'] ) ) {
		$mode  = sanitize_key( wp_unslash( $_POST['csr_news_mode'] ) );
		$modes = array( 'season', 'category', 'all' );
		update_post_meta( $post_id, '_csr_news_mode', in_array( $mode, $modes, true ) ? $mode : 'season' );
	}
	if ( isset( $_POST['csr_news_year'] ) ) {
		update_post_meta( $post_id, '_csr_news_year', absint( $_POST['csr_news_year'] ) );
	}
	if ( isset( $_POST['csr_news_cat'] ) ) {
		update_post_meta( $post_id, '_csr_news_cat', absint( $_POST['csr_news_cat'] ) );
	}
	if ( isset( $_POST['csr_news_intro'] ) ) {
		update_post_meta( $post_id, '_csr_news_intro', sanitize_textarea_field( wp_unslash( $_POST['csr_news_intro'] ) ) );
	}
}
add_action( 'save_post_page', 'csr_news_page_save' );
