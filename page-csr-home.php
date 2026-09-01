<?php
/**
 * Template Name: ČSR — Úvodní stránka
 *
 * Nová úvodní stránka Českého svazu rychlobruslení.
 *
 * V této šabloně se nic needituje — veškerý obsah se nastavuje v administraci:
 *   Vzhled → Přizpůsobit → Úvodní stránka ČSR
 *
 * Vyžaduje csr-home-functions.php načtený z functions.php potomkovské šablony.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Data pro vykreslení
 * ---------------------------------------------------------------------- */

$csr_news_args = array(
	'post_type'           => 'post',
	'post_status'         => 'publish',
	'posts_per_page'      => (int) csr_opt( 'csr_news_count' ),
	'ignore_sticky_posts' => false,
);
$csr_news_cat = (int) csr_opt( 'csr_news_category' );
if ( $csr_news_cat ) {
	$csr_news_args['cat'] = $csr_news_cat;
}
$csr_news = new WP_Query( $csr_news_args );

$csr_filter_cats = csr_opt( 'csr_news_show_filters' )
	? get_categories(
		array(
			'hide_empty' => true,
			'number'     => (int) csr_opt( 'csr_news_filter_count' ),
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	)
	: array();

$csr_events = array();
if ( csr_opt( 'csr_events_show' ) && function_exists( 'tribe_get_events' ) ) {
	$csr_events = tribe_get_events(
		array(
			'posts_per_page' => (int) csr_opt( 'csr_events_count' ),
			'start_date'     => current_time( 'Y-m-d H:i:s' ),
			'orderby'        => 'event_date',
			'order'          => 'ASC',
		)
	);
}

/*
 * Fotogalerie ukazuje alba, ne poslední obrázky z knihovny médií.
 * Ty totiž zahrnují i loga klubů, fotky reprezentantů a náhledy
 * dokumentů — do galerie na úvodní stránce nic z toho nepatří.
 */
$csr_gallery = csr_opt( 'csr_gallery_show' )
	? array_slice( csr_get_albums(), 0, max( 1, (int) csr_opt( 'csr_gallery_count' ) ) )
	: array();

// Fotka v hero: nastavení v Customizeru, jinak náhledový obrázek stránky.
$csr_hero_img = csr_opt_image( 'csr_hero_image', 'full' );
if ( ! $csr_hero_img ) {
	$csr_hero_img = get_the_post_thumbnail_url( get_the_ID(), 'full' );
}


get_template_part( 'template-parts/csr-header' );

// Zamčenou stránku nevypisujeme — obsah bereme z databáze, ne z těla
// stránky, takže by ochrana heslem jinak neplatila.
if ( csr_page_locked() ) {
	csr_render_password_form();
	get_template_part( 'template-parts/csr-footer' );
	return;
}
?>

<main id="obsah">

<!-- ══════════ HERO ══════════ -->
<section class="csr-hero">
	<?php if ( $csr_hero_img ) : ?>
		<div class="csr-hero__media">
			<img src="<?php echo esc_url( $csr_hero_img ); ?>" alt="" fetchpriority="high" decoding="async">
		</div>
	<?php else : ?>
		<div class="csr-hero__fallback" aria-hidden="true"></div>
	<?php endif; ?>

	<svg class="csr-hero__trails" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
		<defs>
			<linearGradient id="csrTrailGrad" x1="0" y1="0" x2="1" y2="0">
				<stop offset="0" stop-color="#7ed0f7" stop-opacity="0"/>
				<stop offset="45%" stop-color="#7ed0f7" stop-opacity=".9"/>
				<stop offset="100%" stop-color="#38b6f0" stop-opacity="0"/>
			</linearGradient>
		</defs>
		<path d="M-100 640C220 560 460 700 760 590s520-230 820-120" stroke-width="2"/>
		<path d="M-100 720C260 620 520 780 840 660s480-200 780-90" stroke-width="1.4"/>
		<path d="M-100 500C180 460 420 560 700 470s560-180 860-70" stroke-width="1.1"/>
		<path d="M-100 810C300 700 560 850 900 720s440-160 740-60" stroke-width="1.7"/>
	</svg>

	<div class="csr-hero__scrim" aria-hidden="true"></div>

	<?php if ( csr_opt( 'csr_hero_rink' ) ) : ?>
		<canvas class="csr-hero__rink" data-csr-rink aria-hidden="true"></canvas>
	<?php endif; ?>

	<div class="csr-hero__inner">
		<div class="csr-hero__content">
			<?php if ( csr_opt( 'csr_hero_show_badge' ) ) : ?>
				<p class="csr-hero__badge">
					<b><?php echo esc_html( csr_opt( 'csr_hero_badge_live' ) ); ?></b>
					<?php echo esc_html( csr_opt( 'csr_hero_badge_text' ) ); ?>
				</p>
			<?php endif; ?>

			<h1 class="csr-hero__title">
				<span class="csr-hero__line"><i><?php echo esc_html( csr_opt( 'csr_hero_line1' ) ); ?></i></span>
				<span class="csr-hero__line"><i><?php echo esc_html( csr_opt( 'csr_hero_line2' ) ); ?></i></span>
				<span class="csr-hero__line"><span class="csr-grad"><?php echo esc_html( csr_opt( 'csr_hero_line3' ) ); ?></span></span>
			</h1>

			<p class="csr-hero__lead"><?php echo esc_html( csr_opt( 'csr_hero_lead' ) ); ?></p>

			<div class="csr-hero__cta">
				<a class="csr-btn csr-btn--primary" href="<?php echo esc_url( csr_link( csr_opt( 'csr_hero_btn1_url' ) ) ); ?>">
					<?php echo esc_html( csr_opt( 'csr_hero_btn1_label' ) ); ?>
					<svg class="csr-btn__ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
				</a>
				<a class="csr-btn csr-btn--ondark" href="<?php echo esc_url( csr_link( csr_opt( 'csr_hero_btn2_url' ) ) ); ?>">
					<?php echo esc_html( csr_opt( 'csr_hero_btn2_label' ) ); ?>
				</a>
			</div>
		</div>

		<?php if ( csr_opt( 'csr_hero_show_stats' ) ) : ?>
			<div class="csr-hero__stats">
				<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
					<?php
					// Hodnota se počítá z obsahu webu. Když se spočítat nedá,
					// číslo se vynechá — radši méně údajů než vymyšlený.
					$csr_value = csr_stat_value( $i );
					$csr_label = csr_stat_label( $i );
					if ( null === $csr_value || ! $csr_label ) {
						continue;
					}
					?>
					<div class="csr-hero__stat">
						<b><span data-csr-count="<?php echo esc_attr( $csr_value ); ?>"<?php echo csr_opt( "csr_stat{$i}_nogroup" ) ? ' data-csr-nogroup' : ''; ?>><?php echo esc_html( $csr_value ); ?></span><?php echo esc_html( csr_opt( "csr_stat{$i}_suffix" ) ); ?></b>
						<span><?php echo esc_html( $csr_label ); ?></span>
					</div>
				<?php endfor; ?>
			</div>
		<?php endif; ?>
	</div>

	<a class="csr-hero__scroll" href="#rychle"><span>Scroll</span><i aria-hidden="true"></i></a>
</section>

<!-- ══════════ INFO LIŠTA ══════════ -->
<?php
$csr_ticker = csr_opt( 'csr_ticker_show' )
	? get_posts( array( 'numberposts' => (int) csr_opt( 'csr_ticker_count' ), 'post_status' => 'publish' ) )
	: array();
if ( $csr_ticker ) :
	?>
	<div class="csr-ticker">
		<div class="csr-ticker__label"><i aria-hidden="true"></i><span><?php echo esc_html( csr_opt( 'csr_ticker_label' ) ); ?></span></div>
		<div class="csr-ticker__viewport">
			<div class="csr-ticker__track" data-csr-marquee>
				<?php foreach ( $csr_ticker as $csr_tp ) : ?>
					<a class="csr-ticker__item" href="<?php echo esc_url( get_permalink( $csr_tp->ID ) ); ?>">
						<time datetime="<?php echo esc_attr( get_the_date( 'c', $csr_tp->ID ) ); ?>"><?php echo esc_html( get_the_date( 'j. n.', $csr_tp->ID ) ); ?></time>
						<?php echo esc_html( wp_trim_words( get_the_title( $csr_tp->ID ), 12, '…' ) ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
<?php endif; ?>

<!-- ══════════ RYCHLÉ ODKAZY ══════════ -->
<section class="csr-section csr-section--tight" id="rychle">
	<div class="csr-container">
		<div class="csr-quick" data-csr-stagger="70">
			<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
				<?php
				$csr_label = csr_opt( "csr_quick{$i}_label" );
				if ( ! $csr_label ) {
					continue;
				}
				?>
				<a class="csr-quick__card csr-reveal" href="<?php echo esc_url( csr_link( csr_opt( "csr_quick{$i}_url" ) ) ); ?>">
					<span class="csr-quick__ico">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo csr_icon_paths( csr_opt( "csr_quick{$i}_icon" ) ); // phpcs:ignore WordPress.Security.EscapeOutput — pevná knihovna SVG cest ?></svg>
					</span>
					<span>
						<span class="csr-quick__label">
							<?php echo esc_html( $csr_label ); ?>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
						</span>
						<span class="csr-quick__desc"><?php echo esc_html( csr_opt( "csr_quick{$i}_desc" ) ); ?></span>
					</span>
				</a>
			<?php endfor; ?>
		</div>
	</div>
</section>

<!-- ══════════ AKTUALITY ══════════ -->
<?php if ( csr_opt( 'csr_news_show' ) ) : ?>
	<section class="csr-section csr-section--soft" id="aktuality">
		<div class="csr-container">

			<div class="csr-sechead csr-sechead--split csr-reveal">
				<div>
					<p class="csr-sechead__eyebrow"><?php echo esc_html( csr_opt( 'csr_news_eyebrow' ) ); ?></p>
					<h2 class="csr-sechead__title">
						<?php echo esc_html( csr_opt( 'csr_news_title' ) ); ?>
						<em><?php echo esc_html( csr_opt( 'csr_news_title_accent' ) ); ?></em>
					</h2>
					<p class="csr-sechead__lead"><?php echo esc_html( csr_opt( 'csr_news_lead' ) ); ?></p>
				</div>

				<?php if ( ! empty( $csr_filter_cats ) ) : ?>
					<div class="csr-filters" data-csr-filters role="group" aria-label="Filtrovat aktuality podle kategorie">
						<button class="csr-filter" type="button" data-csr-filter="all" aria-pressed="true">Vše</button>
						<?php foreach ( $csr_filter_cats as $csr_cat ) : ?>
							<button class="csr-filter" type="button" data-csr-filter="<?php echo esc_attr( $csr_cat->slug ); ?>" aria-pressed="false">
								<?php echo esc_html( $csr_cat->name ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="csr-news" data-csr-news data-csr-stagger="80">
				<?php
				if ( $csr_news->have_posts() ) :
					$csr_i = 0;
					while ( $csr_news->have_posts() ) :
						$csr_news->the_post();
						csr_render_article_card( get_post(), 0 === $csr_i );
						$csr_i++;
					endwhile;
					wp_reset_postdata();
				else :
					?>
					<p class="csr-news__empty">Zatím tu nejsou žádné články.</p>
					<?php
				endif;
				?>
				<p class="csr-news__empty" hidden>V této kategorii zatím nejsou žádné články.</p>
			</div>

			<?php if ( $csr_news->max_num_pages > 1 ) : ?>
				<div class="csr-news__more">
					<button class="csr-btn csr-btn--ghost" type="button"
					        data-csr-loadmore="<?php echo esc_url( home_url( '/?csr_more=1' ) ); ?>"
					        data-csr-page="1"
					        data-csr-max="<?php echo (int) $csr_news->max_num_pages; ?>">
						Zobrazit více článků
					</button>
				</div>
			<?php endif; ?>

		</div>
	</section>
<?php endif; ?>

<!-- ══════════ NADCHÁZEJÍCÍ AKCE ══════════ -->
<?php if ( ! empty( $csr_events ) ) : ?>
	<section class="csr-section" id="akce">
		<div class="csr-container">
			<div class="csr-sechead csr-sechead--split csr-reveal">
				<div>
					<p class="csr-sechead__eyebrow"><?php echo esc_html( csr_opt( 'csr_events_eyebrow' ) ); ?></p>
					<h2 class="csr-sechead__title">
						<?php echo esc_html( csr_opt( 'csr_events_title' ) ); ?>
						<em><?php echo esc_html( csr_opt( 'csr_events_title_accent' ) ); ?></em>
					</h2>
				</div>
				<a class="csr-btn csr-btn--ghost csr-btn--sm" href="<?php echo esc_url( csr_link( csr_opt( 'csr_events_url' ) ) ); ?>">
					Celý kalendář
					<svg class="csr-btn__ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
				</a>
			</div>

			<div class="csr-events" data-csr-stagger="70">
				<?php foreach ( $csr_events as $csr_ev ) : ?>
					<?php
					$csr_start = tribe_get_start_date( $csr_ev, false, 'U' );
					$csr_venue = function_exists( 'tribe_get_venue' ) ? tribe_get_venue( $csr_ev->ID ) : '';
					?>
					<article class="csr-event csr-reveal">
						<div class="csr-event__date">
							<span class="csr-event__day"><?php echo esc_html( wp_date( 'd', $csr_start ) ); ?></span>
							<span class="csr-event__mon"><?php echo esc_html( wp_date( 'M', $csr_start ) ); ?></span>
						</div>
						<div class="csr-event__body">
							<h3 class="csr-event__title">
								<a href="<?php echo esc_url( get_permalink( $csr_ev->ID ) ); ?>"><?php echo esc_html( get_the_title( $csr_ev->ID ) ); ?></a>
							</h3>
							<div class="csr-event__meta">
								<?php if ( $csr_venue ) : ?>
									<span>
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/></svg>
										<?php echo esc_html( $csr_venue ); ?>
									</span>
								<?php endif; ?>
								<span>
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 2"/></svg>
									<?php echo esc_html( tribe_get_start_date( $csr_ev, false, 'j. n. Y' ) ); ?>
								</span>
							</div>
						</div>
						<span class="csr-event__go" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
						</span>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<!-- ══════════ ÚSPĚCHY ══════════ -->
<?php if ( csr_opt( 'csr_achieve_show' ) ) : ?>
	<section class="csr-section csr-achieve">
		<div class="csr-container">
			<div class="csr-achieve__grid">

				<div class="csr-reveal csr-reveal--left">
					<p class="csr-sechead__eyebrow"><?php echo esc_html( csr_opt( 'csr_achieve_eyebrow' ) ); ?></p>
					<h2 class="csr-sechead__title">
						<?php echo esc_html( csr_opt( 'csr_achieve_title' ) ); ?>
						<em><?php echo esc_html( csr_opt( 'csr_achieve_title_accent' ) ); ?></em>
						<?php echo esc_html( csr_opt( 'csr_achieve_title_rest' ) ); ?>
					</h2>
					<p class="csr-sechead__lead"><?php echo esc_html( csr_opt( 'csr_achieve_lead' ) ); ?></p>

					<?php if ( csr_opt( 'csr_medals_show' ) ) : ?>
						<?php
						$csr_medals = array(
							array( 'Zlato',   (int) csr_opt( 'csr_medal_gold' ),   'gold' ),
							array( 'Stříbro', (int) csr_opt( 'csr_medal_silver' ), 'silver' ),
							array( 'Bronz',   (int) csr_opt( 'csr_medal_bronze' ), 'bronze' ),
						);
						$csr_medal_max = max( 1, max( wp_list_pluck( $csr_medals, 1 ) ) );
						?>
						<div class="csr-medals">
							<?php foreach ( $csr_medals as $csr_m ) : ?>
								<div class="csr-medal csr-medal--<?php echo esc_attr( $csr_m[2] ); ?>">
									<span class="csr-medal__name"><?php echo esc_html( $csr_m[0] ); ?></span>
									<span class="csr-medal__bar">
										<span class="csr-medal__fill" style="--csr-w:<?php echo esc_attr( round( $csr_m[1] / $csr_medal_max * 100 ) ); ?>%"></span>
									</span>
									<span class="csr-medal__val"><?php echo esc_html( $csr_m[1] ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( csr_opt( 'csr_achieve_btn_label' ) ) : ?>
						<p style="margin-top:1.75rem">
							<a class="csr-btn csr-btn--ondark" href="<?php echo esc_url( csr_link( csr_opt( 'csr_achieve_btn_url' ) ) ); ?>">
								<?php echo esc_html( csr_opt( 'csr_achieve_btn_label' ) ); ?>
								<svg class="csr-btn__ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
							</a>
						</p>
					<?php endif; ?>
				</div>

				<div class="csr-counters csr-reveal csr-reveal--right">
					<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
						<?php
						// Stejně jako čísla nahoře: hodnota se počítá z obsahu
						// webu, a co spočítat nejde, se vynechá.
						$csr_value  = csr_stat_value( $i, 'counter' );
						$csr_label  = csr_stat_label( $i, 'counter' );
						if ( null === $csr_value || ! $csr_label ) {
							continue;
						}
						$csr_suffix = csr_opt( "csr_counter{$i}_suffix" );
						?>
						<div class="csr-counter<?php echo csr_opt( "csr_counter{$i}_gold" ) ? ' csr-counter--gold' : ''; ?>">
							<b>
								<span data-csr-count="<?php echo esc_attr( $csr_value ); ?>"><?php echo esc_html( $csr_value ); ?></span>
								<?php if ( $csr_suffix ) : ?><i><?php echo esc_html( $csr_suffix ); ?></i><?php endif; ?>
							</b>
							<span><?php echo esc_html( $csr_label ); ?></span>
						</div>
					<?php endfor; ?>
				</div>

			</div>
		</div>
	</section>
<?php endif; ?>

<!-- ══════════ CTA ══════════ -->
<?php if ( csr_opt( 'csr_cta_show' ) ) : ?>
	<section class="csr-section">
		<div class="csr-container">
			<div class="csr-cta csr-reveal csr-reveal--zoom">
				<div>
					<h2><?php echo esc_html( csr_opt( 'csr_cta_title' ) ); ?></h2>
					<p><?php echo esc_html( csr_opt( 'csr_cta_text' ) ); ?></p>
				</div>
				<?php if ( csr_opt( 'csr_cta_btn_label' ) ) : ?>
					<div class="csr-cta__actions">
						<a class="csr-btn" href="<?php echo esc_url( csr_link( csr_opt( 'csr_cta_btn_url' ) ) ); ?>">
							<?php echo esc_html( csr_opt( 'csr_cta_btn_label' ) ); ?>
							<svg class="csr-btn__ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
						</a>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<!-- ══════════ GALERIE ══════════ -->
<?php if ( ! empty( $csr_gallery ) ) : ?>
	<section class="csr-section csr-section--soft">
		<div class="csr-container">
			<div class="csr-sechead csr-sechead--split csr-reveal">
				<div>
					<p class="csr-sechead__eyebrow"><?php echo esc_html( csr_opt( 'csr_gallery_eyebrow' ) ); ?></p>
					<h2 class="csr-sechead__title"><?php echo esc_html( csr_opt( 'csr_gallery_title' ) ); ?></h2>
				</div>
				<a class="csr-btn csr-btn--ghost csr-btn--sm" href="<?php echo esc_url( csr_link( csr_opt( 'csr_gallery_url' ) ) ); ?>">
					Celá galerie
					<svg class="csr-btn__ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
				</a>
			</div>

			<ul class="csr-albums csr-albums--home csr-reveal" data-csr-stagger="55">
				<?php
				foreach ( $csr_gallery as $csr_album ) {
					csr_render_album_card( $csr_album );
				}
				?>
			</ul>
		</div>
	</section>
<?php endif; ?>

<!-- ══════════ PARTNEŘI ══════════ -->
<?php if ( csr_opt( 'csr_partners_show' ) ) : ?>
	<section class="csr-section csr-section--tight">
		<div class="csr-container csr-container--wide">
			<p class="csr-sechead__eyebrow csr-reveal" style="justify-content:center;display:flex;margin-bottom:2rem">
				<?php echo esc_html( csr_opt( 'csr_partners_title' ) ); ?>
			</p>
			<div class="csr-partners__viewport csr-reveal">
				<div class="csr-partners__track" data-csr-marquee>
					<?php for ( $i = 1; $i <= 6; $i++ ) : ?>
						<?php
						$csr_pname = csr_opt( "csr_partner{$i}_name" );
						if ( ! $csr_pname ) {
							continue;
						}
						$csr_plogo = csr_opt_image( "csr_partner{$i}_logo", 'medium' );
						$csr_purl  = csr_opt( "csr_partner{$i}_url" );
						?>
						<div class="csr-partners__item">
							<?php if ( $csr_purl ) : ?><a href="<?php echo esc_url( $csr_purl ); ?>" target="_blank" rel="noopener"><?php endif; ?>
								<?php if ( $csr_plogo ) : ?>
									<img src="<?php echo esc_url( $csr_plogo ); ?>" alt="<?php echo esc_attr( $csr_pname ); ?>" loading="lazy">
								<?php else : ?>
									<span class="csr-logo-ph"><?php echo esc_html( $csr_pname ); ?></span>
								<?php endif; ?>
							<?php if ( $csr_purl ) : ?></a><?php endif; ?>
						</div>
					<?php endfor; ?>
				</div>
			</div>
		</div>
	</section>
<?php endif; ?>

</main>

<?php get_template_part( 'template-parts/csr-footer' );
