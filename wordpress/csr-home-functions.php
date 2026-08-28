<?php
/**
 * Český svaz rychlobruslení — podpora pro novou úvodní stránku.
 *
 * Vložte do functions.php potomkovské šablony (child theme):
 *     require_once get_stylesheet_directory() . '/csr-home-functions.php';
 *
 * Předpokládá, že složka /assets/ z tohoto balíčku leží v potomkovské šabloně.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Nastavení úvodní stránky (panel v Customizeru).
require_once __DIR__ . '/csr-customizer.php';

// Databáze reprezentantů (typ obsahu, taxonomie, hromadné přidání).
require_once __DIR__ . '/csr-athletes.php';

// InfoFeed — oznámení s odkazy na dokumenty.
require_once __DIR__ . '/csr-infofeed.php';

// Výpis novinek za sezónu.
require_once __DIR__ . '/csr-news.php';

// Detail článku.
require_once __DIR__ . '/csr-article.php';

// Databáze klubů.
require_once __DIR__ . '/csr-clubs.php';

// Lidé ve svazu.
require_once __DIR__ . '/csr-people.php';

// Databáze dokumentů.
require_once __DIR__ . '/csr-documents.php';

// Fotogalerie (alba).
require_once __DIR__ . '/csr-gallery.php';

// Kontakty.
require_once __DIR__ . '/csr-contact.php';

// Výsledkové tabulky.
require_once __DIR__ . '/csr-results.php';

// České rekordy.
require_once __DIR__ . '/csr-records.php';

// Náborová stránka.
require_once __DIR__ . '/csr-landing.php';

// Čísla na úvodní stránce.
require_once __DIR__ . '/csr-stats.php';

const CSR_HOME_TEMPLATE   = 'page-csr-home.php';
const CSR_ROSTER_TEMPLATE = 'page-csr-roster.php';
const CSR_EVENTS_TEMPLATE = 'page-csr-events.php';
const CSR_FEED_TEMPLATE   = 'page-csr-infofeed.php';
const CSR_NEWS_TEMPLATE   = 'page-csr-news.php';
const CSR_ARTICLE_TEMPLATE = 'single-csr-article.php';
const CSR_CLUBS_TEMPLATE  = 'page-csr-clubs.php';
const CSR_STRUCT_TEMPLATE = 'page-csr-structure.php';
const CSR_DOCS_TEMPLATE   = 'page-csr-documents.php';
const CSR_GALLERY_TEMPLATE = 'page-csr-gallery.php';
const CSR_ALBUM_TEMPLATE   = 'single-csr-album.php';
const CSR_CONTACT_TEMPLATE = 'page-csr-contact.php';
const CSR_RESULTS_TEMPLATE = 'page-csr-results.php';
const CSR_RECORDS_TEMPLATE = 'page-csr-records.php';
const CSR_LANDING_TEMPLATE = 'page-csr-landing.php';

/**
 * Všechny šablony z tohoto balíčku. Podle nich se rozhoduje, kdy načíst styly.
 */
/**
 * Český počet: 1 fotka, 2–4 fotky, 5+ fotek.
 *
 * @param int    $n     Počet.
 * @param string $jedna Tvar pro 1.
 * @param string $malo  Tvar pro 2–4.
 * @param string $hodne Tvar pro 0 a 5+.
 * @return string Číslo i s tvarem.
 */
/**
 * Je stránka zamčená heslem?
 *
 * Šablony vypisují obsah z databáze (dokumenty, výsledky, alba…), ne
 * z těla stránky. Kdyby se na heslo neptaly, vypsaly by ho i tomu, kdo
 * heslo nezadal — ochrana by tím byla k ničemu.
 *
 * @return bool
 */
function csr_page_locked() {
	return post_password_required();
}

/**
 * Vykreslí formulář pro zadání hesla místo obsahu.
 */
function csr_render_password_form() {
	?>
	<section class="csr-section">
		<div class="csr-container">
			<div class="csr-cal__empty csr-reveal csr-locked">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
				<h2>Obsah je chráněný heslem</h2>
				<p>Tahle stránka je jen pro ty, kdo znají heslo.</p>
				<?php echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput — výstup WordPressu ?>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Je stránka postavená v Elementoru?
 *
 * @param int $post_id ID stránky.
 * @return bool
 */
function csr_built_with_elementor( $post_id ) {
	return 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true );
}

/**
 * Vypíše volný text stránky pod obsahem šablony.
 *
 * Elementor se věší na filtr the_content a vrací celou stránku, jak ji
 * sestavil. Kdybychom ho na elementorové stránce zavolali, vykreslila by
 * se pod novým vzhledem znovu celá stará stránka. Proto se u těch stránek
 * volný text přeskakuje — starý obsah zůstává uložený, jen se nezobrazuje.
 *
 * @param int $post_id ID stránky.
 */
function csr_page_prose( $post_id ) {
	if ( csr_built_with_elementor( $post_id ) ) {
		if ( current_user_can( 'edit_posts' ) ) {
			echo '<p class="csr-prose__note">Vidíte jen vy jako správce: '
				. 'obsah téhle stránky je uložený v Elementoru a nová šablona ho '
				. 'nezobrazuje. Nic se nesmazalo — po přepnutí šablony zpět na '
				. '<em>Výchozí</em> se objeví jako dřív.</p>';
		}
		return;
	}

	$content = get_post_field( 'post_content', $post_id );
	if ( ! trim( wp_strip_all_tags( $content ) ) ) {
		return;
	}
	?>
	<div class="csr-prose csr-reveal">
		<?php echo apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</div>
	<?php
}

/**
 * Adresa výpisu závodů, na kterou se dá odkazovat.
 *
 * Šablona kalendáře běží ve dvou situacích: na stránce, které je
 * přiřazená, a na archivu akcí, pokud si ho tak nastaví The Events
 * Calendar. Na archivu ale get_permalink() nic nevrátí — archiv není
 * stránka — a odkaz „Nadcházející" pak vede na prázdno, takže se
 * z proběhlých závodů nedá vrátit zpět.
 *
 * @return string
 */
function csr_events_base_url() {
	if ( is_page() ) {
		$url = get_permalink();
		if ( $url ) {
			return $url;
		}
	}
	if ( post_type_exists( 'tribe_events' ) ) {
		$url = get_post_type_archive_link( 'tribe_events' );
		if ( $url ) {
			return $url;
		}
	}
	return home_url( '/' );
}

/**
 * Odkaz na telefon. Plus na začátku necháváme — bez něj se číslo
 * ze zahraničí nemusí vytočit.
 *
 * @param string $phone Telefon v libovolném zápisu.
 * @return string
 */
function csr_tel_href( $phone ) {
	return 'tel:' . preg_replace( '/[^\d+]/', '', (string) $phone );
}

function csr_plural( $n, $jedna, $malo, $hodne ) {
	$n = (int) $n;
	if ( 1 === $n ) {
		return $n . ' ' . $jedna;
	}
	if ( $n >= 2 && $n <= 4 ) {
		return $n . ' ' . $malo;
	}
	return $n . ' ' . $hodne;
}

function csr_templates() {
	return array(
		CSR_HOME_TEMPLATE,
		CSR_ROSTER_TEMPLATE,
		CSR_EVENTS_TEMPLATE,
		CSR_FEED_TEMPLATE,
		CSR_NEWS_TEMPLATE,
		CSR_CLUBS_TEMPLATE,
		CSR_STRUCT_TEMPLATE,
		CSR_DOCS_TEMPLATE,
		CSR_GALLERY_TEMPLATE,
		CSR_CONTACT_TEMPLATE,
		CSR_RESULTS_TEMPLATE,
		CSR_RECORDS_TEMPLATE,
		CSR_LANDING_TEMPLATE,
	);
}

/**
 * Všechny soubory šablon z balíčku, včetně těch, které se nepřiřazují
 * stránce, ale nasazují se filtrem.
 *
 * @return array
 */
function csr_all_template_files() {
	return array_merge(
		csr_templates(),
		array( CSR_ARTICLE_TEMPLATE, CSR_ALBUM_TEMPLATE )
	);
}

/**
 * Zapamatuje si, kterou šablonu WordPress nakonec vybral.
 *
 * Běží dřív než wp_head(), takže se podle toho dá rozhodnout o načtení
 * stylů. Bez toho jsme se ptali jen is_page_template(), což platí pouze
 * pro stránky — na archivu akcí z The Events Calendar, který stránka
 * není, se proto styly nenačetly a zůstal z něj holý seznam odkazů,
 * přestože hlavička i patička se vykreslily.
 *
 * @param string $template Cesta k vybrané šabloně.
 * @return string Nezměněná cesta.
 */
function csr_remember_template( $template ) {
	$GLOBALS['csr_current_template'] = $template ? basename( $template ) : '';
	return $template;
}
// Priorita 999: až za našimi vlastními přesměrováními (článek, album)
// i za pluginy, ať vidíme skutečně poslední volbu.
add_filter( 'template_include', 'csr_remember_template', 999 );

/**
 * Běží právě některá z našich šablon?
 */
function csr_is_csr_template() {
	$aktualni = isset( $GLOBALS['csr_current_template'] ) ? $GLOBALS['csr_current_template'] : '';
	if ( $aktualni && in_array( $aktualni, csr_all_template_files(), true ) ) {
		return true;
	}
	// Záloha pro případ, že se filtr nestihl uplatnit.
	return is_page_template( csr_templates() ) || csr_is_article_view() || is_singular( 'csr_album' );
}
const CSR_HOME_VERSION  = '1.0.0';
/**
 * Kolik článků na stránku. Bere se z nastavení, konstanta je jen záloha.
 */
function csr_posts_per_page() {
	$count = (int) csr_opt( 'csr_news_count', 6 );
	return $count > 0 ? $count : 6;
}

/**
 * Zjistí, jestli se právě vykresluje nová úvodní stránka.
 */
function csr_is_home_template() {
	return is_page_template( CSR_HOME_TEMPLATE );
}

/* -------------------------------------------------------------------------
 * Styly a skripty
 * ---------------------------------------------------------------------- */

/**
 * Načte CSS a JS jen na stránce s naší šablonou, ať se zbytek webu nezpomaluje.
 */
function csr_home_enqueue_assets() {
	if ( ! csr_is_csr_template() ) {
		return;
	}

	$base = get_stylesheet_directory_uri() . '/assets';
	$dir  = get_stylesheet_directory() . '/assets';

	// Verzujeme podle času úpravy souboru — po nasazení se nemusí čistit cache.
	$css_ver = file_exists( "$dir/css/csr-home.css" ) ? filemtime( "$dir/css/csr-home.css" ) : CSR_HOME_VERSION;
	$js_ver  = file_exists( "$dir/js/csr-home.js" ) ? filemtime( "$dir/js/csr-home.js" ) : CSR_HOME_VERSION;

	wp_enqueue_style(
		'csr-fonts',
		'https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,600;0,700;1,600;1,700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap',
		array(),
		null
	);

	wp_enqueue_style( 'csr-home', "$base/css/csr-home.css", array(), $css_ver );
	wp_enqueue_script( 'csr-home', "$base/js/csr-home.js", array(), $js_ver, true );
}
add_action( 'wp_enqueue_scripts', 'csr_home_enqueue_assets', 20 );

/**
 * Na naší šabloně nepotřebujeme balast původního vzhledu.
 * Odhlásíme jen styly, které by se bily s novým layoutem.
 */
function csr_home_dequeue_conflicts() {
	if ( ! csr_is_csr_template() ) {
		return;
	}
	// Mega menu si stylujeme sami — původní CSS by přebíjelo novou navigaci.
	wp_dequeue_style( 'megamenu' );
	wp_dequeue_style( 'megamenu-genericons' );
}
add_action( 'wp_enqueue_scripts', 'csr_home_dequeue_conflicts', 100 );

/**
 * Předpřipojení k Google Fonts — ušetří pár desítek ms při prvním načtení.
 */
function csr_home_resource_hints( $hints, $relation ) {
	if ( 'preconnect' === $relation && csr_is_csr_template() ) {
		$hints[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $hints;
}
add_filter( 'wp_resource_hints', 'csr_home_resource_hints', 10, 2 );

/* -------------------------------------------------------------------------
 * Pomocné funkce
 * ---------------------------------------------------------------------- */

/**
 * Vrátí slugy kategorií příspěvku jako řetězec pro atribut data-csr-cat.
 */
function csr_post_category_slugs( $post_id ) {
	$terms = get_the_category( $post_id );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return '';
	}
	return implode( ' ', wp_list_pluck( $terms, 'slug' ) );
}

/**
 * Odhad doby čtení v minutách.
 */
function csr_reading_time( $post_id ) {
	// str_word_count() se o diakritiku neopře a česká slova by rozsekal.
	$text  = wp_strip_all_tags( get_post_field( 'post_content', $post_id ) );
	$words = preg_match_all( '/[\p{L}\p{N}]+/u', $text );
	return max( 1, (int) round( $words / 200 ) );
}

/**
 * Barevná varianta štítku podle kategorie.
 */
function csr_chip_modifier( $slug ) {
	$map = array(
		'speed-skating' => 'csr-chip--ss',
		'short-track'   => 'csr-chip--st',
		'reprezentace'  => 'csr-chip--ss',
		'vysledky'      => 'csr-chip--ss',
		'svaz'          => 'csr-chip--org',
		'antidoping'    => 'csr-chip--org',
	);
	return isset( $map[ $slug ] ) ? $map[ $slug ] : 'csr-chip--org';
}

/**
 * Vykreslí jednu kartu článku.
 * Používá ji šablona i AJAX dotazování, ať je markup vždy stejný.
 *
 * @param WP_Post $post    Příspěvek.
 * @param bool    $is_lead Má být karta velká (hlavní článek)?
 */
function csr_render_article_card( $post, $is_lead = false ) {
	$id    = $post->ID;
	$cats  = get_the_category( $id );
	$first = ! empty( $cats ) ? $cats[0] : null;
	$thumb = get_the_post_thumbnail(
		$id,
		$is_lead ? 'large' : 'medium_large',
		array(
			'loading'  => 'lazy',
			'decoding' => 'async',
			'alt'      => '',
		)
	);
	?>
	<article class="csr-news__item<?php echo $is_lead ? ' csr-news__item--lead' : ''; ?> csr-reveal"
	         data-csr-cat="<?php echo esc_attr( csr_post_category_slugs( $id ) ); ?>">
		<div class="csr-card">
			<div class="csr-card__media">
				<?php if ( $thumb ) : ?>
					<?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput — výstup get_the_post_thumbnail je bezpečný ?>
				<?php else : ?>
					<div class="csr-thumb-ph csr-thumb-ph--<?php echo (int) ( $id % 8 ) + 1; ?>" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M4 17c5 1.6 10.5.4 14-3s5-9 4.6-13"/><path d="M5 20h13"/></svg>
					</div>
				<?php endif; ?>

				<?php if ( $first ) : ?>
					<span class="csr-chip <?php echo esc_attr( csr_chip_modifier( $first->slug ) ); ?> csr-card__chip">
						<?php echo esc_html( $first->name ); ?>
					</span>
				<?php endif; ?>
			</div>

			<div class="csr-card__body">
				<div class="csr-card__meta">
					<time datetime="<?php echo esc_attr( get_the_date( 'c', $id ) ); ?>">
						<?php echo esc_html( get_the_date( 'j. F Y', $id ) ); ?>
					</time>
					<?php if ( $is_lead ) : ?>
						<span>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 2"/></svg>
							<?php echo esc_html( sprintf( '%d min čtení', csr_reading_time( $id ) ) ); ?>
						</span>
					<?php endif; ?>
				</div>

				<h3 class="csr-card__title">
					<a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a>
				</h3>

				<p class="csr-card__excerpt">
					<?php echo esc_html( wp_trim_words( get_the_excerpt( $id ), $is_lead ? 34 : 20, '…' ) ); ?>
				</p>

				<?php if ( $is_lead ) : ?>
					<div class="csr-card__foot">
						<span class="csr-arrowlink">
							Číst dál
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
						</span>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</article>
	<?php
}

/* -------------------------------------------------------------------------
 * AJAX — načítání dalších článků
 * ---------------------------------------------------------------------- */

/**
 * Vrátí další stránku článků jako HTML fragment.
 * Odpovídá na /?csr_more=1&csr_page=N
 */
function csr_ajax_load_more() {
	if ( ! isset( $_GET['csr_more'] ) ) {
		return;
	}

	$page = isset( $_GET['csr_page'] ) ? max( 2, (int) $_GET['csr_page'] ) : 2;

	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => csr_posts_per_page(),
			'paged'               => $page,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		)
	);

	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			csr_render_article_card( get_post(), false );
		}
	}
	wp_reset_postdata();

	exit;
}
add_action( 'template_redirect', 'csr_ajax_load_more' );

/* -------------------------------------------------------------------------
 * Nastavení
 * ---------------------------------------------------------------------- */

/**
 * Zaregistruje pozice menu, které šablona používá.
 */
function csr_register_menus() {
	register_nav_menus(
		array(
			'csr_primary'    => 'ČSR — hlavní navigace',
			'csr_footer_org' => 'ČSR — patička: Svaz',
			'csr_footer_sport' => 'ČSR — patička: Sport',
		)
	);
}
add_action( 'after_setup_theme', 'csr_register_menus' );

/**
 * Delší úryvky pro karty článků.
 */
function csr_excerpt_length( $length ) {
	return csr_is_home_template() ? 34 : $length;
}
add_filter( 'excerpt_length', 'csr_excerpt_length', 999 );

/**
 * Odpojí filtry, kterými pluginy přepisují výpis menu.
 *
 * Max Mega Menu se věší na wp_nav_menu_args a vrátí vlastní strukturu
 * s vlastními třídami. Naše CSS na ni neplatí a navigace z hlavičky
 * úplně zmizí. Na dobu vlastního výpisu proto ty filtry odpojíme
 * a hned zase vrátíme, ať zbytek webu funguje jako dřív.
 *
 * @return array Záloha filtrů pro csr_reattach_menu_filters().
 */
function csr_detach_menu_filters() {
	if ( ! apply_filters( 'csr_isolate_nav_menu', true ) ) {
		return array();
	}

	global $wp_filter;
	$zaloha = array();
	foreach ( array( 'wp_nav_menu_args', 'wp_nav_menu' ) as $hook ) {
		if ( isset( $wp_filter[ $hook ] ) ) {
			$zaloha[ $hook ] = $wp_filter[ $hook ];
			unset( $wp_filter[ $hook ] );
		}
	}
	return $zaloha;
}

/**
 * Vrátí zpět filtry odpojené v csr_detach_menu_filters().
 *
 * @param array $zaloha Návratová hodnota csr_detach_menu_filters().
 */
function csr_reattach_menu_filters( $zaloha ) {
	global $wp_filter;
	foreach ( $zaloha as $hook => $filtry ) {
		$wp_filter[ $hook ] = $filtry;
	}
}

/**
 * Vypíše hlavní navigaci.
 * Zkusí postupně naši pozici, pak stávající "primary", pak seznam stránek —
 * aby web nezůstal bez menu, i když se pozice po nasazení nepřiřadí.
 *
 * @param string $ul_class Třída pro <ul>.
 */
function csr_nav_menu( $ul_class = 'csr-nav__list' ) {
	$location = null;
	foreach ( array( 'csr_primary', 'primary', 'main' ) as $candidate ) {
		if ( has_nav_menu( $candidate ) ) {
			$location = $candidate;
			break;
		}
	}

	if ( ! $location ) {
		wp_page_menu(
			array(
				'menu_class'  => $ul_class,
				'container'   => '',
				'depth'       => 2,
				'echo'        => true,
				'before'      => '',
				'after'       => '',
			)
		);
		return;
	}

	$zaloha = csr_detach_menu_filters();

	wp_nav_menu(
		array(
			'theme_location' => $location,
			'container'      => false,
			'menu_class'     => $ul_class,
			// Menu svazu je čtyřúrovňové (Reprezentace → sezóna → disciplína
			// → soupiska). Při menší hloubce by chyběly právě ty odkazy,
			// kvůli kterým tam menu je.
			'depth'          => (int) apply_filters( 'csr_nav_depth', 4 ),
			'fallback_cb'    => false,
		)
	);

	csr_reattach_menu_filters( $zaloha );
}

/**
 * Vypíše menu v patičce podle pozice, s tichým selháním.
 */
function csr_footer_menu( $location ) {
	if ( ! has_nav_menu( $location ) ) {
		return false;
	}
	$zaloha = csr_detach_menu_filters();
	wp_nav_menu(
		array(
			'theme_location' => $location,
			'container'      => false,
			'menu_class'     => '',
			'depth'          => 1,
			'fallback_cb'    => false,
		)
	);
	csr_reattach_menu_filters( $zaloha );
	return true;
}

/**
 * Doplní doménu k relativnímu odkazu z nastavení.
 * Kotvy (#sekce), absolutní adresy, mailto: a tel: nechá být.
 *
 * @param string $url Hodnota z Customizeru.
 */
function csr_link( $url ) {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '#';
	}
	if ( 0 === strpos( $url, '#' ) ) {
		return $url;
	}
	if ( preg_match( '#^(https?:)?//#i', $url ) ) {
		return $url;
	}
	if ( 0 === strpos( $url, 'mailto:' ) || 0 === strpos( $url, 'tel:' ) ) {
		return $url;
	}

	return home_url( '/' . ltrim( $url, '/' ) );
}

/**
 * Datum závodu jako den nebo rozsah dnů („13", „13–15").
 *
 * @param int $start Začátek (unix).
 * @param int $end   Konec (unix).
 */
function csr_event_day_label( $start, $end ) {
	$d1 = wp_date( 'j', $start );
	$d2 = wp_date( 'j', $end );

	// Jednodenní závod.
	if ( $d1 === $d2 ) {
		return $d1;
	}

	// Přes přelom měsíce dává rozsah nesmysl („27–1"), proto jen začátek.
	// Celý rozsah je stejně vypsaný v řádku s podrobnostmi.
	if ( wp_date( 'Y-m', $start ) !== wp_date( 'Y-m', $end ) ) {
		return $d1;
	}

	return $d1 . '–' . $d2;
}
