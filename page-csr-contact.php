<?php
/**
 * Template Name: ČSR — Kontakty
 *
 * Adresa a e-mail se berou ze stejného nastavení jako patička, aby se
 * nemohly rozejít. Lidé se berou z databáze osob — stejné záznamy,
 * jaké vypisuje Struktura svazu.
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

$csr_address = trim( (string) csr_opt( 'csr_footer_address', '' ) );
$csr_email   = sanitize_email( (string) csr_opt( 'csr_footer_email', '' ) );
$csr_phone   = (string) csr_opt( 'csr_contact_phone', '' );
$csr_ico     = trim( (string) csr_opt( 'csr_contact_ico', '' ) );
$csr_ucet    = trim( (string) csr_opt( 'csr_contact_account', '' ) );
$csr_datovka = trim( (string) csr_opt( 'csr_contact_databox', '' ) );
$csr_lide    = csr_contact_people();
?>

<main id="obsah">

<!-- ══════════ ZÁHLAVÍ ══════════ -->
<section class="csr-pagehead">
	<div class="csr-pagehead__glow" aria-hidden="true"></div>
	<div class="csr-container csr-pagehead__inner">
		<nav class="csr-crumbs" aria-label="Drobečková navigace">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Úvod</a>
			<span aria-hidden="true">/</span>
			<span><?php the_title(); ?></span>
		</nav>
		<h1 class="csr-pagehead__title"><?php the_title(); ?></h1>
		<?php if ( csr_opt( 'csr_contact_lead', '' ) ) : ?>
			<p class="csr-pagehead__lead"><?php echo esc_html( csr_opt( 'csr_contact_lead', '' ) ); ?></p>
		<?php endif; ?>
	</div>
</section>

<!-- ══════════ SVAZ ══════════ -->
<section class="csr-section">
	<div class="csr-container">
		<div class="csr-contact">

			<div class="csr-contact__card csr-reveal">
				<h2 class="csr-contact__h">Český svaz rychlobruslení</h2>
				<dl class="csr-contact__list">

					<?php if ( $csr_address ) : ?>
						<dt>Adresa</dt>
						<dd>
							<address class="csr-contact__addr"><?php echo nl2br( esc_html( $csr_address ) ); ?></address>
							<?php if ( csr_map_href( $csr_address ) ) : ?>
								<a class="csr-contact__map" href="<?php echo esc_url( csr_map_href( $csr_address ) ); ?>"
									target="_blank" rel="noopener noreferrer">Zobrazit na mapě</a>
							<?php endif; ?>
						</dd>
					<?php endif; ?>

					<?php if ( $csr_email ) : ?>
						<dt>E-mail</dt>
						<dd>
							<?php // mailto:, ne http:// — původní odkaz vedl na neexistující server. ?>
							<a href="<?php echo esc_url( csr_mail_href( $csr_email ) ); ?>"><?php echo esc_html( $csr_email ); ?></a>
						</dd>
					<?php endif; ?>

					<?php if ( csr_format_phone( $csr_phone ) ) : ?>
						<dt>Telefon</dt>
						<dd>
							<a href="<?php echo esc_url( csr_tel_href( $csr_phone ) ); ?>">
								<?php echo esc_html( csr_format_phone( $csr_phone ) ); ?>
							</a>
						</dd>
					<?php endif; ?>

					<?php if ( $csr_ico ) : ?>
						<dt>IČO</dt>
						<dd><?php echo esc_html( $csr_ico ); ?></dd>
					<?php endif; ?>

					<?php if ( $csr_ucet ) : ?>
						<dt>Číslo účtu</dt>
						<dd><span class="csr-contact__num"><?php echo esc_html( $csr_ucet ); ?></span></dd>
					<?php endif; ?>

					<?php if ( $csr_datovka ) : ?>
						<dt>Datová schránka</dt>
						<dd><span class="csr-contact__num"><?php echo esc_html( $csr_datovka ); ?></span></dd>
					<?php endif; ?>

				</dl>
			</div>

			<?php if ( $csr_address ) : ?>
				<div class="csr-contact__card csr-contact__card--map csr-reveal">
					<h2 class="csr-contact__h">Kde nás najdete</h2>
					<p class="csr-contact__hint">
						Sídlo svazu je v areálu na Strahově. Mapu otevřeme až po vašem kliknutí —
						dokud na ni neklepnete, neodesílá se odsud nic ven.
					</p>
					<a class="csr-btn csr-btn--ghost" href="<?php echo esc_url( csr_map_href( $csr_address ) ); ?>"
						target="_blank" rel="noopener noreferrer">
						Otevřít mapu
					</a>
				</div>
			<?php endif; ?>

		</div>
	</div>
</section>

<!-- ══════════ LIDÉ ══════════ -->
<?php if ( $csr_lide ) : ?>
	<section class="csr-section csr-section--alt">
		<div class="csr-container">
			<h2 class="csr-h2">Na koho se obrátit</h2>
			<ul class="csr-people-contact" data-csr-stagger="60">
				<?php foreach ( $csr_lide as $csr_p ) : ?>
					<?php
					$csr_role  = (string) get_post_meta( $csr_p->ID, '_csr_person_role', true );
					$csr_mail  = sanitize_email( (string) get_post_meta( $csr_p->ID, '_csr_person_email', true ) );
					$csr_tel   = (string) get_post_meta( $csr_p->ID, '_csr_person_phone', true );
					?>
					<li class="csr-pcard csr-reveal">
						<?php if ( $csr_role ) : ?>
							<span class="csr-pcard__role"><?php echo esc_html( $csr_role ); ?></span>
						<?php endif; ?>
						<span class="csr-pcard__name"><?php echo esc_html( get_the_title( $csr_p ) ); ?></span>
						<span class="csr-pcard__rows">
							<?php if ( $csr_mail ) : ?>
								<a href="<?php echo esc_url( csr_mail_href( $csr_mail ) ); ?>">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
									<?php echo esc_html( $csr_mail ); ?>
								</a>
							<?php endif; ?>
							<?php if ( csr_format_phone( $csr_tel ) ) : ?>
								<a href="<?php echo esc_url( csr_tel_href( $csr_tel ) ); ?>">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2.2 2A16 16 0 0 1 3 6.2 2 2 0 0 1 5 4Z"/></svg>
									<?php echo esc_html( csr_format_phone( $csr_tel ) ); ?>
								</a>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
<?php endif; ?>

<!-- ══════════ MEZINÁRODNÍ ORGANIZACE ══════════ -->
<?php
$csr_orgs = array();
for ( $csr_i = 1; $csr_i <= 4; $csr_i++ ) {
	$csr_nazev = (string) csr_opt( 'csr_org' . $csr_i . '_label', '' );
	$csr_url   = (string) csr_opt( 'csr_org' . $csr_i . '_url', '' );
	if ( $csr_nazev && $csr_url ) {
		$csr_orgs[] = array( 'label' => $csr_nazev, 'url' => $csr_url );
	}
}
?>
<?php if ( $csr_orgs ) : ?>
	<section class="csr-section">
		<div class="csr-container">
			<h2 class="csr-h2">Mezinárodní a nadřazené organizace</h2>
			<ul class="csr-orgs" data-csr-stagger="50">
				<?php foreach ( $csr_orgs as $csr_o ) : ?>
					<li class="csr-org csr-reveal">
						<a class="csr-org__link" href="<?php echo esc_url( $csr_o['url'] ); ?>"
							target="_blank" rel="noopener noreferrer">
							<span class="csr-org__name"><?php echo esc_html( $csr_o['label'] ); ?></span>
							<span class="csr-org__host">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>
								<?php echo esc_html( (string) wp_parse_url( $csr_o['url'], PHP_URL_HOST ) ); ?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
<?php endif; ?>

<section class="csr-section csr-section--tight">
	<div class="csr-container">
		<?php csr_page_prose( get_the_ID() ); ?>
	</div>
</section>

</main>

<?php get_template_part( 'template-parts/csr-footer' );
