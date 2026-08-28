<?php
/**
 * Template Name: ČSR — InfoFeed
 *
 * Výpis oznámení s odkazy na dokumenty. Položky se zadávají v administraci
 * v sekci InfoFeed, štítky zdrojů v InfoFeed → Zdroje.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/csr-header' );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended — jen stránkování výpisu
$csr_paged = isset( $_GET['strana'] ) ? max( 1, absint( $_GET['strana'] ) ) : 1;

$csr_query = new WP_Query(
	array(
		'post_type'      => CSR_CPT_FEED,
		'post_status'    => 'publish',
		'posts_per_page' => max( 1, (int) csr_opt( 'csr_feed_per_page' ) ),
		'paged'          => $csr_paged,
	)
);

// Zdroje pro filtry — jen ty, které mají aspoň jednu položku.
$csr_sources = get_terms(
	array(
		'taxonomy'   => CSR_TAX_SOURCE,
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 8,
	)
);
?>

<main id="obsah">

<!-- ══════════ ZÁHLAVÍ ══════════ -->
<section class="csr-pagehead">
	<div class="csr-pagehead__glow" aria-hidden="true"></div>
	<div class="csr-container csr-pagehead__inner">

		<nav class="csr-crumbs" aria-label="Drobečková navigace">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Úvod</a>
			<span aria-hidden="true">/</span>
			<span><?php echo esc_html( get_the_title() ); ?></span>
		</nav>

		<h1 class="csr-pagehead__title"><?php echo esc_html( get_the_title() ); ?></h1>

		<div class="csr-pagehead__meta">
			<?php if ( $csr_query->found_posts ) : ?>
				<span class="csr-chip csr-chip--solid">
					<?php
					$csr_total = (int) $csr_query->found_posts;
					if ( 1 === $csr_total ) {
						echo '1 oznámení';
					} elseif ( $csr_total < 5 ) {
						echo esc_html( $csr_total ) . ' oznámení';
					} else {
						echo esc_html( $csr_total ) . ' oznámení';
					}
					?>
				</span>
			<?php endif; ?>
		</div>

		<?php if ( csr_opt( 'csr_feed_lead' ) ) : ?>
			<p class="csr-pagehead__lead"><?php echo esc_html( csr_opt( 'csr_feed_lead' ) ); ?></p>
		<?php endif; ?>

	</div>
</section>

<!-- ══════════ VÝPIS ══════════ -->
<section class="csr-section">
	<div class="csr-container">

		<?php if ( $csr_query->have_posts() ) : ?>

			<div class="csr-feedbar csr-reveal">
				<div class="csr-feedsearch" data-csr-feedsearch>
					<svg class="csr-feedsearch__ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
					<label class="csr-sr-only" for="csr-feed-q">Hledat v oznámeních</label>
					<input type="search" id="csr-feed-q" placeholder="Hledat v oznámeních…" autocomplete="off">
					<button class="csr-feedsearch__clear" type="button" aria-label="Vymazat hledání">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
					</button>
				</div>

				<?php if ( ! empty( $csr_sources ) ) : ?>
					<div class="csr-filters" data-csr-feedfilters role="group" aria-label="Filtrovat podle zdroje">
						<button class="csr-filter" type="button" data-csr-filter="all" aria-pressed="true">Vše</button>
						<?php foreach ( $csr_sources as $csr_src ) : ?>
							<button class="csr-filter" type="button" data-csr-filter="<?php echo esc_attr( $csr_src->slug ); ?>" aria-pressed="false">
								<?php echo esc_html( $csr_src->name ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="csr-feed" data-csr-feed data-csr-stagger="50">
				<?php
				while ( $csr_query->have_posts() ) {
					$csr_query->the_post();
					csr_render_feed_item( get_post() );
				}
				wp_reset_postdata();
				?>
				<p class="csr-feed__empty" hidden>Nic nenalezeno. Zkuste jiný výraz nebo zrušte filtr.</p>
			</div>

			<?php if ( $csr_query->max_num_pages > 1 ) : ?>
				<nav class="csr-pager" aria-label="Stránkování oznámení">
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
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h11l5 5v7a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Z"/><path d="M8 12h6M8 16h4"/></svg>
				<h2>InfoFeed je zatím prázdný</h2>
				<?php if ( current_user_can( 'edit_posts' ) ) : ?>
					<p>Oznámení přidáte v administraci v sekci <em>InfoFeed</em>. Pro převod
						stávajících položek použijte <em>InfoFeed → Hromadné přidání</em>.</p>
				<?php else : ?>
					<p>Zatím tu nejsou žádná oznámení.</p>
				<?php endif; ?>
			</div>

		<?php endif; ?>

	</div>
</section>

</main>

<?php get_template_part( 'template-parts/csr-footer' );
