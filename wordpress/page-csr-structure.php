<?php
/**
 * Template Name: ČSR — Struktura svazu
 *
 * Vypisuje lidi z databáze místo 22 obrázků s prázdným popisem.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/csr-header' );

$csr_all = array();
foreach ( array_keys( csr_bodies() ) as $csr_slug ) {
	$csr_all[ $csr_slug ] = csr_get_people( $csr_slug );
}
$csr_total = array_sum( array_map( 'count', $csr_all ) );
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
		<?php if ( $csr_total ) : ?>
			<div class="csr-pagehead__meta">
				<span class="csr-chip csr-chip--solid"><?php echo (int) $csr_total; ?> lidí</span>
			</div>
		<?php endif; ?>
		<?php
		$csr_lead = csr_opt( 'csr_people_lead', '' );
		if ( $csr_lead ) :
			?>
			<p class="csr-pagehead__lead"><?php echo esc_html( $csr_lead ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php if ( ! $csr_total ) : ?>

	<section class="csr-section">
		<div class="csr-container">
			<div class="csr-cal__empty csr-reveal">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 19a6.5 6.5 0 0 1 13 0M16 5.5a3 3 0 0 1 0 5M18 19a6.6 6.6 0 0 0-2-4.7"/></svg>
				<h2>Zatím tu nikdo není</h2>
				<?php if ( current_user_can( 'edit_posts' ) ) : ?>
					<p><small>Lidi přidáte v administraci v sekci <em>Lidé</em>, všechny naráz přes
						<em>Lidé → Hromadné vložení</em>.</small></p>
				<?php endif; ?>
			</div>
		</div>
	</section>

<?php else : ?>

	<?php
	foreach ( csr_bodies() as $csr_slug => $csr_name ) :
		$csr_people = $csr_all[ $csr_slug ];
		if ( ! $csr_people ) {
			continue;
		}
		$csr_soft = 'kontrolni-komise' === $csr_slug ? ' csr-section--soft' : '';
		?>
		<section class="csr-section<?php echo esc_attr( $csr_soft ); ?>" aria-labelledby="organ-<?php echo esc_attr( $csr_slug ); ?>">
			<div class="csr-container">
				<div class="csr-sechead csr-reveal">
					<h2 class="csr-sechead__title" id="organ-<?php echo esc_attr( $csr_slug ); ?>">
						<?php echo esc_html( $csr_name ); ?>
					</h2>
				</div>

				<?php
				// Předsedy dělíme na dvě dráhy — když je dráha vyplněná.
				$csr_long  = 'predsedove' === $csr_slug ? csr_get_people( $csr_slug, 'dlouha' ) : array();
				$csr_short = 'predsedove' === $csr_slug ? csr_get_people( $csr_slug, 'kratka' ) : array();

				if ( $csr_long || $csr_short ) :
					foreach ( array( 'Dlouhá dráha' => $csr_long, 'Krátká dráha' => $csr_short ) as $csr_label => $csr_group ) :
						if ( ! $csr_group ) {
							continue;
						}
						?>
						<h3 class="csr-people__sub csr-reveal"><?php echo esc_html( $csr_label ); ?></h3>
						<div class="csr-people" data-csr-stagger="55">
							<?php
							foreach ( $csr_group as $csr_p ) {
								csr_render_person( $csr_p );
							}
							?>
						</div>
						<?php
					endforeach;

					// Kdo dráhu vyplněnou nemá, ať se neztratí.
					$csr_rest = array_filter(
						$csr_people,
						function ( $p ) {
							return ! get_post_meta( $p->ID, '_csr_person_track', true );
						}
					);
					if ( $csr_rest ) :
						?>
						<div class="csr-people" data-csr-stagger="55">
							<?php
							foreach ( $csr_rest as $csr_p ) {
								csr_render_person( $csr_p );
							}
							?>
						</div>
						<?php
					endif;
				else :
					?>
					<div class="csr-people" data-csr-stagger="55">
						<?php
						foreach ( $csr_people as $csr_p ) {
							csr_render_person( $csr_p );
						}
						?>
					</div>
					<?php
				endif;
				?>
			</div>
		</section>
		<?php
	endforeach;
	?>

<?php endif; ?>

<?php
// Volný text z editoru stránky.
if ( trim( wp_strip_all_tags( get_post_field( 'post_content', get_the_ID() ) ) ) ) :
	?>
	<section class="csr-section csr-section--tight">
		<div class="csr-container">
			<div class="csr-prose csr-reveal">
				<?php echo apply_filters( 'the_content', get_post_field( 'post_content', get_the_ID() ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
		</div>
	</section>
	<?php
endif;
?>

</main>

<?php get_template_part( 'template-parts/csr-footer' );
