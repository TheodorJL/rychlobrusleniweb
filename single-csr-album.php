<?php
/**
 * Detail alba — fotky ve své vlastní proporci, s vlastním lightboxem.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/csr-header' );

while ( have_posts() ) :
	the_post();

	$csr_ids    = csr_album_items( get_the_ID() );
	$csr_casti  = csr_album_split( $csr_ids );
	$csr_datum  = csr_album_date_label( get_the_ID() );
	$csr_misto  = (string) get_post_meta( get_the_ID(), '_csr_album_place', true );
	$csr_autor  = (string) get_post_meta( get_the_ID(), '_csr_album_author', true );
	$csr_zpet   = csr_opt( 'csr_gallery_page', 0 );
	?>

<main id="obsah">

<section class="csr-pagehead">
	<div class="csr-pagehead__glow" aria-hidden="true"></div>
	<div class="csr-container csr-pagehead__inner">
		<nav class="csr-crumbs" aria-label="Drobečková navigace">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Úvod</a>
			<span aria-hidden="true">/</span>
			<?php if ( $csr_zpet ) : ?>
				<a href="<?php echo esc_url( get_permalink( (int) $csr_zpet ) ); ?>">Galerie</a>
				<span aria-hidden="true">/</span>
			<?php endif; ?>
			<span><?php the_title(); ?></span>
		</nav>
		<h1 class="csr-pagehead__title"><?php the_title(); ?></h1>
		<div class="csr-pagehead__meta">
			<?php if ( $csr_casti['fotky'] ) : ?>
				<span class="csr-chip csr-chip--solid">
					<?php echo esc_html( csr_plural( count( $csr_casti['fotky'] ), 'fotka', 'fotky', 'fotek' ) ); ?>
				</span>
			<?php endif; ?>
			<?php if ( $csr_casti['videa'] ) : ?>
				<span class="csr-chip csr-chip--ss">
					<?php echo esc_html( csr_plural( count( $csr_casti['videa'] ), 'video', 'videa', 'videí' ) ); ?>
				</span>
			<?php endif; ?>
			<?php if ( $csr_datum ) : ?>
				<span class="csr-chip"><?php echo esc_html( $csr_datum ); ?></span>
			<?php endif; ?>
			<?php if ( $csr_misto ) : ?>
				<span class="csr-chip"><?php echo esc_html( $csr_misto ); ?></span>
			<?php endif; ?>
		</div>
		<?php if ( $csr_autor ) : ?>
			<p class="csr-pagehead__lead">Fotografie: <strong><?php echo esc_html( $csr_autor ); ?></strong></p>
		<?php endif; ?>
	</div>
</section>

<?php if ( trim( wp_strip_all_tags( get_the_content() ) ) ) : ?>
	<section class="csr-section csr-section--tight">
		<div class="csr-container">
			<div class="csr-album__text"><?php the_content(); ?></div>
		</div>
	</section>
<?php endif; ?>

<section class="csr-section">
	<div class="csr-container">
		<?php if ( ! $csr_ids ) : ?>
			<p class="csr-news__empty">V tomhle albu zatím nejsou žádné fotky.</p>
		<?php else : ?>
			<ul class="csr-shots" data-csr-gallery>
				<?php
				$csr_i = 0;
				foreach ( $csr_ids as $csr_id ) {
					csr_render_media( $csr_id, $csr_i );
					$csr_i++;
				}
				?>
			</ul>
		<?php endif; ?>

		<?php if ( $csr_zpet ) : ?>
			<p class="csr-album__back">
				<a class="csr-btn csr-btn--ghost" href="<?php echo esc_url( get_permalink( (int) $csr_zpet ) ); ?>">
					Zpět na všechna alba
				</a>
			</p>
		<?php endif; ?>
	</div>
</section>

</main>

	<?php
endwhile;

get_template_part( 'template-parts/csr-footer' );
