<?php
/**
 * Template Name: ČSR — Text a fotky
 *
 * Pro stránky, které jsou hlavně čtení: Archiv, Smlouvy, historie.
 * Text a fotky se z obsahu vyberou a poskládají po našem — ať už je
 * stránka psaná blokovým editorem, nebo Elementorem.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/csr-header' );

$csr_id    = get_the_ID();
$csr_thumb = get_post_thumbnail_id( $csr_id );
$csr_back  = $csr_thumb ? wp_get_attachment_image_url( $csr_thumb, 'large' ) : '';
$csr_rodic = wp_get_post_parent_id( $csr_id );
?>

<main id="obsah">

<article class="csr-article csr-story">

	<!-- ══════════ ZÁHLAVÍ ══════════ -->
	<header class="csr-pagehead csr-pagehead--article<?php echo $csr_back ? ' csr-pagehead--photo' : ''; ?>">
		<?php if ( $csr_back ) : ?>
			<div class="csr-pagehead__backdrop" style="background-image:url(<?php echo esc_url( $csr_back ); ?>)" aria-hidden="true"></div>
		<?php endif; ?>
		<div class="csr-pagehead__glow" aria-hidden="true"></div>

		<div class="csr-container csr-pagehead__inner">
			<nav class="csr-crumbs" aria-label="Drobečková navigace">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Úvod</a>
				<span aria-hidden="true">/</span>
				<?php if ( $csr_rodic ) : ?>
					<a href="<?php echo esc_url( get_permalink( $csr_rodic ) ); ?>"><?php echo esc_html( get_the_title( $csr_rodic ) ); ?></a>
					<span aria-hidden="true">/</span>
				<?php endif; ?>
				<span><?php echo esc_html( get_the_title() ); ?></span>
			</nav>

			<h1 class="csr-pagehead__title csr-article__title"><?php the_title(); ?></h1>
		</div>
	</header>

	<!-- ══════════ TĚLO ══════════ -->
	<div class="csr-section csr-section--tight">
		<div class="csr-container csr-article__layout">
			<div class="csr-article__body">

				<?php if ( csr_page_locked() ) : ?>

					<?php csr_render_password_form(); ?>

				<?php else : ?>

					<?php
					while ( have_posts() ) :
						the_post();
						/*
						 * Obsah necháme projít the_content() (kvůli Elementoru)
						 * a teprve pak si z něj vybereme text a fotky. Nulou
						 * říkáme, že žádnou fotku vynechávat nemáme — velká
						 * se tu nikde neopakuje, v záhlaví je jen rozostřená.
						 */
						ob_start();
						the_content();
						csr_render_story( ob_get_clean(), 0 );
					endwhile;

					wp_link_pages(
						array(
							'before' => '<nav class="csr-pager" aria-label="Stránky">',
							'after'  => '</nav>',
						)
					);
					?>

				<?php endif; ?>

			</div>
		</div>
	</div>

</article>

</main>

<?php
get_template_part( 'template-parts/csr-footer' );
