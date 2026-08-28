<?php
/**
 * Template Name: ČSR — Dokumenty
 *
 * Výpis dokumentů z databáze s hledáním a filtrem podle rubriky.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_template_part( 'template-parts/csr-header' );

// Stránka může být omezená na jednu rubriku (Pravidla ISU, Smlouvy, Archiv…).
$csr_only = csr_docs_page_type( get_the_ID() );
$csr_docs = csr_get_documents( $csr_only );

// Rubriky stavíme jen z těch, které jsou opravdu použité.
$csr_types = array();
$csr_ext   = 0;
$csr_shown = 0;
foreach ( $csr_docs as $csr_d ) {
	$csr_file = csr_document_file( $csr_d->ID );
	if ( ! $csr_file['url'] ) {
		continue;
	}
	$csr_shown++;
	if ( ! $csr_file['local'] ) {
		$csr_ext++;
	}
	foreach ( (array) wp_get_object_terms( $csr_d->ID, 'csr_doctype', array( 'fields' => 'names' ) ) as $csr_t ) {
		$csr_types[ sanitize_title( $csr_t ) ] = $csr_t;
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
			<span><?php echo esc_html( get_the_title() ); ?></span>
		</nav>
		<h1 class="csr-pagehead__title"><?php echo esc_html( get_the_title() ); ?></h1>
		<?php if ( $csr_shown ) : ?>
			<div class="csr-pagehead__meta">
				<span class="csr-chip csr-chip--solid">
					<?php
					if ( 1 === $csr_shown ) {
						echo '1 dokument';
					} elseif ( $csr_shown < 5 ) {
						echo esc_html( $csr_shown ) . ' dokumenty';
					} else {
						echo esc_html( $csr_shown ) . ' dokumentů';
					}
					?>
				</span>
			</div>
		<?php endif; ?>
		<?php
		$csr_lead = csr_opt( 'csr_docs_lead', '' );
		if ( $csr_lead ) :
			?>
			<p class="csr-pagehead__lead"><?php echo esc_html( $csr_lead ); ?></p>
		<?php endif; ?>
	</div>
</section>

<!-- ══════════ VÝPIS ══════════ -->
<section class="csr-section">
	<div class="csr-container">

		<?php if ( $csr_shown ) : ?>

			<div class="csr-calbar csr-reveal">
				<label class="csr-clubsearch" data-csr-docsearch>
					<span class="screen-reader-text">Hledat dokument</span>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
					<input type="search" placeholder="Hledat v názvech dokumentů…" autocomplete="off">
				</label>

				<?php if ( ! $csr_only && count( $csr_types ) > 1 ) : ?>
					<div class="csr-filters" data-csr-docfilters role="group" aria-label="Filtrovat podle rubriky">
						<button class="csr-filter" type="button" data-csr-filter="all" aria-pressed="true">Vše</button>
						<?php foreach ( $csr_types as $csr_slug => $csr_name ) : ?>
							<button class="csr-filter" type="button" data-csr-filter="<?php echo esc_attr( $csr_slug ); ?>" aria-pressed="false">
								<?php echo esc_html( $csr_name ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<ul class="csr-docs" data-csr-docs data-csr-stagger="45">
				<?php
				foreach ( $csr_docs as $csr_d ) {
					csr_render_document( $csr_d );
				}
				?>
			</ul>
			<p class="csr-news__empty" data-csr-docempty hidden>Tomuhle hledání neodpovídá žádný dokument.</p>

			<?php if ( $csr_ext && current_user_can( 'edit_posts' ) ) : ?>
				<p class="csr-docs__admin">
					Vidíte jen vy jako správce: <strong><?php echo (int) $csr_ext; ?></strong>
					<?php echo 1 === $csr_ext ? 'dokument leží' : 'dokumentů leží'; ?>
					na cizím úložišti. Nahrajte soubory do knihovny médií a vyberte je u dokumentu —
					jinak zmizí, až služba skončí.
				</p>
			<?php endif; ?>

		<?php else : ?>

			<div class="csr-cal__empty csr-reveal">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>
				<h2>Zatím tu nejsou žádné dokumenty</h2>
				<?php if ( current_user_can( 'edit_posts' ) ) : ?>
					<p><small>Dokumenty se přidávají v administraci v sekci <em>Dokumenty</em>.</small></p>
				<?php endif; ?>
			</div>

		<?php endif; ?>

		<?php
		csr_page_prose( get_the_ID() );
		?>

	</div>
</section>

</main>

<?php get_template_part( 'template-parts/csr-footer' );
