<?php
/**
 * Template Name: ČSR — Náborová stránka
 *
 * Kampaňová stránka s výzvou a přihlašovacím formulářem — třeba pozvánka
 * na seminář „Jak založit oddíl". Na rozdíl od ostatních šablon tady obsah
 * stránky vypisujeme včetně elementorového: je to to hlavní, co na stránce
 * je, a je v něm i vložený formulář.
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

$csr_lead  = get_post_meta( get_the_ID(), '_csr_landing_lead', true );
$csr_badge = get_post_meta( get_the_ID(), '_csr_landing_badge', true );

// Bez ručně zadaného úvodu vezmeme první odstavec obsahu.
if ( ! $csr_lead ) {
	$csr_lead = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', get_the_ID() ) ), 34, '…' );
}
?>

<main id="obsah">

<!-- ══════════ ZÁHLAVÍ ══════════ -->
<section class="csr-pagehead csr-pagehead--landing">
	<div class="csr-pagehead__glow" aria-hidden="true"></div>
	<div class="csr-container csr-pagehead__inner">
		<nav class="csr-crumbs" aria-label="Drobečková navigace">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Úvod</a>
			<span aria-hidden="true">/</span>
			<span><?php echo esc_html( get_the_title() ); ?></span>
		</nav>

		<?php if ( $csr_badge ) : ?>
			<span class="csr-chip csr-chip--solid csr-landing__badge"><?php echo esc_html( $csr_badge ); ?></span>
		<?php endif; ?>

		<h1 class="csr-pagehead__title"><?php echo esc_html( get_the_title() ); ?></h1>

		<?php if ( $csr_lead ) : ?>
			<p class="csr-pagehead__lead"><?php echo esc_html( $csr_lead ); ?></p>
		<?php endif; ?>
	</div>
</section>

<!-- ══════════ OBSAH A FORMULÁŘ ══════════ -->
<section class="csr-section">
	<div class="csr-container">
		<div class="csr-landing csr-reveal">
			<?php
			/*
			 * Tady the_content() voláme schválně: obsah je v Elementoru
			 * a je v něm i vložený formulář. Na ostatních šablonách ho
			 * přeskakujeme, protože by zdvojil to, co vykreslí šablona sama.
			 */
			the_content();
			?>
		</div>
	</div>
</section>

</main>

<?php get_template_part( 'template-parts/csr-footer' );
