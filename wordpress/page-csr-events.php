<?php
/**
 * Template Name: ČSR — Kalendář akcí
 *
 * Výpis závodů z pluginu The Events Calendar v designu nového webu.
 * Závody se dál zadávají běžně v administraci v sekci Akce.
 *
 * Přepínání nadcházející / proběhlé řeší parametr ?zobrazit=minule,
 * stránkování ?strana=2.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/csr-header' );

$csr_has_tec = function_exists( 'tribe_get_events' );

// phpcs:disable WordPress.Security.NonceVerification.Recommended — jen čtení výpisu
$csr_past  = isset( $_GET['zobrazit'] ) && 'minule' === sanitize_key( wp_unslash( $_GET['zobrazit'] ) );
$csr_paged = isset( $_GET['strana'] ) ? max( 1, absint( $_GET['strana'] ) ) : 1;
// phpcs:enable

$csr_per_page = max( 1, (int) csr_opt( 'csr_cal_per_page' ) );
$csr_now      = current_time( 'Y-m-d H:i:s' );

$csr_query = null;
$csr_items = array();

if ( $csr_has_tec ) {
	$csr_args = array(
		'posts_per_page' => $csr_per_page,
		'paged'          => $csr_paged,
	);

	if ( $csr_past ) {
		$csr_args['end_date'] = $csr_now;
		$csr_args['order']    = 'DESC';
	} else {
		$csr_args['start_date'] = $csr_now;
		$csr_args['order']      = 'ASC';
	}

	// Druhý parametr vrátí WP_Query, ze kterého vezmeme počet stránek.
	$csr_query = tribe_get_events( $csr_args, true );
	$csr_items = $csr_query->posts;
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
			<span><?php echo esc_html( get_the_title() ); ?></span>
		</nav>

		<h1 class="csr-pagehead__title"><?php echo esc_html( get_the_title() ); ?></h1>

		<div class="csr-pagehead__meta">
			<span class="csr-chip csr-chip--ss">
				<?php echo $csr_past ? 'Proběhlé závody' : 'Nadcházející závody'; ?>
			</span>
			<?php if ( $csr_query && $csr_query->found_posts ) : ?>
				<span class="csr-chip csr-chip--solid">
					<?php
					$csr_total = (int) $csr_query->found_posts;
					if ( 1 === $csr_total ) {
						echo '1 akce';
					} elseif ( $csr_total < 5 ) {
						echo esc_html( $csr_total ) . ' akce';
					} else {
						echo esc_html( $csr_total ) . ' akcí';
					}
					?>
				</span>
			<?php endif; ?>
		</div>

		<?php if ( csr_opt( 'csr_cal_lead' ) ) : ?>
			<p class="csr-pagehead__lead"><?php echo esc_html( csr_opt( 'csr_cal_lead' ) ); ?></p>
		<?php endif; ?>

	</div>
</section>

<!-- ══════════ VÝPIS ══════════ -->
<section class="csr-section">
	<div class="csr-container">

		<div class="csr-calbar csr-reveal">
			<div class="csr-filters" role="group" aria-label="Přepnout výpis závodů">
				<a class="csr-filter" href="<?php echo esc_url( get_permalink() ); ?>"
				   aria-pressed="<?php echo $csr_past ? 'false' : 'true'; ?>">Nadcházející</a>
				<a class="csr-filter" href="<?php echo esc_url( add_query_arg( 'zobrazit', 'minule', get_permalink() ) ); ?>"
				   aria-pressed="<?php echo $csr_past ? 'true' : 'false'; ?>">Proběhlé</a>
			</div>

			<?php if ( $csr_has_tec ) : ?>
				<a class="csr-btn csr-btn--ghost csr-btn--sm" href="<?php echo esc_url( home_url( '/akce/?ical=1' ) ); ?>">
					<svg class="csr-btn__ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M8 11l4 4 4-4M4 19h16"/></svg>
					Přidat do kalendáře
				</a>
			<?php endif; ?>
		</div>

		<?php if ( ! $csr_has_tec ) : ?>

			<div class="csr-cal__empty csr-reveal">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
				<h2>Kalendář není dostupný</h2>
				<p>Není aktivní plugin The Events Calendar, ze kterého se závody načítají.</p>
			</div>

		<?php elseif ( empty( $csr_items ) ) : ?>

			<div class="csr-cal__empty csr-reveal">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18M9 15h6"/></svg>
				<?php if ( $csr_past ) : ?>
					<h2>Žádné proběhlé závody</h2>
					<p>V archivu zatím nic není.</p>
				<?php else : ?>
					<h2>Zatím tu nejsou žádné nadcházející závody</h2>
					<p>Kalendář sezóny se připravuje. Mezitím si můžete projít
						<a href="<?php echo esc_url( add_query_arg( 'zobrazit', 'minule', get_permalink() ) ); ?>">proběhlé závody</a>.</p>
					<?php if ( current_user_can( 'edit_posts' ) ) : ?>
						<p><small>Závody přidáte v administraci v sekci <em>Akce → Vytvořit</em>.</small></p>
					<?php endif; ?>
				<?php endif; ?>
			</div>

		<?php else : ?>

			<?php
			$csr_month_open = false;
			$csr_prev_month = '';

			foreach ( $csr_items as $csr_ev ) {
				$csr_start = (int) tribe_get_start_date( $csr_ev, false, 'U' );
				$csr_end   = (int) tribe_get_end_date( $csr_ev, false, 'U' );
				$csr_month = wp_date( 'Y-m', $csr_start );

				// Nový měsíc → uzavřít předchozí seznam a otevřít nový.
				if ( $csr_month !== $csr_prev_month ) {
					if ( $csr_month_open ) {
						echo '</div>';
					}
					?>
					<div class="csr-cal__month csr-reveal">
						<h2><?php echo esc_html( wp_date( 'F Y', $csr_start ) ); ?></h2>
					</div>
					<div class="csr-cal__list" data-csr-stagger="60">
					<?php
					$csr_month_open = true;
					$csr_prev_month = $csr_month;
				}

				$csr_venue = function_exists( 'tribe_get_venue' ) ? tribe_get_venue( $csr_ev->ID ) : '';
				$csr_cats  = get_the_terms( $csr_ev->ID, 'tribe_events_cat' );
				$csr_thumb = get_the_post_thumbnail( $csr_ev->ID, 'medium', array( 'loading' => 'lazy', 'alt' => '' ) );
				?>

				<article class="csr-calevent<?php echo $csr_past ? ' csr-calevent--past' : ''; ?> csr-reveal">
					<div class="csr-calevent__date">
						<span class="csr-calevent__day"><?php echo esc_html( csr_event_day_label( $csr_start, $csr_end ) ); ?></span>
						<span class="csr-calevent__mon"><?php echo esc_html( wp_date( 'M', $csr_start ) ); ?></span>
					</div>

					<?php if ( $csr_thumb ) : ?>
						<div class="csr-calevent__thumb"><?php echo $csr_thumb; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<?php else : ?>
						<div class="csr-calevent__thumb csr-thumb-ph csr-thumb-ph--<?php echo (int) ( $csr_ev->ID % 8 ) + 1; ?>" aria-hidden="true"></div>
					<?php endif; ?>

					<div class="csr-calevent__body">
						<h3 class="csr-calevent__title">
							<a href="<?php echo esc_url( get_permalink( $csr_ev->ID ) ); ?>">
								<?php echo esc_html( get_the_title( $csr_ev->ID ) ); ?>
							</a>
						</h3>
						<div class="csr-calevent__meta">
							<span>
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
								<?php
								echo esc_html(
									wp_date( 'j. n.', $csr_start ) !== wp_date( 'j. n.', $csr_end )
										? wp_date( 'j. n.', $csr_start ) . ' – ' . wp_date( 'j. n. Y', $csr_end )
										: wp_date( 'j. n. Y', $csr_start )
								);
								?>
							</span>
							<?php if ( $csr_venue ) : ?>
								<span>
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/></svg>
									<?php echo esc_html( $csr_venue ); ?>
								</span>
							<?php endif; ?>
							<?php if ( $csr_cats && ! is_wp_error( $csr_cats ) ) : ?>
								<?php foreach ( array_slice( $csr_cats, 0, 2 ) as $csr_cat ) : ?>
									<span class="csr-chip <?php echo esc_attr( csr_chip_modifier( $csr_cat->slug ) ); ?>">
										<?php echo esc_html( $csr_cat->name ); ?>
									</span>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</div>

					<span class="csr-calevent__go" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
					</span>
				</article>

				<?php
			}

			if ( $csr_month_open ) {
				echo '</div>';
			}
			?>

			<?php
			$csr_pages = $csr_query ? (int) $csr_query->max_num_pages : 1;
			if ( $csr_pages > 1 ) :
				$csr_base = $csr_past ? add_query_arg( 'zobrazit', 'minule', get_permalink() ) : get_permalink();
				?>
				<nav class="csr-pager" aria-label="Stránkování závodů">
					<?php
					echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput — paginate_links escapuje sám
						array(
							'base'      => add_query_arg( 'strana', '%#%', $csr_base ),
							'format'    => '',
							'current'   => $csr_paged,
							'total'     => $csr_pages,
							'mid_size'  => 1,
							'prev_text' => '‹ Novější',
							'next_text' => 'Starší ›',
							'type'      => 'plain',
						)
					);
					?>
				</nav>
			<?php endif; ?>

		<?php endif; ?>

		<?php
		// Volný text napsaný do editoru stránky se vypíše pod kalendářem.
		while ( have_posts() ) :
			the_post();
			if ( trim( wp_strip_all_tags( get_the_content() ) ) ) :
				?>
				<div class="csr-prose csr-reveal"><?php the_content(); ?></div>
				<?php
			endif;
		endwhile;
		?>

	</div>
</section>

</main>

<?php get_template_part( 'template-parts/csr-footer' );
