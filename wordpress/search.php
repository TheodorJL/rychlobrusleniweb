<?php
/**
 * Výsledky hledání.
 *
 * Hledá se napříč vším: články, závody, dokumenty, kluby, alba
 * i reprezentanti. U každého výsledku je proto vidět, co to je —
 * bez toho je seznam nesrozumitelný.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/csr-header' );

$csr_dotaz = get_search_query();
$csr_pocet = (int) $GLOBALS['wp_query']->found_posts;
?>

<main id="obsah">

<section class="csr-pagehead">
	<div class="csr-pagehead__glow" aria-hidden="true"></div>
	<div class="csr-container csr-pagehead__inner">
		<nav class="csr-crumbs" aria-label="Drobečková navigace">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Úvod</a>
			<span aria-hidden="true">/</span>
			<span>Hledání</span>
		</nav>
		<h1 class="csr-pagehead__title">
			<?php if ( $csr_dotaz ) : ?>
				<?php echo esc_html( $csr_dotaz ); ?>
			<?php else : ?>
				Hledání
			<?php endif; ?>
		</h1>
		<?php if ( $csr_dotaz ) : ?>
			<div class="csr-pagehead__meta">
				<span class="csr-chip csr-chip--solid">
					<?php echo esc_html( csr_plural( $csr_pocet, 'výsledek', 'výsledky', 'výsledků' ) ); ?>
				</span>
			</div>
		<?php endif; ?>
		<?php
		$csr_lead = csr_opt( 'csr_search_lead', '' );
		if ( $csr_lead ) :
			?>
			<p class="csr-pagehead__lead"><?php echo esc_html( $csr_lead ); ?></p>
		<?php endif; ?>
	</div>
</section>

<section class="csr-section">
	<div class="csr-container">

		<form class="csr-searchbar csr-reveal" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="csr-clubsearch">
				<span class="screen-reader-text">Hledaný výraz</span>
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
				<input type="search" name="s" value="<?php echo esc_attr( $csr_dotaz ); ?>"
				       placeholder="Závody, výsledky, dokumenty, kluby…" autocomplete="off">
			</label>
			<button class="csr-btn csr-btn--primary csr-btn--sm" type="submit">Hledat</button>
		</form>

		<?php if ( have_posts() ) : ?>

			<ul class="csr-results" data-csr-stagger="40">
				<?php
				while ( have_posts() ) :
					the_post();
					$csr_druh  = csr_search_kind( get_post() );
					$csr_thumb = has_post_thumbnail() ? get_the_post_thumbnail( get_the_ID(), 'medium', array( 'alt' => '', 'loading' => 'lazy' ) ) : '';
					?>
					<li class="csr-result csr-reveal">
						<a class="csr-result__link" href="<?php the_permalink(); ?>">
							<?php if ( $csr_thumb ) : ?>
								<span class="csr-result__media"><?php echo $csr_thumb; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<?php endif; ?>
							<span class="csr-result__body">
								<span class="csr-result__meta">
									<?php if ( $csr_druh ) : ?>
										<span class="csr-chip csr-chip--org"><?php echo esc_html( $csr_druh ); ?></span>
									<?php endif; ?>
									<?php if ( 'page' !== get_post_type() ) : ?>
										<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j. n. Y' ) ); ?></time>
									<?php endif; ?>
								</span>
								<span class="csr-result__title"><?php the_title(); ?></span>
								<?php
								$csr_uryvek = trim( wp_strip_all_tags( get_the_excerpt() ) );
								if ( $csr_uryvek ) :
									?>
									<span class="csr-result__excerpt"><?php echo esc_html( wp_trim_words( $csr_uryvek, 26, '…' ) ); ?></span>
								<?php endif; ?>
							</span>
						</a>
					</li>
					<?php
				endwhile;
				?>
			</ul>

			<?php
			$csr_stranky = (int) $GLOBALS['wp_query']->max_num_pages;
			if ( $csr_stranky > 1 ) :
				?>
				<nav class="csr-pager" aria-label="Stránkování výsledků">
					<?php
					echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput — paginate_links escapuje sám
						array(
							'total'     => $csr_stranky,
							'current'   => max( 1, get_query_var( 'paged' ) ),
							'mid_size'  => 1,
							'prev_text' => '‹ Předchozí',
							'next_text' => 'Další ›',
							'type'      => 'plain',
						)
					);
					?>
				</nav>
			<?php endif; ?>

		<?php else : ?>

			<div class="csr-cal__empty csr-reveal">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
				<h2>Nic jsme nenašli</h2>
				<p>
					<?php if ( $csr_dotaz ) : ?>
						Výrazu <strong><?php echo esc_html( $csr_dotaz ); ?></strong> neodpovídá nic na webu.
						Zkuste kratší slovo nebo jméno bez diakritiky.
					<?php else : ?>
						Napište, co hledáte, do pole výš.
					<?php endif; ?>
				</p>
				<p>
					<a class="csr-btn csr-btn--ghost csr-btn--sm" href="<?php echo esc_url( home_url( '/' ) ); ?>">Zpátky na úvod</a>
				</p>
			</div>

		<?php endif; ?>

	</div>
</section>

</main>

<?php get_template_part( 'template-parts/csr-footer' );
