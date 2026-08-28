<?php
/**
 * Template Name: ČSR — České rekordy
 *
 * Rekordy stažené na pozadí ze speedskatingresults.com. V HTML stránky
 * jsou jako obyčejný text, takže je najde i vyhledávač a přečte čtečka
 * pro nevidomé — na rozdíl od původní verze, kde je doplňoval JavaScript
 * až v prohlížeči návštěvníka.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/csr-header' );

$csr_data   = csr_records_get();
$csr_groups = csr_record_groups();
$csr_any    = ! empty( $csr_data['groups'] );

// Ručně zadané tabulky označené příznakem — sem patří short track,
// který cizí server nevede.
$csr_manual = csr_get_record_tables();
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
		<?php
		$csr_lead = csr_opt( 'csr_records_lead', '' );
		if ( $csr_lead ) :
			?>
			<p class="csr-pagehead__lead"><?php echo esc_html( $csr_lead ); ?></p>
		<?php endif; ?>
	</div>
</section>

<!-- ══════════ REKORDY ══════════ -->
<section class="csr-section">
	<div class="csr-container">

		<?php if ( $csr_any ) : ?>

			<div class="csr-calbar csr-reveal">
				<label class="csr-clubsearch" data-csr-recsearch>
					<span class="screen-reader-text">Hledat v rekordech</span>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
					<input type="search" placeholder="Jméno, dráha, trať…" autocomplete="off">
				</label>

				<div class="csr-filters" data-csr-recfilters role="group" aria-label="Filtrovat podle kategorie">
					<button class="csr-filter" type="button" data-csr-filter="all" aria-pressed="true">Vše</button>
					<?php foreach ( $csr_groups as $csr_k => $csr_g ) : ?>
						<?php if ( empty( $csr_data['groups'][ $csr_k ] ) ) { continue; } ?>
						<button class="csr-filter" type="button" data-csr-filter="<?php echo esc_attr( $csr_k ); ?>" aria-pressed="false">
							<?php echo esc_html( $csr_g[0] ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			</div>

			<div data-csr-records>
				<?php
				foreach ( $csr_groups as $csr_k => $csr_g ) {
					if ( empty( $csr_data['groups'][ $csr_k ] ) ) {
						continue;
					}
					csr_render_record_group( $csr_k, $csr_g[0], $csr_data['groups'][ $csr_k ] );
				}
				?>
			</div>
			<p class="csr-news__empty" data-csr-recempty hidden>Tomuhle hledání neodpovídá žádný rekord.</p>

			<?php csr_render_records_source( $csr_data ); ?>

		<?php else : ?>

			<div class="csr-cal__empty csr-reveal">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 1.7M9.5 2h5"/></svg>
				<h2>Rekordy se zatím nepodařilo načíst</h2>
				<?php if ( current_user_can( 'edit_posts' ) ) : ?>
					<p><small>Vidíte jen vy jako správce: zkuste <em>Výsledky → České rekordy → Stáhnout teď</em>.
						<?php if ( ! empty( $csr_data['error'] ) ) : ?>
							Server hlásí: <code><?php echo esc_html( $csr_data['error'] ); ?></code>
						<?php endif; ?>
					</small></p>
				<?php endif; ?>
			</div>

		<?php endif; ?>

		<?php
		// Ručně zadané rekordy (short track).
		foreach ( $csr_manual as $csr_t ) {
			csr_render_result( $csr_t );
		}
		?>

		<?php
		csr_page_prose( get_the_ID() );
		?>

	</div>
</section>

</main>

<?php get_template_part( 'template-parts/csr-footer' );
