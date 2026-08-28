<?php
/**
 * Template Name: ČSR — Fotogalerie
 *
 * Přehled alb. Každé album má svou stránku, takže se na jednu stránku
 * nenačítá 133 fotek naráz jako dřív.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/csr-header' );

// Zamčenou stránku nevypisujeme — obsah bereme z databáze, ne z těla
// stránky, takže by ochrana heslem jinak neplatila.
if ( csr_page_locked() ) {
	csr_render_password_form();
	get_template_part( 'template-parts/csr-footer' );
	return;
}

$csr_only   = get_post_meta( get_the_ID(), '_csr_gallery_type', true );
$csr_alba   = csr_get_albums( (string) $csr_only );
$csr_rubriky = array();
foreach ( $csr_alba as $csr_a ) {
	foreach ( wp_get_post_terms( $csr_a->ID, 'csr_album_type' ) as $csr_t ) {
		$csr_rubriky[ $csr_t->slug ] = $csr_t->name;
	}
}
?>

<main id="obsah">

<section class="csr-pagehead">
	<div class="csr-pagehead__glow" aria-hidden="true"></div>
	<div class="csr-container csr-pagehead__inner">
		<nav class="csr-crumbs" aria-label="Drobečková navigace">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Úvod</a>
			<span aria-hidden="true">/</span>
			<span><?php the_title(); ?></span>
		</nav>
		<h1 class="csr-pagehead__title"><?php the_title(); ?></h1>
		<div class="csr-pagehead__meta">
			<span class="csr-chip csr-chip--solid">
				<?php echo esc_html( csr_plural( count( $csr_alba ), 'album', 'alba', 'alb' ) ); ?>
			</span>
		</div>
		<?php if ( csr_opt( 'csr_gallery_lead', '' ) ) : ?>
			<p class="csr-pagehead__lead"><?php echo esc_html( csr_opt( 'csr_gallery_lead', '' ) ); ?></p>
		<?php endif; ?>
	</div>
</section>

<section class="csr-section">
	<div class="csr-container">

		<?php if ( ! $csr_alba ) : ?>
			<p class="csr-news__empty">Zatím tu není žádné album.</p>
		<?php else : ?>

			<?php if ( ! $csr_only && count( $csr_rubriky ) > 1 ) : ?>
				<div class="csr-calbar csr-reveal">
					<div class="csr-filters" data-csr-albumfilters role="group" aria-label="Filtrovat podle rubriky">
						<button class="csr-filter" type="button" data-csr-filter="all" aria-pressed="true">Vše</button>
						<?php foreach ( $csr_rubriky as $csr_slug => $csr_nazev ) : ?>
							<button class="csr-filter" type="button" data-csr-filter="<?php echo esc_attr( $csr_slug ); ?>" aria-pressed="false">
								<?php echo esc_html( $csr_nazev ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<ul class="csr-albums" data-csr-albums data-csr-stagger="55">
				<?php
				foreach ( $csr_alba as $csr_album ) :
					$csr_ids    = csr_album_items( $csr_album->ID );
					$csr_casti  = csr_album_split( $csr_ids );
					$csr_obal   = csr_album_cover( $csr_album->ID );
					$csr_datum  = csr_album_date_label( $csr_album->ID );
					$csr_misto  = (string) get_post_meta( $csr_album->ID, '_csr_album_place', true );
					$csr_slugy  = wp_list_pluck( wp_get_post_terms( $csr_album->ID, 'csr_album_type' ), 'slug' );

					// Prázdné album ukážeme jen správci, ať to může spravit.
					if ( ! $csr_ids && ! current_user_can( 'edit_posts' ) ) {
						continue;
					}
					?>
					<li class="csr-album csr-reveal<?php echo $csr_ids ? '' : ' csr-album--empty'; ?>"
						data-csr-item
						data-csr-cat="<?php echo esc_attr( implode( ' ', $csr_slugy ) ); ?>">
						<a class="csr-album__link" href="<?php echo esc_url( get_permalink( $csr_album->ID ) ); ?>">
							<span class="csr-album__cover">
								<?php if ( $csr_obal ) : ?>
									<?php
									echo wp_get_attachment_image(
										$csr_obal,
										'medium_large',
										false,
										array( 'alt' => '', 'loading' => 'lazy', 'class' => 'csr-album__img' )
									);
									?>
								<?php else : ?>
									<span class="csr-album__blank" aria-hidden="true"></span>
								<?php endif; ?>
								<?php if ( $csr_casti['fotky'] || $csr_casti['videa'] ) : ?>
									<span class="csr-album__count">
										<?php if ( $csr_casti['fotky'] ) : ?>
											<?php echo (int) count( $csr_casti['fotky'] ); ?>&nbsp;foto
										<?php endif; ?>
										<?php if ( $csr_casti['videa'] ) : ?>
											<?php echo (int) count( $csr_casti['videa'] ); ?>&nbsp;video
										<?php endif; ?>
									</span>
								<?php endif; ?>
							</span>
							<span class="csr-album__body">
								<span class="csr-album__title"><?php echo esc_html( get_the_title( $csr_album->ID ) ); ?></span>
								<span class="csr-album__meta">
									<?php if ( $csr_datum ) : ?>
										<span><?php echo esc_html( $csr_datum ); ?></span>
									<?php endif; ?>
									<?php if ( $csr_misto ) : ?>
										<span><?php echo esc_html( $csr_misto ); ?></span>
									<?php endif; ?>
								</span>
								<?php if ( ! $csr_ids ) : ?>
									<span class="csr-album__warn">Vidíte jen vy jako správce: album nemá žádné fotky.</span>
								<?php endif; ?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="csr-news__empty" data-csr-albumempty hidden>V téhle rubrice zatím žádné album není.</p>

		<?php endif; ?>

	</div>
</section>

</main>

<?php get_template_part( 'template-parts/csr-footer' );
