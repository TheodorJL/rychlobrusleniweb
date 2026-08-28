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

/* -------------------------------------------------------------------------
 * Rozebrání obsahu článku
 *
 * Články se na starém webu psaly v Elementoru, takže the_content() vrátí
 * celou jeho konstrukci — sekce, sloupce, widgety. Do našeho designu se to
 * nehodí a obrázky v tom zůstanou úplně bez úpravy. Vytáhneme si z toho
 * proto jen to podstatné (text a obrázky) a vykreslíme si to sami.
 * Funguje to stejně i na článcích psaných normálním editorem.
 * ---------------------------------------------------------------------- */

/**
 * Rozebere hotové HTML článku na posloupnost bloků.
 *
 * Vrací pole položek v pořadí, v jakém byly v článku:
 *   array( 'typ' => 'text',    'html' => '<p>…</p>' )
 *   array( 'typ' => 'obrazky', 'polozky' => array( … ) )
 *   array( 'typ' => 'vlozeny', 'html' => '<iframe …>' )
 * Obrázky, které v článku stály za sebou, se slučují do jedné položky,
 * aby se z nich dala poskládat mřížka.
 *
 * @param string $html Vykreslený obsah článku.
 * @return array Bloky, nebo prázdné pole, když se nedá nic vytáhnout.
 */
function csr_article_extract( $html ) {
	$html = trim( (string) $html );
	if ( '' === $html || ! class_exists( 'DOMDocument' ) ) {
		return array();
	}

	$dom   = new DOMDocument();
	$stare = libxml_use_internal_errors( true );

	$vlajky = 0;
	if ( defined( 'LIBXML_HTML_NOIMPLIED' ) && defined( 'LIBXML_HTML_NODEFDTD' ) ) {
		$vlajky = LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD;
	}
	// Bez deklarace kódování by DOMDocument četl vstup jako latin-1.
	$nacteno = $dom->loadHTML( '<?xml encoding="utf-8" ?><div id="csr-korel">' . $html . '</div>', $vlajky );

	libxml_clear_errors();
	libxml_use_internal_errors( $stare );

	if ( ! $nacteno ) {
		return array();
	}

	$xpath = new DOMXPath( $dom );
	$uzly  = $xpath->query( '//p | //h1 | //h2 | //h3 | //h4 | //h5 | //h6 | //ul | //ol | //blockquote | //table | //pre | //img | //iframe | //video' );
	if ( ! $uzly ) {
		return array();
	}

	$bloky  = array();
	$hotove = array();

	foreach ( $uzly as $uzel ) {
		if ( csr_node_uvnitr( $uzel, $hotove ) ) {
			continue;
		}
		$hotove[] = $uzel;

		$jmeno = strtolower( $uzel->nodeName );

		if ( 'img' === $jmeno ) {
			csr_article_push_image( $bloky, csr_article_image_data( $uzel ) );
			continue;
		}

		if ( 'iframe' === $jmeno || 'video' === $jmeno ) {
			$bloky[] = array( 'typ' => 'vlozeny', 'html' => $dom->saveHTML( $uzel ) );
			continue;
		}

		$text     = trim( preg_replace( '/\s+/u', ' ', $uzel->textContent ) );
		$obrazky  = $uzel->getElementsByTagName( 'img' );
		$jen_foto = ( '' === $text || '&nbsp;' === $text ) && $obrazky->length;

		// Odstavec, ve kterém je jen obrázek, je obrázek — ne text.
		if ( $jen_foto ) {
			foreach ( $obrazky as $obrazek ) {
				csr_article_push_image( $bloky, csr_article_image_data( $obrazek ) );
			}
			continue;
		}

		if ( '' === $text ) {
			continue;
		}

		$bloky[] = array( 'typ' => 'text', 'html' => $dom->saveHTML( $uzel ) );
	}

	return $bloky;
}

/**
 * Leží uzel uvnitř něčeho, co už jsme zpracovali?
 *
 * XPath vrací i vnořené uzly — odstavec uvnitř citace by se jinak
 * vykreslil dvakrát.
 *
 * @param DOMNode $uzel   Zkoumaný uzel.
 * @param array   $hotove Už zpracované uzly.
 * @return bool
 */
function csr_node_uvnitr( $uzel, $hotove ) {
	for ( $rodic = $uzel->parentNode; $rodic; $rodic = $rodic->parentNode ) {
		foreach ( $hotove as $hotovy ) {
			if ( $rodic->isSameNode( $hotovy ) ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Přidá obrázek — a když předchozí blok byly taky obrázky, připojí ho k nim.
 *
 * @param array $bloky   Seznam bloků, mění se na místě.
 * @param array $obrazek Údaje o obrázku.
 */
function csr_article_push_image( &$bloky, $obrazek ) {
	if ( empty( $obrazek['url'] ) ) {
		return;
	}

	$posledni = count( $bloky ) - 1;
	if ( $posledni >= 0 && 'obrazky' === $bloky[ $posledni ]['typ'] ) {
		// Tentýž obrázek dvakrát za sebou nemá smysl.
		foreach ( $bloky[ $posledni ]['polozky'] as $uz_mame ) {
			if ( $uz_mame['url'] === $obrazek['url'] ) {
				return;
			}
		}
		$bloky[ $posledni ]['polozky'][] = $obrazek;
		return;
	}

	$bloky[] = array( 'typ' => 'obrazky', 'polozky' => array( $obrazek ) );
}

/**
 * Vytáhne z <img> vše, co potřebujeme k pěknému vykreslení.
 *
 * @param DOMElement $uzel Uzel obrázku.
 * @return array
 */
function csr_article_image_data( $uzel ) {
	$src = $uzel->getAttribute( 'src' );
	if ( '' === $src ) {
		return array();
	}

	// Elementor i editor píšou do třídy ID přílohy — díky němu umíme
	// sáhnout po originále a po popisku z knihovny médií.
	$id = 0;
	if ( preg_match( '/wp-image-(\d+)/', $uzel->getAttribute( 'class' ), $shoda ) ) {
		$id = (int) $shoda[1];
	}
	if ( ! $id ) {
		$id = csr_attachment_from_url( $src );
	}

	$sirka = (int) $uzel->getAttribute( 'width' );
	$vyska = (int) $uzel->getAttribute( 'height' );

	if ( $id ) {
		$meta = wp_get_attachment_metadata( $id );
		if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			$sirka = (int) $meta['width'];
			$vyska = (int) $meta['height'];
		}
		$vetsi = wp_get_attachment_image_url( $id, 'large' );
		if ( $vetsi ) {
			$src = $vetsi;
		}
	}

	return array(
		'id'      => $id,
		'url'     => $src,
		'alt'     => $uzel->getAttribute( 'alt' ),
		'sirka'   => $sirka,
		'vyska'   => $vyska,
		'navysku' => $sirka && $vyska && $vyska > $sirka,
		'popis'   => $id ? wp_get_attachment_caption( $id ) : '',
	);
}

/* -------------------------------------------------------------------------
 * Vykreslení rozebraného obsahu
 * ---------------------------------------------------------------------- */

/**
 * Vykreslí obsah článku nebo stránky po našem.
 *
 * @param string $html      Hotové HTML obsahu.
 * @param int    $bez_fotky ID náhledového obrázku, který už je v záhlaví.
 */
function csr_render_story( $html, $bez_fotky = 0 ) {
	$bloky = csr_article_extract( $html );

	// Když se rozebrat nedá, radši vypíšeme obsah tak, jak je.
	if ( ! $bloky ) {
		echo '<div class="csr-prose csr-prose--article csr-reveal">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput — obsah z redakce, prošel the_content
		return;
	}

	$bloky = csr_article_group_results( $bloky );

	/*
	 * Text sbíráme do jednoho obalu. Kdyby měl každý odstavec svůj,
	 * vznikla by mezi nimi dvojitá mezera a pravidlo pro úvodní odstavec
	 * by zvětšilo úplně každý.
	 */
	$prosa = '';
	$prvni = true;
	$vysyp = function () use ( &$prosa, &$prvni ) {
		if ( '' === $prosa ) {
			return;
		}
		// Zvětšený úvodní odstavec patří jen na začátek, ne za každou fotku.
		$trida = 'csr-prose csr-prose--article csr-reveal' . ( $prvni ? ' csr-prose--lead' : '' );
		echo '<div class="' . esc_attr( $trida ) . '">' . wp_kses_post( $prosa ) . '</div>';
		$prosa = '';
		$prvni = false;
	};

	foreach ( $bloky as $blok ) {
		if ( 'text' === $blok['typ'] ) {
			$prosa .= $blok['html'];
			continue;
		}
		$vysyp();

		if ( 'obrazky' === $blok['typ'] ) {
			$polozky = $blok['polozky'];

			// Náhledový obrázek už je nahoře v záhlaví, znovu ho neopakujeme.
			if ( $bez_fotky ) {
				$polozky = array_values(
					array_filter(
						$polozky,
						function ( $obrazek ) use ( $bez_fotky ) {
							return (int) $obrazek['id'] !== (int) $bez_fotky;
						}
					)
				);
			}
			if ( $polozky ) {
				csr_render_story_gallery( $polozky );
			}
			continue;
		}

		if ( 'vysledky' === $blok['typ'] ) {
			csr_render_story_results( $blok['radky'] );
			continue;
		}

		if ( 'vlozeny' === $blok['typ'] ) {
			echo '<div class="csr-story__embed">' . $blok['html'] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput — vložený přehrávač z redakce
			continue;
		}
	}

	$vysyp();
}

/**
 * Skupina fotek. Podle počtu se z nich stane jeden velký snímek,
 * dvojice vedle sebe, nebo mřížka.
 *
 * @param array $obrazky Fotky ke skupině.
 */
function csr_render_story_gallery( $obrazky ) {
	$pocet = count( $obrazky );
	$druh  = 1 === $pocet ? 'solo' : ( $pocet < 4 ? 'rada' : 'mrizka' );

	// U jediné fotky necháváme její vlastní poměr stran, ve skupině se ořízne.
	$sam    = 1 === $pocet ? $obrazky[0] : null;
	$pomer  = $sam && $sam['sirka'] && $sam['vyska'] ? $sam['sirka'] . ' / ' . $sam['vyska'] : '';
	$popis  = $sam ? $sam['popis'] : '';
	?>
	<figure class="csr-artgal csr-artgal--<?php echo esc_attr( $druh ); ?><?php echo $sam && $sam['navysku'] ? ' csr-artgal--navysku' : ''; ?> csr-reveal"
		data-csr-gallery
		<?php if ( $pomer ) : ?>style="--csr-ratio: <?php echo esc_attr( $pomer ); ?>"<?php endif; ?>>

		<?php foreach ( $obrazky as $i => $obrazek ) : ?>
			<?php
			$plna = $obrazek['id'] ? wp_get_attachment_image_url( $obrazek['id'], 'full' ) : '';
			if ( ! $plna ) {
				$plna = $obrazek['url'];
			}
			$titulek = $obrazek['popis'] ? $obrazek['popis'] : $obrazek['alt'];
			?>
			<button type="button" class="csr-artgal__item"
				data-csr-shot
				data-csr-full="<?php echo esc_url( $plna ); ?>"
				data-csr-index="<?php echo (int) $i; ?>"
				<?php if ( $titulek ) : ?>data-csr-caption="<?php echo esc_attr( $titulek ); ?>"<?php endif; ?>>
				<img src="<?php echo esc_url( $obrazek['url'] ); ?>"
					alt="<?php echo esc_attr( $obrazek['alt'] ); ?>"
					<?php if ( $obrazek['sirka'] && $obrazek['vyska'] ) : ?>
						width="<?php echo (int) $obrazek['sirka']; ?>" height="<?php echo (int) $obrazek['vyska']; ?>"
					<?php endif; ?>
					loading="<?php echo $i < 2 ? 'eager' : 'lazy'; ?>" decoding="async">
				<span class="screen-reader-text">
					<?php echo esc_html( $titulek ? $titulek : 'Zvětšit fotku ' . ( $i + 1 ) ); ?>
				</span>
			</button>
		<?php endforeach; ?>

		<?php if ( $popis ) : ?>
			<figcaption><?php echo esc_html( $popis ); ?></figcaption>
		<?php endif; ?>
	</figure>
	<?php
}

/* -------------------------------------------------------------------------
 * Výsledkové řádky
 *
 * Články ze závodů končí výčtem umístění: „Ženy 1500 metrů : 1. Beune
 * (Niz.) 1:53,19, 2. Takagi 1:53,48, …". Jako běžný odstavec se to čte
 * mizerně, tak z toho uděláme přehlednou tabulku. Text se nikde nemění,
 * jen se rozdělí — když si nejsme jistí, necháme odstavec být.
 * ---------------------------------------------------------------------- */

/** Pořadí ve výsledku: číslo, tečka, a za ní mezera nebo rovnou velké písmeno. */
const CSR_RESULT_RANK  = '/(?:^|[\s,.\x{2026}])\d{1,2}\.(?:\s|\p{Lu})/u';
/** Totéž jako místo, kde se řádek dělí na jednotlivá umístění. */
const CSR_RESULT_SPLIT = '/(?<=[\s,.\x{2026}])(?=\d{1,2}\.(?:\s|\p{Lu}))/u';

/**
 * Začíná řádek novou disciplínou?
 *
 * Word zalomil dlouhý řádek doprostřed, takže „Ženy 1500 metrů : 1. Beune"
 * a „1:53,783……10. Zdráhalová" jsou dva řádky, ale jeden výsledek. Nový
 * začíná jen tam, kde je před dvojtečkou popisek obsahující písmeno —
 * čas „1:53" tuhle podmínku nesplní.
 *
 * @param string $radek Čistý text řádku.
 * @return bool
 */
function csr_result_starts( $radek ) {
	if ( ! preg_match( '/^([^:]{1,60}):/u', $radek, $shoda ) ) {
		return false;
	}
	return (bool) preg_match( '/\p{L}/u', $shoda[1] );
}

/**
 * Rozebere jeden řádek s výsledky.
 *
 * @param string $text Čistý text řádku.
 * @return array|false Pole s klíči „hlava" a „mista", nebo false.
 */
function csr_result_row( $text ) {
	$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
	if ( '' === $text || false === strpos( $text, ':' ) ) {
		return false;
	}

	/*
	 * Bez aspoň tří umístění to nejspíš výsledky nejsou. Za pořadím
	 * nemusí být mezera („3.Morozova") a před ním bývá výpustka
	 * („……10. Zdráhalová"), tak počítáme i s tím.
	 */
	if ( preg_match_all( CSR_RESULT_RANK, $text ) < 3 ) {
		return false;
	}

	$hlava  = trim( substr( $text, 0, strpos( $text, ':' ) ) );
	$zbytek = trim( substr( $text, strpos( $text, ':' ) + 1 ) );

	// Uvozovací „Výsledky :" bereme jako nadpis celého bloku, ne jako disciplínu.
	if ( '' === $hlava || csr_strlen( $hlava ) > 60 || '' === $zbytek ) {
		return false;
	}

	$casti = preg_split( CSR_RESULT_SPLIT, $zbytek );
	if ( ! $casti || count( $casti ) < 3 ) {
		return false;
	}

	$mista = array();
	foreach ( $casti as $cast ) {
		// Koncové „…." značí vynechaná místa, do výpisu nepatří. Osamocenou
		// tečku necháváme — patří ke zkratkám jako „(Nor.)".
		$cast = trim( preg_replace( '/[\s.,\x{2026}]{2,}$/u', '', trim( $cast ) ) );
		$cast = trim( rtrim( $cast, ',' ) );
		if ( '' === $cast ) {
			continue;
		}
		// Oddělíme jen pořadí, se zbytkem řádku nehýbeme.
		if ( preg_match( '/^(\d{1,2})\.\s*(.+)$/u', $cast, $shoda ) ) {
			$mista[] = array( 'poradi' => $shoda[1], 'kdo' => $shoda[2] );
		} else {
			$mista[] = array( 'poradi' => '', 'kdo' => $cast );
		}
	}

	return $mista ? array( 'hlava' => $hlava, 'mista' => $mista ) : false;
}

/**
 * Sloučí po sobě jdoucí výsledkové odstavce do jednoho bloku.
 *
 * @param array $bloky Bloky z csr_article_extract().
 * @return array
 */
function csr_article_group_results( $bloky ) {
	$vysledek = array();

	foreach ( $bloky as $blok ) {
		if ( 'text' !== $blok['typ'] ) {
			$vysledek[] = $blok;
			continue;
		}

		/*
		 * Odstavec může mít víc řádků oddělených zalomením. Nejdřív je
		 * slepíme zpátky — Word zalomil i uprostřed jednoho výsledku.
		 */
		$slepene = array();
		$nadpis  = '';
		foreach ( preg_split( '#<br[^>]*>#i', $blok['html'] ) as $kus ) {
			$cisty = trim( html_entity_decode( wp_strip_all_tags( $kus ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' === $cisty ) {
				continue;
			}
			// Samostatné „Výsledky:" uvozuje blok.
			if ( preg_match( '/^v[yý]sledky\s*:?$/iu', $cisty ) ) {
				$nadpis = 'Výsledky';
				continue;
			}
			// „Výsledky : Ženy 500 metrů : 1. …" — uvození na začátku řádku.
			if ( preg_match( '/^v[yý]sledky\s*:\s*(.+)$/iu', $cisty, $shoda ) ) {
				$nadpis = 'Výsledky';
				$cisty  = $shoda[1];
			}

			if ( ! $slepene || csr_result_starts( $cisty ) ) {
				$slepene[] = $cisty;
			} else {
				$slepene[ count( $slepene ) - 1 ] .= ' ' . $cisty;
			}
		}

		$radky = array();
		$cely  = true;
		foreach ( $slepene as $cisty ) {
			$radek = csr_result_row( $cisty );
			if ( ! $radek ) {
				$cely = false;
				break;
			}
			$radky[] = $radek;
		}

		if ( ! $cely || ! $radky ) {
			$vysledek[] = $blok;
			continue;
		}

		// Navazuje-li na předchozí výsledkový blok, přilepíme se k němu.
		$posledni = count( $vysledek ) - 1;
		if ( $posledni >= 0 && 'vysledky' === $vysledek[ $posledni ]['typ'] ) {
			$vysledek[ $posledni ]['radky'] = array_merge( $vysledek[ $posledni ]['radky'], $radky );
			continue;
		}

		$vysledek[] = array( 'typ' => 'vysledky', 'radky' => $radky, 'nadpis' => $nadpis );
	}

	return $vysledek;
}

/**
 * Vykreslí blok s výsledky.
 *
 * @param array $radky Řádky z csr_article_group_results().
 */
function csr_render_story_results( $radky ) {
	?>
	<section class="csr-artres csr-reveal" aria-label="Výsledky">
		<h2 class="csr-artres__title">Výsledky</h2>
		<?php foreach ( $radky as $radek ) : ?>
			<div class="csr-artres__row">
				<h3 class="csr-artres__disc"><?php echo esc_html( $radek['hlava'] ); ?></h3>
				<ol class="csr-artres__list">
					<?php foreach ( $radek['mista'] as $misto ) : ?>
						<li>
							<?php if ( '' !== $misto['poradi'] ) : ?>
								<span class="csr-artres__rank"><?php echo esc_html( $misto['poradi'] ); ?></span>
							<?php endif; ?>
							<span class="csr-artres__who"><?php echo esc_html( $misto['kdo'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		<?php endforeach; ?>
	</section>
	<?php
}
