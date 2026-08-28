<?php
/**
 * Template Name: ČSR — Kluby
 *
 * Výpis klubů z databáze. Přidání klubu = nový záznam v „Kluby",
 * ne klonování sloupce v Elementoru.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/csr-header' );

$csr_clubs = csr_get_clubs();

// Filtry stavíme jen z krajů, ve kterých opravdu nějaký klub je.
$csr_regions = array();
foreach ( $csr_clubs as $csr_c ) {
	foreach ( (array) wp_get_object_terms( $csr_c->ID, 'csr_region', array( 'fields' => 'names' ) ) as $csr_r ) {
		$csr_regions[ sanitize_title( $csr_r ) ] = $csr_r;
	}
}
asort( $csr_regions );
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
			<span class="csr-chip csr-chip--solid">
				<?php
				$csr_total = count( $csr_clubs );
				if ( 1 === $csr_total ) {
					echo '1 klub';
				} elseif ( $csr_total < 5 ) {
					echo esc_html( $csr_total ) . ' kluby';
				} else {
					echo esc_html( $csr_total ) . ' klubů';
				}
				?>
			</span>
			<?php if ( count( $csr_regions ) > 1 ) : ?>
				<span class="csr-chip csr-chip--ss"><?php echo count( $csr_regions ); ?> krajů</span>
			<?php endif; ?>
		</div>
		<?php
		$csr_lead = csr_opt( 'csr_clubs_lead', '' );
		if ( $csr_lead ) :
			?>
			<p class="csr-pagehead__lead"><?php echo esc_html( $csr_lead ); ?></p>
		<?php endif; ?>
	</div>
</section>

<!-- ══════════ VÝPIS KLUBŮ ══════════ -->
<section class="csr-section">
	<div class="csr-container">

		<?php if ( $csr_clubs ) : ?>

			<div class="csr-calbar csr-reveal">
				<label class="csr-clubsearch" data-csr-clubsearch>
					<span class="screen-reader-text">Hledat klub</span>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
					<input type="search" placeholder="Hledat klub, město nebo kontakt…" autocomplete="off">
				</label>

				<?php if ( count( $csr_regions ) > 1 ) : ?>
					<div class="csr-filters" data-csr-clubfilters role="group" aria-label="Filtrovat kluby podle kraje">
						<button class="csr-filter" type="button" data-csr-filter="all" aria-pressed="true">Vše</button>
						<?php foreach ( $csr_regions as $csr_slug => $csr_name ) : ?>
							<button class="csr-filter" type="button" data-csr-filter="<?php echo esc_attr( $csr_slug ); ?>" aria-pressed="false">
								<?php echo esc_html( $csr_name ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="csr-clubs" data-csr-clubs data-csr-stagger="60">
				<?php
				foreach ( $csr_clubs as $csr_c ) {
					csr_render_club_card( $csr_c );
				}
				?>
			</div>
			<p class="csr-news__empty" data-csr-empty hidden>Tomuhle hledání neodpovídá žádný klub.</p>

		<?php else : ?>

			<div class="csr-cal__empty csr-reveal">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 19a6.5 6.5 0 0 1 13 0M16 5.5a3 3 0 0 1 0 5M18 19a6.6 6.6 0 0 0-2-4.7"/></svg>
				<h2>Zatím tu nejsou žádné kluby</h2>
				<?php if ( current_user_can( 'edit_posts' ) ) : ?>
					<p><small>Kluby se přidávají v administraci v sekci <em>Kluby</em>. Všechny naráz jde vložit
						přes <em>Kluby → Hromadné vložení</em>.</small></p>
				<?php endif; ?>
			</div>

		<?php endif; ?>

		<?php
		// Volný text z editoru stránky se vypíše pod kluby.
		if ( trim( wp_strip_all_tags( get_post_field( 'post_content', get_the_ID() ) ) ) ) :
			?>
			<div class="csr-prose csr-reveal">
				<?php echo apply_filters( 'the_content', get_post_field( 'post_content', get_the_ID() ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
			<?php
		endif;
		?>

	</div>
</section>

</main>

<?php get_template_part( 'template-parts/csr-footer' );
