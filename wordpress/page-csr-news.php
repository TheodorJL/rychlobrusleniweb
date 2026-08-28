<?php
/**
 * Template Name: ČSR — Novinky sezóny
 *
 * Výpis článků za sezónu. Nahrazuje widget „Posts" z Elementor Pro,
 * který se na webu nevykresluje.
 *
 * Co se vypíše, se nastavuje vpravo u stránky v boxu „Novinky".
 * Bez nastavení se sezóna odvodí z názvu stránky („2025-2026" → 2025).
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/csr-header' );

$csr_page_id = get_the_ID();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended — jen stránkování výpisu
$csr_paged = isset( $_GET['strana'] ) ? max( 1, absint( $_GET['strana'] ) ) : 1;
$csr_query = csr_season_query( $csr_page_id, $csr_paged );
$csr_intro = get_post_meta( $csr_page_id, '_csr_news_intro', true );

$csr_mode = get_post_meta( $csr_page_id, '_csr_news_mode', true );
$csr_mode = $csr_mode ? $csr_mode : 'season';
$csr_year = (int) get_post_meta( $csr_page_id, '_csr_news_year', true );
if ( ! $csr_year ) {
	$csr_year = csr_guess_season_year( get_the_title() );
}

// Filtry sestavíme jen z kategorií, které se ve výpisu opravdu objevily.
$csr_used_cats = array();
foreach ( $csr_query->posts as $csr_p ) {
	foreach ( (array) get_the_category( $csr_p->ID ) as $csr_t ) {
		$csr_used_cats[ $csr_t->slug ] = $csr_t->name;
	}
}
?>

<main id="obsah">

<!-- ══════════ ZÁHLAVÍ ══════════ -->
<section class="csr-pagehead">
	<div class="csr-pagehead__glow" aria-hidden="true"></div>
	<div class="csr-container csr-pagehead__inner">

		<nav class="csr-crumbs" aria-label="Drobečková navigace">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Úvod</a>
			<span aria-hidden="true">/</span>
			<span>Novinky</span>
		</nav>

		<h1 class="csr-pagehead__title"><?php echo esc_html( get_the_title() ); ?></h1>

		<div class="csr-pagehead__meta">
			<?php if ( 'season' === $csr_mode && $csr_year ) : ?>
				<span class="csr-chip csr-chip--ss">
					Sezóna <?php echo esc_html( $csr_year . '–' . ( $csr_year + 1 ) ); ?>
				</span>
			<?php endif; ?>
			<?php if ( $csr_query->found_posts ) : ?>
				<span class="csr-chip csr-chip--solid">
					<?php
					$csr_total = (int) $csr_query->found_posts;
					if ( 1 === $csr_total ) {
						echo '1 článek';
					} elseif ( $csr_total < 5 ) {
						echo esc_html( $csr_total ) . ' články';
					} else {
						echo esc_html( $csr_total ) . ' článků';
					}
					?>
				</span>
			<?php endif; ?>
		</div>

		<?php if ( $csr_intro ) : ?>
			<p class="csr-pagehead__lead"><?php echo esc_html( $csr_intro ); ?></p>
		<?php endif; ?>

	</div>
</section>

<!-- ══════════ VÝPIS ČLÁNKŮ ══════════ -->
<section class="csr-section">
	<div class="csr-container">

		<?php if ( $csr_query->have_posts() ) : ?>

			<?php if ( count( $csr_used_cats ) > 1 ) : ?>
				<div class="csr-calbar csr-reveal">
					<div class="csr-filters" data-csr-filters role="group" aria-label="Filtrovat články podle kategorie">
						<button class="csr-filter" type="button" data-csr-filter="all" aria-pressed="true">Vše</button>
						<?php foreach ( $csr_used_cats as $csr_slug => $csr_name ) : ?>
							<button class="csr-filter" type="button" data-csr-filter="<?php echo esc_attr( $csr_slug ); ?>" aria-pressed="false">
								<?php echo esc_html( $csr_name ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="csr-news" data-csr-news data-csr-stagger="70">
				<?php
				$csr_i = 0;
				while ( $csr_query->have_posts() ) {
					$csr_query->the_post();
					// Velká úvodní karta jen na první stránce výpisu.
					csr_render_article_card( get_post(), 0 === $csr_i && 1 === $csr_paged );
					$csr_i++;
				}
				wp_reset_postdata();
				?>
				<p class="csr-news__empty" hidden>V této kategorii zatím nejsou žádné články.</p>
			</div>

			<?php if ( $csr_query->max_num_pages > 1 ) : ?>
				<nav class="csr-pager" aria-label="Stránkování článků">
					<?php
					echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput — paginate_links escapuje sám
						array(
							'base'      => add_query_arg( 'strana', '%#%', get_permalink() ),
							'format'    => '',
							'current'   => $csr_paged,
							'total'     => (int) $csr_query->max_num_pages,
							'mid_size'  => 1,
							'prev_text' => '‹ Novější',
							'next_text' => 'Starší ›',
							'type'      => 'plain',
						)
					);
					?>
				</nav>
			<?php endif; ?>

		<?php else : ?>

			<div class="csr-cal__empty csr-reveal">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>
				<?php if ( 'season' === $csr_mode && $csr_year ) : ?>
					<h2>Za sezónu <?php echo esc_html( $csr_year . '–' . ( $csr_year + 1 ) ); ?> tu zatím nic není</h2>
					<p>Vypisují se články vydané mezi 1. 7. <?php echo esc_html( $csr_year ); ?>
						a 30. 6. <?php echo esc_html( $csr_year + 1 ); ?>.</p>
				<?php else : ?>
					<h2>Zatím tu nejsou žádné články</h2>
				<?php endif; ?>
				<?php if ( current_user_can( 'edit_posts' ) ) : ?>
					<p><small>Výběr článků nastavíte vpravo u této stránky v boxu <em>Novinky</em>.</small></p>
				<?php endif; ?>
			</div>

		<?php endif; ?>

		<?php
		// Volný text napsaný do editoru stránky se vypíše pod výpisem.
		csr_page_prose( $csr_page_id );
		?>

	</div>
</section>

</main>

<?php get_template_part( 'template-parts/csr-footer' );
