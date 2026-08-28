<?php
/**
 * Template Name: ČSR — Výsledky
 *
 * Výsledkové tabulky jedné sezóny a disciplíny. Jedna šablona obslouží
 * všech dvanáct stránek z menu „Výsledky" — každá si v postranním boxu
 * vybere svou sezónu.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/csr-header' );

$csr_scope   = csr_results_page_scope( get_the_ID() );
$csr_tables  = csr_get_results( $csr_scope['season'], $csr_scope['sport'] );
$csr_sports  = csr_result_sports();
$csr_seasons = csr_results_other_pages( get_the_ID() );

// Kolik tabulek má vůbec co ukázat.
$csr_shown = 0;
foreach ( $csr_tables as $csr_t ) {
	$csr_parsed = csr_parse_table( get_post_meta( $csr_t->ID, '_csr_result_data', true ) );
	if ( $csr_parsed['rows'] || csr_result_file( $csr_t->ID )['url'] ) {
		$csr_shown++;
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
			<span>Výsledky</span>
			<span aria-hidden="true">/</span>
			<span><?php echo esc_html( get_the_title() ); ?></span>
		</nav>
		<h1 class="csr-pagehead__title"><?php echo esc_html( get_the_title() ); ?></h1>
		<div class="csr-pagehead__meta">
			<?php if ( $csr_scope['sport'] && isset( $csr_sports[ $csr_scope['sport'] ] ) ) : ?>
				<span class="csr-chip csr-chip--<?php echo 'st' === $csr_scope['sport'] ? 'st' : 'ss'; ?>">
					<?php echo esc_html( $csr_sports[ $csr_scope['sport'] ] ); ?>
				</span>
			<?php endif; ?>
			<?php if ( $csr_shown ) : ?>
				<span class="csr-chip csr-chip--solid">
					<?php echo esc_html( csr_plural( $csr_shown, 'tabulka', 'tabulky', 'tabulek' ) ); ?>
				</span>
			<?php endif; ?>
		</div>
		<?php
		$csr_lead = csr_opt( 'csr_results_lead', '' );
		if ( $csr_lead ) :
			?>
			<p class="csr-pagehead__lead"><?php echo esc_html( $csr_lead ); ?></p>
		<?php endif; ?>
	</div>
</section>

<!-- ══════════ TABULKY ══════════ -->
<section class="csr-section">
	<div class="csr-container">

		<?php if ( $csr_shown ) : ?>

			<?php
			foreach ( $csr_tables as $csr_t ) {
				csr_render_result( $csr_t );
			}
			?>

		<?php else : ?>

			<div class="csr-cal__empty csr-reveal">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h18v14H3z"/><path d="M3 10h18M9 10v9M15 10v9"/></svg>
				<h2>Výsledky zatím nejsou zveřejněné</h2>
				<?php if ( current_user_can( 'edit_posts' ) ) : ?>
					<p><small>Tabulky se přidávají v administraci v sekci <em>Výsledky</em>.
						Nezapomeňte u nich vybrat sezónu
						<?php if ( $csr_scope['season'] ) : ?>
							<strong><?php echo esc_html( $csr_scope['season'] ); ?></strong>
						<?php endif; ?>
						a disciplínu.</small></p>
				<?php endif; ?>
			</div>

		<?php endif; ?>

		<?php
		// Volný text stránky pod tabulkami — poznámky, odkazy na propozice.
		if ( trim( wp_strip_all_tags( get_post_field( 'post_content', get_the_ID() ) ) ) ) :
			?>
			<div class="csr-prose csr-reveal">
				<?php echo apply_filters( 'the_content', get_post_field( 'post_content', get_the_ID() ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
			<?php
		endif;
		?>

		<?php if ( $csr_seasons ) : ?>
			<nav class="csr-seasons csr-reveal" aria-label="Další výsledky">
				<h2 class="csr-seasons__title">Další výsledky</h2>
				<ul class="csr-seasons__list">
					<?php foreach ( $csr_seasons as $csr_p ) : ?>
						<li>
							<a href="<?php echo esc_url( get_permalink( $csr_p->ID ) ); ?>">
								<?php echo esc_html( get_the_title( $csr_p->ID ) ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>

	</div>
</section>

</main>

<?php get_template_part( 'template-parts/csr-footer' );
