<?php
/**
 * Sdílená hlavička pro všechny šablony ČSR.
 * Otevírá dokument a vykresluje navigaci, mobilní menu a vyhledávání.
 * Uzavírá ji template-parts/csr-footer.php.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

list( $csr_logo, $csr_logo_is_fallback ) = csr_logo_url();
$csr_logo_invert = ! $csr_logo_is_fallback && csr_opt( 'csr_logo_invert' );
$csr_show_text   = $csr_logo_is_fallback || csr_opt( 'csr_brand_show_text' );
$csr_brand_class = 'csr-brand' . ( $csr_logo_invert ? ' csr-brand--invert' : '' );

?><!doctype html>
<html <?php language_attributes(); ?> data-csr-theme="dark">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="<?php echo esc_attr( csr_opt( 'csr_color_dark' ) ); ?>">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="csr-page">

<div class="csr-progress" aria-hidden="true"></div>
<a class="csr-sr-only" href="#obsah">Přeskočit na obsah</a>

<header class="csr-header">
	<div class="csr-header__inner">

		<a class="<?php echo esc_attr( $csr_brand_class ); ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> — úvodní stránka">
			<?php if ( $csr_logo_is_fallback ) : ?>
				<span class="csr-brand__mark"><img src="<?php echo esc_url( $csr_logo ); ?>" alt="" width="42" height="42"></span>
			<?php else : ?>
				<img class="csr-brand__logo" src="<?php echo esc_url( $csr_logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php endif; ?>

			<?php if ( $csr_show_text ) : ?>
				<span class="csr-brand__txt">
					<span class="csr-brand__name"><?php echo esc_html( csr_opt( 'csr_brand_name' ) ); ?></span>
					<span class="csr-brand__sub"><?php echo esc_html( csr_opt( 'csr_brand_sub' ) ); ?></span>
				</span>
			<?php endif; ?>
		</a>

		<nav class="csr-nav" aria-label="Hlavní navigace">
			<?php csr_nav_menu( 'csr-nav__list' ); ?>
		</nav>

		<div class="csr-header__actions">
			<button class="csr-iconbtn" type="button" data-csr-open="search" aria-expanded="false" aria-label="Vyhledávání">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
			</button>
			<button class="csr-iconbtn csr-burger" type="button" data-csr-open="drawer" aria-expanded="false" aria-label="Otevřít menu">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3.5 7h17M3.5 12h17M3.5 17h17"/></svg>
			</button>
		</div>

	</div>
</header>

<div class="csr-drawer">
	<div class="csr-drawer__top">
		<a class="<?php echo esc_attr( $csr_brand_class ); ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php if ( $csr_logo_is_fallback ) : ?>
				<span class="csr-brand__mark"><img src="<?php echo esc_url( $csr_logo ); ?>" alt="" width="42" height="42"></span>
			<?php else : ?>
				<img class="csr-brand__logo" src="<?php echo esc_url( $csr_logo ); ?>" alt="">
			<?php endif; ?>
		</a>
		<button class="csr-iconbtn" type="button" data-csr-close="drawer" aria-label="Zavřít menu">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
		</button>
	</div>
	<div class="csr-drawer__body">
		<?php csr_nav_menu( 'csr-nav__list' ); ?>
	</div>
</div>

<div class="csr-search" role="dialog" aria-modal="true" aria-label="Vyhledávání">
	<div class="csr-search__box">
		<form class="csr-search__form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="#6b7d93" stroke-width="2" stroke-linecap="round" width="20" height="20" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
			<input class="csr-search__input" type="search" name="s" placeholder="Hledat závody, výsledky, dokumenty…" aria-label="Hledaný výraz" value="<?php echo esc_attr( get_search_query() ); ?>">
			<button class="csr-btn csr-btn--primary csr-btn--sm" type="submit">Hledat</button>
		</form>
		<p class="csr-search__hint">Tip: vyhledávání otevřete kdykoli klávesou <kbd>/</kbd> · zavřete <kbd>Esc</kbd></p>
	</div>
</div>
