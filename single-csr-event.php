<?php
/**
 * Detail závodu z The Events Calendar.
 *
 * Nasazuje se filtrem — závod není stránka, takže se k němu šablona
 * nedá přiřadit ručně. Údaje čteme z polí pluginu, ale tak, aby stránka
 * nespadla, kdyby ho někdo vypnul.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/csr-header' );

the_post();
$csr_id   = get_the_ID();
$csr_akce = csr_event_details( $csr_id );
$csr_dals = csr_next_events( $csr_id, 3 );
$csr_probehl = $csr_akce['end'] && $csr_akce['end'] < current_time( 'timestamp' );

/** Datum závodu, jednodenní i vícedenní. */
$csr_kdy = '';
if ( $csr_akce['start'] ) {
	$csr_kdy = wp_date( 'j. n. Y', $csr_akce['start'] );
	if ( $csr_akce['end'] && wp_date( 'Y-m-d', $csr_akce['end'] ) !== wp_date( 'Y-m-d', $csr_akce['start'] ) ) {
		$csr_kdy .= ' – ' . wp_date( 'j. n. Y', $csr_akce['end'] );
	} elseif ( ! $csr_akce['cely_den'] ) {
		$csr_kdy .= ', ' . wp_date( 'G:i', $csr_akce['start'] );
	}
}
?>

<main id="obsah">

<section class="csr-pagehead">
	<div class="csr-pagehead__glow" aria-hidden="true"></div>
	<div class="csr-container csr-pagehead__inner">
		<nav class="csr-crumbs" aria-label="Drobečková navigace">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Úvod</a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( csr_events_base_url() ); ?>">Kalendář</a>
			<span aria-hidden="true">/</span>
			<span><?php echo esc_html( get_the_title() ); ?></span>
		</nav>

		<div class="csr-pagehead__meta">
			<?php if ( $csr_probehl ) : ?>
				<span class="csr-chip csr-chip--org">Proběhlo</span>
			<?php elseif ( $csr_kdy ) : ?>
				<span class="csr-chip csr-chip--ss">Nadcházející závod</span>
			<?php endif; ?>
			<?php
			foreach ( (array) get_the_terms( $csr_id, 'tribe_events_cat' ) as $csr_t ) {
				if ( $csr_t instanceof WP_Term ) {
					printf( '<span class="csr-chip csr-chip--solid">%s</span>', esc_html( $csr_t->name ) );
				}
			}
			?>
		</div>

		<h1 class="csr-pagehead__title"><?php the_title(); ?></h1>
	</div>
</section>

<section class="csr-section">
	<div class="csr-container">
		<div class="csr-event">

			<div class="csr-event__main">
				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="csr-event__photo">
						<?php the_post_thumbnail( 'large', array( 'alt' => '' ) ); ?>
					</figure>
				<?php endif; ?>

				<?php
				$csr_telo = trim( wp_strip_all_tags( get_the_content() ) );
				if ( $csr_telo ) :
					?>
					<div class="csr-prose"><?php the_content(); ?></div>
				<?php else : ?>
					<p class="csr-event__nodesc">K závodu zatím není popis. Podrobnosti bývají v propozicích u dokumentů.</p>
				<?php endif; ?>
			</div>

			<aside class="csr-event__side">
				<dl class="csr-event__facts">
					<?php if ( $csr_kdy ) : ?>
						<div><dt>Kdy</dt><dd><?php echo esc_html( $csr_kdy ); ?></dd></div>
					<?php endif; ?>
					<?php if ( $csr_akce['misto'] ) : ?>
						<div>
							<dt>Kde</dt>
							<dd>
								<?php echo esc_html( $csr_akce['misto'] ); ?>
								<?php if ( $csr_akce['adresa'] ) : ?>
									<br><span class="csr-event__addr"><?php echo esc_html( wp_strip_all_tags( $csr_akce['adresa'] ) ); ?></span>
									<br><a href="<?php echo esc_url( csr_map_href( $csr_akce['misto'] . ' ' . wp_strip_all_tags( $csr_akce['adresa'] ) ) ); ?>"
									       target="_blank" rel="noopener noreferrer">Zobrazit na mapě</a>
								<?php endif; ?>
							</dd>
						</div>
					<?php endif; ?>
					<?php if ( $csr_akce['poradatel'] ) : ?>
						<div><dt>Pořadatel</dt><dd><?php echo esc_html( $csr_akce['poradatel'] ); ?></dd></div>
					<?php endif; ?>
				</dl>

				<div class="csr-event__actions">
					<?php if ( $csr_akce['web'] ) : ?>
						<a class="csr-btn csr-btn--primary csr-btn--sm" href="<?php echo esc_url( $csr_akce['web'] ); ?>" target="_blank" rel="noopener noreferrer">
							Web závodu
						</a>
					<?php endif; ?>
					<?php if ( ! $csr_probehl && $csr_akce['start'] ) : ?>
						<a class="csr-btn csr-btn--ghost csr-btn--sm" href="<?php echo esc_url( add_query_arg( 'ical', '1', get_permalink() ) ); ?>">
							<svg class="csr-btn__ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M8 11l4 4 4-4M4 19h16"/></svg>
							Přidat do kalendáře
						</a>
					<?php endif; ?>
				</div>
			</aside>

		</div>

		<?php if ( $csr_dals ) : ?>
			<nav class="csr-seasons csr-reveal" aria-label="Další závody">
				<h2 class="csr-seasons__title">Nejbližší další závody</h2>
				<ul class="csr-seasons__list">
					<?php foreach ( $csr_dals as $csr_e ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $csr_e->ID ) ); ?>"><?php echo esc_html( get_the_title( $csr_e->ID ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>

	</div>
</section>

</main>

<?php get_template_part( 'template-parts/csr-footer' );
