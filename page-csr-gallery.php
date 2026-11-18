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
				foreach ( $csr_alba as $csr_album ) {
					csr_render_album_card( $csr_album );
				}
				?>
			</ul>
			<p class="csr-news__empty" data-csr-albumempty hidden>V téhle rubrice zatím žádné album není.</p>

		<?php endif; ?>

	</div>
</section>

</main>

<?php get_template_part( 'template-parts/csr-footer' );
