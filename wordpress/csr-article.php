<?php
/**
 * Detail článku.
 *
 * Nahrazuje výpis příspěvku z rodičovské šablony. Zapíná se přepínačem
 * v Customizeru (Vzhled → Přizpůsobit → ČSR: Články), takže se dá kdykoli
 * vrátit původní vzhled bez zásahu do kódu.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Přepnutí šablony
 * ---------------------------------------------------------------------- */

/**
 * Podstrčí naši šablonu místo rodičovské single.php.
 *
 * @param string $template Cesta k šabloně, kterou vybral WordPress.
 * @return string
 */
function csr_article_template( $template ) {
	if ( ! is_singular( 'post' ) || ! csr_opt( 'csr_article_enable', 1 ) ) {
		return $template;
	}
	$file = get_stylesheet_directory() . '/' . CSR_ARTICLE_TEMPLATE;
	return file_exists( $file ) ? $file : $template;
}
add_filter( 'template_include', 'csr_article_template', 99 );

/**
 * Vykresluje se právě článek v našem vzhledu?
 */
function csr_is_article_view() {
	return is_singular( 'post' ) && (bool) csr_opt( 'csr_article_enable', 1 );
}

/* -------------------------------------------------------------------------
 * Náprava zalomení textu
 * ---------------------------------------------------------------------- */

/**
 * Spojí text natvrdo zalomený značkami <br> zpět do odstavců.
 *
 * Články na webu vznikaly kopírováním z Wordu nebo PDF, takže je celý text
 * jeden odstavec plný <br> — a to i uprostřed vět. Tady se řádky slepí zpátky
 * a odstavec se ukončí tam, kde byl řádek zjevně kratší než ostatní
 * a končil tečkou. Původní obsah v databázi zůstává nedotčený.
 *
 * @param string $html Obsah příspěvku.
 * @return string
 */
function csr_unwrap_hard_breaks( $html ) {
	if ( false === strpos( $html, '<br' ) ) {
		return $html;
	}

	return preg_replace_callback(
		'#<p([^>]*)>(.*?)</p>#is',
		'csr_unwrap_paragraph',
		$html
	);
}

/**
 * Zpracuje jeden odstavec. Když si nejsme jistí, necháme ho být.
 *
 * @param array $match Výsledek preg_replace_callback.
 * @return string
 */
function csr_unwrap_paragraph( $match ) {
	$attr = $match[1];
	$body = $match[2];

	// Obrázky a vložený obsah necháváme na pokoji.
	if ( preg_match( '#<(img|iframe|video|audio|figure|div)\b#i', $body ) ) {
		return $match[0];
	}

	$lines = preg_split( '#\s*<br[^>]*>\s*#i', $body );
	if ( count( $lines ) < 4 ) {
		return $match[0];
	}

	// Délku měříme na čistém textu bez značek a s rozkódovanými entitami.
	$plain   = array();
	$longest = 0;
	foreach ( $lines as $line ) {
		$text     = trim( html_entity_decode( wp_strip_all_tags( $line ), ENT_QUOTES, 'UTF-8' ) );
		$plain[]  = $text;
		$longest  = max( $longest, csr_strlen( $text ) );
	}

	// Krátký text může být adresa nebo podpis — tam zalomení dává smysl.
	if ( $longest < 40 || array_sum( array_map( 'csr_strlen', $plain ) ) < 200 ) {
		return $match[0];
	}

	$threshold  = $longest * 0.8;
	$paragraphs = array();
	$buffer     = array();

	foreach ( $lines as $i => $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$buffer[] = $line;

		$text  = $plain[ $i ];
		$short = csr_strlen( $text ) < $threshold;
		$ends  = (bool) preg_match( '/[.!?\x{2026}][")\x{201C}\x{00BB}\x{2019}]?$/u', $text );

		// Konec odstavce: řádek je zřetelně kratší než ostatní a končí větou.
		if ( ( $short && $ends ) || count( $lines ) - 1 === $i ) {
			$paragraphs[] = implode( ' ', $buffer );
			$buffer       = array();
		}
	}
	if ( $buffer ) {
		$paragraphs[] = implode( ' ', $buffer );
	}

	// Jediný odstavec = heuristika nic nenašla, vracíme původní podobu.
	if ( count( $paragraphs ) < 2 ) {
		return $match[0];
	}

	$out = '';
	foreach ( $paragraphs as $p ) {
		$out .= '<p' . $attr . '>' . $p . '</p>';
	}
	return $out;
}

/**
 * Délka řetězce v znacích, ne v bajtech — kvůli diakritice.
 *
 * @param string $text Text.
 * @return int
 */
function csr_strlen( $text ) {
	return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
}

/**
 * Napojení na obsah. Priorita 999, ať to běží až po Elementoru.
 *
 * @param string $content Obsah příspěvku.
 * @return string
 */
function csr_article_content_filter( $content ) {
	if ( ! in_the_loop() || ! is_main_query() || ! csr_is_article_view() ) {
		return $content;
	}
	if ( ! csr_opt( 'csr_article_unwrap', 1 ) ) {
		return $content;
	}
	return csr_unwrap_hard_breaks( $content );
}
add_filter( 'the_content', 'csr_article_content_filter', 999 );

/* -------------------------------------------------------------------------
 * Doplňky článku
 * ---------------------------------------------------------------------- */

/**
 * Odkazy pro sdílení. Žádné cizí skripty — jen obyčejné odkazy.
 *
 * @param int $post_id ID příspěvku.
 * @return array
 */
function csr_share_links( $post_id ) {
	$url   = rawurlencode( get_permalink( $post_id ) );
	$title = rawurlencode( get_the_title( $post_id ) );

	return array(
		array(
			'label' => 'Sdílet na Facebooku',
			'short' => 'Facebook',
			'url'   => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
			'icon'  => '<path d="M14 8.5V7c0-.8.2-1.2 1.3-1.2H17V3h-2.6C11.6 3 11 4.5 11 6.5v2H9v3h2v9h3v-9h2.3l.3-3H14Z"/>',
		),
		array(
			'label' => 'Sdílet na X',
			'short' => 'X',
			'url'   => 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title,
			'icon'  => '<path d="M3 3h4.5l4.3 6 5-6H20l-6.8 8.1L21 21h-4.5l-4.6-6.4L6.4 21H4l7.2-8.6L3 3Z"/>',
		),
		array(
			'label' => 'Poslat e-mailem',
			'short' => 'E-mail',
			'url'   => 'mailto:?subject=' . $title . '&body=' . $url,
			'icon'  => '<path d="M3 6h18v12H3z"/><path d="m3 7 9 6 9-6"/>',
		),
	);
}

/**
 * Články ze stejné rubriky.
 *
 * @param int $post_id ID příspěvku.
 * @param int $count   Kolik jich vrátit.
 * @return WP_Query
 */
function csr_related_query( $post_id, $count = 3 ) {
	$cats = wp_get_post_categories( $post_id );

	$args = array(
		'post_type'           => 'post',
		'posts_per_page'      => $count,
		'post__not_in'        => array( $post_id ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	if ( $cats ) {
		$args['category__in'] = $cats;
	}

	$query = new WP_Query( $args );

	// Ve stejné rubrice nic není — doplníme nejnovějšími články.
	if ( ! $query->have_posts() && $cats ) {
		unset( $args['category__in'] );
		$query = new WP_Query( $args );
	}
	return $query;
}

/**
 * Sousední příspěvek jako karta pro navigaci pod článkem.
 *
 * @param bool $previous Předchozí (true) nebo následující (false).
 * @return void
 */
function csr_render_adjacent( $previous = true ) {
	$post = get_adjacent_post( false, '', $previous );
	if ( ! $post ) {
		return;
	}
	$dir   = $previous ? 'prev' : 'next';
	$label = $previous ? 'Předchozí článek' : 'Následující článek';
	$arrow = $previous ? 'M19 12H5M11 6l-6 6 6 6' : 'M5 12h14M13 6l6 6-6 6';
	?>
	<a class="csr-adjacent csr-adjacent--<?php echo esc_attr( $dir ); ?>" href="<?php echo esc_url( get_permalink( $post ) ); ?>" rel="<?php echo $previous ? 'prev' : 'next'; ?>">
		<span class="csr-adjacent__label">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?php echo esc_attr( $arrow ); ?>"/></svg>
			<?php echo esc_html( $label ); ?>
		</span>
		<span class="csr-adjacent__title"><?php echo esc_html( get_the_title( $post ) ); ?></span>
	</a>
	<?php
}
