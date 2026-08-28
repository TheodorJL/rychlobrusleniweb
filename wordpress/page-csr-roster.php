<?php
/**
 * Template Name: ČSR — Soupiska reprezentace
 *
 * Vypíše reprezentanty podle sezóny a týmu, které se vyberou v boxu
 * „Soupiska — co se má vypsat" vpravo u stránky.
 *
 * Lidé se zadávají v administraci v sekci Reprezentanti.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$csr_season_id = (int) get_post_meta( get_the_ID(), '_csr_page_season', true );
$csr_squad_id  = (int) get_post_meta( get_the_ID(), '_csr_page_squad', true );
$csr_intro     = get_post_meta( get_the_ID(), '_csr_page_intro', true );

$csr_roster = csr_get_roster( $csr_season_id, $csr_squad_id );
$csr_season = $csr_season_id ? get_term( $csr_season_id, CSR_TAX_SEASON ) : null;
$csr_squad  = $csr_squad_id ? get_term( $csr_squad_id, CSR_TAX_SQUAD ) : null;

$csr_count = count( $csr_roster['zavodnici'] );

get_template_part( 'template-parts/csr-header' );
?>

<main id="obsah">

<!-- ══════════ ZÁHLAVÍ STRÁNKY ══════════ -->
<section class="csr-pagehead">
	<div class="csr-pagehead__glow" aria-hidden="true"></div>
	<div class="csr-container csr-pagehead__inner">

		<nav class="csr-crumbs" aria-label="Drobečková navigace">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Úvod</a>
			<span aria-hidden="true">/</span>
			<span>Reprezentace</span>
		</nav>

		<h1 class="csr-pagehead__title"><?php echo esc_html( get_the_title() ); ?></h1>

		<div class="csr-pagehead__meta">
			<?php if ( $csr_season && ! is_wp_error( $csr_season ) ) : ?>
				<span class="csr-chip csr-chip--ss">Sezóna <?php echo esc_html( $csr_season->name ); ?></span>
			<?php endif; ?>
			<?php if ( $csr_squad && ! is_wp_error( $csr_squad ) ) : ?>
				<span class="csr-chip csr-chip--org"><?php echo esc_html( $csr_squad->name ); ?></span>
			<?php endif; ?>
			<?php if ( $csr_count ) : ?>
				<span class="csr-chip csr-chip--solid">
					<?php
					// Čeština má tři tvary: 1 závodník, 2–4 závodníci, 5+ závodníků.
					if ( 1 === $csr_count ) {
						echo '1 závodník';
					} elseif ( $csr_count < 5 ) {
						echo esc_html( $csr_count ) . ' závodníci';
					} else {
						echo esc_html( $csr_count ) . ' závodníků';
					}
					?>
				</span>
			<?php endif; ?>
		</div>

		<?php if ( $csr_intro ) : ?>
			<p class="csr-pagehead__lead"><?php echo esc_html( $csr_intro ); ?></p>
		<?php endif; ?>

	</div>
</section>

<!-- ══════════ SOUPISKA ══════════ -->
<section class="csr-section">
	<div class="csr-container">

		<?php if ( empty( $csr_roster['zavodnici'] ) && empty( $csr_roster['stab'] ) ) : ?>

			<p class="csr-news__empty">
				Pro tuto kombinaci sezóny a týmu zatím nikdo není zařazený.
				<?php if ( current_user_can( 'edit_posts' ) ) : ?>
					<br><small>Lidi přidáte v sekci <em>Reprezentanti</em>, výběr sezóny a týmu je vpravo u této stránky.</small>
				<?php endif; ?>
			</p>

		<?php else : ?>

			<?php if ( $csr_roster['zavodnici'] ) : ?>
				<div class="csr-roster" data-csr-stagger="60">
					<?php foreach ( $csr_roster['zavodnici'] as $csr_person ) : ?>
						<?php csr_render_person_card( $csr_person, false ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $csr_roster['stab'] ) : ?>
				<div class="csr-sechead csr-reveal" style="margin-top:clamp(3rem,6vw,5rem)">
					<p class="csr-sechead__eyebrow">Realizační tým</p>
					<h2 class="csr-sechead__title">Trenéři a <em>vedení</em></h2>
				</div>
				<div class="csr-roster csr-roster--staff" data-csr-stagger="60">
					<?php foreach ( $csr_roster['stab'] as $csr_person ) : ?>
						<?php csr_render_person_card( $csr_person, true ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		<?php endif; ?>

		<?php
		// Volný obsah stránky (poznámky, kritéria…) se vypíše pod soupiskou.
		csr_page_prose( get_the_ID() );
		?>

	</div>
</section>

</main>

<?php get_template_part( 'template-parts/csr-footer' );
