<?php
/**
 * Detail článku.
 *
 * Nevybírá se u příspěvku ručně — nasazuje ji filtr v csr-article.php
 * podle přepínače v Customizeru, takže platí pro všechny články naráz.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/csr-header' );

while ( have_posts() ) :
	the_post();

	$csr_id    = get_the_ID();
	$csr_cats  = get_the_category();
	$csr_first = ! empty( $csr_cats ) ? $csr_cats[0] : null;
	$csr_thumb = get_post_thumbnail_id( $csr_id );
	$csr_meta  = $csr_thumb ? wp_get_attachment_metadata( $csr_thumb ) : array();

	// Fotky z mobilu na výšku se nesmí roztáhnout přes celou šířku.
	$csr_portrait = ! empty( $csr_meta['height'] ) && ! empty( $csr_meta['width'] )
		&& $csr_meta['height'] > $csr_meta['width'];
	$csr_backdrop = $csr_thumb ? wp_get_attachment_image_url( $csr_thumb, 'large' ) : '';
	?>

<main id="obsah">

<!-- ══════════ ZÁHLAVÍ ČLÁNKU ══════════ -->
<article class="csr-article">

	<header class="csr-pagehead csr-pagehead--article<?php echo $csr_backdrop ? ' csr-pagehead--photo' : ''; ?>">
		<?php if ( $csr_backdrop ) : ?>
			<div class="csr-pagehead__backdrop" style="background-image:url(<?php echo esc_url( $csr_backdrop ); ?>)" aria-hidden="true"></div>
		<?php endif; ?>
		<div class="csr-pagehead__glow" aria-hidden="true"></div>

		<div class="csr-container csr-pagehead__inner">
			<nav class="csr-crumbs" aria-label="Drobečková navigace">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Úvod</a>
				<span aria-hidden="true">/</span>
				<?php if ( $csr_first ) : ?>
					<a href="<?php echo esc_url( get_category_link( $csr_first->term_id ) ); ?>"><?php echo esc_html( $csr_first->name ); ?></a>
					<span aria-hidden="true">/</span>
				<?php endif; ?>
				<span><?php echo esc_html( wp_trim_words( get_the_title(), 6, '…' ) ); ?></span>
			</nav>

			<?php if ( $csr_first ) : ?>
				<span class="csr-chip <?php echo esc_attr( csr_chip_modifier( $csr_first->slug ) ); ?>">
					<?php echo esc_html( $csr_first->name ); ?>
				</span>
			<?php endif; ?>

			<h1 class="csr-pagehead__title csr-article__title"><?php the_title(); ?></h1>

			<div class="csr-article__meta">
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
					<?php echo esc_html( get_the_date() ); ?>
				</time>
				<span>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 2"/></svg>
					<?php echo esc_html( csr_reading_time( $csr_id ) ); ?> min čtení
				</span>
			</div>
		</div>
	</header>

	<!-- ══════════ TĚLO ČLÁNKU ══════════ -->
	<div class="csr-section csr-section--tight">
		<div class="csr-container csr-article__layout">

			<div class="csr-article__body">

				<?php if ( $csr_thumb ) : ?>
					<figure class="csr-figure<?php echo $csr_portrait ? ' csr-figure--portrait' : ''; ?> csr-reveal">
						<?php
						echo wp_get_attachment_image(
							$csr_thumb,
							'large',
							false,
							array(
								'class'    => 'csr-figure__img',
								'decoding' => 'async',
							)
						);
						?>
						<?php
						$csr_caption = wp_get_attachment_caption( $csr_thumb );
						if ( $csr_caption ) :
							?>
							<figcaption><?php echo esc_html( $csr_caption ); ?></figcaption>
						<?php endif; ?>
					</figure>
				<?php endif; ?>

				<?php
				/*
				 * Obsah si necháme vykreslit the_content() do bufferu — Elementor
				 * si na něj věší svoje vykreslování — a teprve pak si z něj
				 * vybereme text a fotky a poskládáme je po svém.
				 */
				ob_start();
				the_content();
				csr_render_story( ob_get_clean(), $csr_thumb );
				?>

				<?php
				wp_link_pages(
					array(
						'before' => '<nav class="csr-pager" aria-label="Stránky článku">',
						'after'  => '</nav>',
					)
				);
				?>

				<?php if ( csr_opt( 'csr_article_share', 1 ) ) : ?>
					<div class="csr-share csr-reveal">
						<span class="csr-share__label">Sdílet</span>
						<?php foreach ( csr_share_links( $csr_id ) as $csr_s ) : ?>
							<a class="csr-share__btn" href="<?php echo esc_url( $csr_s['url'] ); ?>"
								target="_blank" rel="noopener noreferrer"
								title="<?php echo esc_attr( $csr_s['label'] ); ?>">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $csr_s['icon']; // phpcs:ignore WordPress.Security.EscapeOutput — pevné SVG ?></svg>
								<span><?php echo esc_html( $csr_s['short'] ); ?></span>
							</a>
						<?php endforeach; ?>
						<button class="csr-share__btn csr-share__btn--copy" type="button"
							data-csr-copy="<?php echo esc_url( get_permalink( $csr_id ) ); ?>">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a4 4 0 0 0 5.7.4l3-3A4 4 0 0 0 13 5l-1.7 1.7"/><path d="M14 11a4 4 0 0 0-5.7-.4l-3 3A4 4 0 0 0 11 19l1.7-1.7"/></svg>
							<span>Kopírovat odkaz</span>
						</button>
					</div>
				<?php endif; ?>

			</div>

		</div>
	</div>

	<!-- ══════════ SOUSEDNÍ ČLÁNKY ══════════ -->
	<?php if ( get_adjacent_post( false, '', true ) || get_adjacent_post( false, '', false ) ) : ?>
		<div class="csr-container">
			<nav class="csr-adjacents csr-reveal" aria-label="Další články">
				<?php
				csr_render_adjacent( true );
				csr_render_adjacent( false );
				?>
			</nav>
		</div>
	<?php endif; ?>

</article>

<!-- ══════════ SOUVISEJÍCÍ ČLÁNKY ══════════ -->
<?php
$csr_related = csr_related_query( $csr_id, 3 );
if ( $csr_related->have_posts() ) :
	?>
	<section class="csr-section">
		<div class="csr-container">
			<div class="csr-sechead csr-reveal">
				<h2 class="csr-sechead__title">Mohlo by vás zajímat</h2>
			</div>
			<div class="csr-news csr-news--related" data-csr-stagger="70">
				<?php
				while ( $csr_related->have_posts() ) {
					$csr_related->the_post();
					csr_render_article_card( get_post(), false );
				}
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
	<?php
endif;

// Vrátíme se k původnímu příspěvku, komentáře patří jemu.
$csr_post = get_post( $csr_id );
setup_postdata( $csr_post );

if ( comments_open( $csr_id ) || get_comments_number( $csr_id ) ) :
	?>
	<section class="csr-section csr-section--tight">
		<div class="csr-container csr-article__layout">
			<div class="csr-article__body csr-comments">
				<?php comments_template(); ?>
			</div>
		</div>
	</section>
	<?php
endif;
?>

</main>

	<?php
endwhile;

get_template_part( 'template-parts/csr-footer' );
