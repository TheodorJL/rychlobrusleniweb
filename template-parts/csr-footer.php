<?php
/**
 * Sdílená patička pro všechny šablony ČSR.
 * Uzavírá dokument otevřený v template-parts/csr-header.php.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

list( $csr_logo, $csr_logo_is_fallback ) = csr_logo_url();
$csr_logo_invert = ! $csr_logo_is_fallback && csr_opt( 'csr_logo_invert' );

$csr_social = array(
	'csr_footer_fb' => array( 'Facebook',  '<path d="M13.5 22v-8h2.7l.4-3.1h-3.1V8.9c0-.9.25-1.5 1.55-1.5h1.65V4.6c-.3-.04-1.3-.13-2.45-.13-2.42 0-4.08 1.48-4.08 4.2v2.23H7.5V14h2.67v8h3.33Z"/>' ),
	'csr_footer_ig' => array( 'Instagram', '<path d="M12 2.2c3.2 0 3.6 0 4.85.07 3.25.15 4.77 1.69 4.92 4.92.06 1.25.07 1.65.07 4.81 0 3.17 0 3.56-.07 4.81-.15 3.23-1.66 4.77-4.92 4.92-1.25.06-1.64.07-4.85.07-3.2 0-3.6 0-4.85-.07-3.26-.15-4.77-1.7-4.92-4.92C2.17 15.56 2.16 15.17 2.16 12c0-3.16.01-3.56.07-4.81C2.38 3.96 3.9 2.42 7.15 2.27 8.4 2.21 8.8 2.2 12 2.2Zm0 5.63a4.17 4.17 0 1 0 0 8.34 4.17 4.17 0 0 0 0-8.34Zm0 6.88a2.71 2.71 0 1 1 0-5.42 2.71 2.71 0 0 1 0 5.42Zm5.31-7.04a.97.97 0 1 1-1.95 0 .97.97 0 0 1 1.95 0Z"/>' ),
	'csr_footer_yt' => array( 'YouTube',   '<path d="M21.6 7.2a2.5 2.5 0 0 0-1.75-1.76C18.28 5 12 5 12 5s-6.28 0-7.85.44A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.75 1.76C5.72 19 12 19 12 19s6.28 0 7.85-.44a2.5 2.5 0 0 0 1.75-1.76A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8ZM10 15.1V8.9l5.2 3.1-5.2 3.1Z"/>' ),
);

$csr_has_social = false;
foreach ( $csr_social as $csr_key => $csr_meta ) {
	if ( csr_opt( $csr_key ) ) {
		$csr_has_social = true;
		break;
	}
}
?>

<footer class="csr-footer">
	<div class="csr-container">
		<div class="csr-footer__grid">

			<div>
				<div class="csr-footer__brand <?php echo $csr_logo_invert ? 'csr-brand--invert' : ''; ?>">
					<?php if ( $csr_logo_is_fallback ) : ?>
						<img src="<?php echo esc_url( $csr_logo ); ?>" alt="" width="52" height="52">
						<span class="csr-brand__txt">
							<span class="csr-brand__name"><?php echo esc_html( csr_opt( 'csr_brand_name' ) ); ?></span>
							<span class="csr-brand__sub"><?php echo esc_html( csr_opt( 'csr_brand_sub' ) ); ?></span>
						</span>
					<?php else : ?>
						<img class="csr-brand__logo" src="<?php echo esc_url( $csr_logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
					<?php endif; ?>
				</div>
				<p class="csr-footer__about"><?php echo esc_html( csr_opt( 'csr_footer_about' ) ); ?></p>

				<?php if ( $csr_has_social ) : ?>
					<div class="csr-footer__social">
						<?php foreach ( $csr_social as $csr_key => $csr_meta ) : ?>
							<?php if ( csr_opt( $csr_key ) ) : ?>
								<a href="<?php echo esc_url( csr_opt( $csr_key ) ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( $csr_meta[0] ); ?>">
									<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><?php echo $csr_meta[1]; // phpcs:ignore WordPress.Security.EscapeOutput — pevné SVG cesty ?></svg>
								</a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div>
				<h3><?php echo esc_html( csr_opt( 'csr_footer_col2_title' ) ); ?></h3>
				<?php if ( ! csr_footer_menu( 'csr_footer_org' ) ) : ?>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/1450-2/' ) ); ?>">Struktura svazu</a></li>
						<li><a href="<?php echo esc_url( home_url( '/dokumenty/' ) ); ?>">Dokumenty</a></li>
						<li><a href="<?php echo esc_url( home_url( '/pravidla-a-predpisy-isu/' ) ); ?>">Pravidla a předpisy ISU</a></li>
						<li><a href="<?php echo esc_url( home_url( '/smlouvy/' ) ); ?>">Smlouvy</a></li>
						<li><a href="<?php echo esc_url( home_url( '/svaz/historie-archiv/' ) ); ?>">Archiv</a></li>
					</ul>
				<?php endif; ?>
			</div>

			<div>
				<h3><?php echo esc_html( csr_opt( 'csr_footer_col3_title' ) ); ?></h3>
				<?php if ( ! csr_footer_menu( 'csr_footer_sport' ) ) : ?>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/akce/' ) ); ?>">Kalendář závodů</a></li>
						<li><a href="<?php echo esc_url( home_url( '/ceske-rekordy/' ) ); ?>">České rekordy</a></li>
						<li><a href="<?php echo esc_url( home_url( '/infofeed/' ) ); ?>">InfoFeed</a></li>
						<li><a href="<?php echo esc_url( home_url( '/kluby-2/' ) ); ?>">Kluby</a></li>
						<li><a href="<?php echo esc_url( home_url( '/zakladni-dokumenty/' ) ); ?>">Antidoping</a></li>
					</ul>
				<?php endif; ?>
			</div>

			<div>
				<h3>Kontakt</h3>
				<div class="csr-footer__contact">
					<?php if ( csr_opt( 'csr_footer_address' ) ) : ?>
						<div>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/></svg>
							<span><?php echo nl2br( esc_html( csr_opt( 'csr_footer_address' ) ) ); ?></span>
						</div>
					<?php endif; ?>
					<div>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.6 6.8 8.4 6 8.4-6"/></svg>
						<?php if ( csr_opt( 'csr_footer_email' ) ) : ?>
							<span><a href="mailto:<?php echo esc_attr( antispambot( csr_opt( 'csr_footer_email' ) ) ); ?>"><?php echo esc_html( antispambot( csr_opt( 'csr_footer_email' ) ) ); ?></a></span>
						<?php else : ?>
							<span><a href="<?php echo esc_url( home_url( '/svaz/kontakty/' ) ); ?>">Kontakty na svaz</a></span>
						<?php endif; ?>
					</div>
				</div>
			</div>

		</div>

		<div class="csr-footer__bottom">
			<span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
			<nav aria-label="Právní informace">
				<a href="<?php echo esc_url( home_url( '/svaz/kontakty/' ) ); ?>">Kontakty</a>
				<a href="<?php echo esc_url( home_url( '/dokumenty/' ) ); ?>">Dokumenty</a>
			</nav>
		</div>
	</div>
</footer>

<button class="csr-top" type="button" aria-label="Zpět nahoru">
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M6 11l6-6 6 6"/></svg>
</button>

</div><!-- /.csr-page -->

<?php wp_footer(); ?>
</body>
</html>
