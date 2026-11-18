<?php
/**
 * České rekordy.
 *
 * Na starém webu si rekordy stahoval prohlížeč každého návštěvníka přímo
 * ze speedskatingresults.com. Mělo to tři vady: v HTML stránky nebyl ani
 * řádek textu (proto té stránce chyběl i popis pro vyhledávače), při
 * výpadku cizího serveru se místo rekordů ukázalo čtyřikrát „Chyba při
 * načítání dat" a IP adresa každého návštěvníka putovala na cizí server.
 *
 * Tady data stahuje WordPress sám, jednou denně na pozadí, a ukládá si je.
 * Stránka je pak obyčejné HTML — čitelné bez JavaScriptu, dohledatelné
 * Googlem. Když cizí server neodpoví, zůstanou poslední známé hodnoty
 * a u nich datum, kdy byly naposledy ověřené.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Jak dlouho platí stažená data, než se zkusí obnovit. */
const CSR_RECORDS_TTL = 12 * HOUR_IN_SECONDS;

/** Klíč trvalé zálohy. Přežije i vyprázdnění cache. */
const CSR_RECORDS_BACKUP = 'csr_records_backup';

/**
 * Skupiny rekordů v pořadí, v jakém se vypisují.
 *
 * @return array Klíč => [popisek, věk, pohlaví].
 */
function csr_record_groups() {
	return array(
		'sr-m' => array( 'Muži',            'sr', 'm' ),
		'sr-f' => array( 'Ženy',            'sr', 'f' ),
		'jr-m' => array( 'Junioři',         'jr', 'm' ),
		'jr-f' => array( 'Juniorky',        'jr', 'f' ),
	);
}

/**
 * Adresa, ze které se rekordy berou.
 *
 * @param string $age    sr nebo jr.
 * @param string $gender m nebo f.
 * @return string
 */
function csr_records_endpoint( $age, $gender ) {
	return add_query_arg(
		array(
			'country' => 'CZE',
			'age'     => $age,
			'gender'  => $gender,
		),
		'https://speedskatingresults.com/api/json/country_records.php'
	);
}

/* -------------------------------------------------------------------------
 * Stahování
 * ---------------------------------------------------------------------- */

/**
 * Stáhne jednu skupinu rekordů.
 *
 * @param string $age    sr nebo jr.
 * @param string $gender m nebo f.
 * @return array|WP_Error Seznam rekordů, nebo chyba.
 */
function csr_records_fetch( $age, $gender ) {
	$response = wp_remote_get(
		csr_records_endpoint( $age, $gender ),
		array(
			'timeout'    => 10,
			'user-agent' => 'speedskating.cz (WordPress)',
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		return new WP_Error( 'csr_records_http', sprintf( 'Server odpověděl kódem %d.', $code ) );
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) || ! isset( $data['records'] ) || ! is_array( $data['records'] ) ) {
		return new WP_Error( 'csr_records_shape', 'Odpověď nemá očekávaný tvar.' );
	}

	$out = array();
	foreach ( $data['records'] as $record ) {
		if ( ! is_array( $record ) ) {
			continue;
		}
		$given  = isset( $record['skater']['givenname'] ) ? $record['skater']['givenname'] : '';
		$family = isset( $record['skater']['familyname'] ) ? $record['skater']['familyname'] : '';
		$name   = trim( $given . ' ' . $family );

		// Rekord bez jména nebo bez času nedává smysl a jen by kazil tabulku.
		if ( '' === $name || empty( $record['time'] ) ) {
			continue;
		}

		$out[] = array(
			'distance' => isset( $record['distance'] ) ? (int) $record['distance'] : 0,
			'time'     => (string) $record['time'],
			'name'     => $name,
			'date'     => isset( $record['date'] ) ? (string) $record['date'] : '',
			'location' => isset( $record['location'] ) ? (string) $record['location'] : '',
		);
	}

	// Řadíme podle tratě, ne podle pořadí ze serveru — to se může změnit.
	usort( $out, function ( $a, $b ) {
		return $a['distance'] <=> $b['distance'];
	} );

	return $out;
}

/**
 * Stáhne všechny skupiny a uloží je.
 *
 * Když se nepodaří stáhnout jediná skupina, zálohu nepřepisujeme —
 * lepší jsou včerejší rekordy než prázdná stránka.
 *
 * @return array Klíče ok (bool), groups, time, error.
 */
function csr_records_refresh() {
	$groups = array();
	$errors = array();

	foreach ( csr_record_groups() as $key => $group ) {
		list( , $age, $gender ) = $group;
		$rows = csr_records_fetch( $age, $gender );
		if ( is_wp_error( $rows ) ) {
			$errors[] = $group[0] . ': ' . $rows->get_error_message();
			continue;
		}
		$groups[ $key ] = $rows;
	}

	if ( ! $groups ) {
		$payload = array(
			'ok'     => false,
			'groups' => array(),
			'time'   => 0,
			'error'  => implode( ' · ', $errors ),
		);
		set_transient( 'csr_records', $payload, 15 * MINUTE_IN_SECONDS );
		return $payload;
	}

	$payload = array(
		'ok'     => true,
		'groups' => $groups,
		'time'   => time(),
		'error'  => implode( ' · ', $errors ),
	);

	set_transient( 'csr_records', $payload, CSR_RECORDS_TTL );
	update_option( CSR_RECORDS_BACKUP, $payload, false );

	return $payload;
}

/**
 * Rekordy pro vykreslení.
 *
 * Pořadí zdrojů: čerstvá cache → záloha → pokus o stažení.
 * Stahování na návštěvníka necháváme až jako poslední možnost, aby
 * mu stránka nečekala na cizí server.
 *
 * @return array Klíče ok, groups, time, error, stale.
 */
function csr_records_get() {
	$cached = get_transient( 'csr_records' );
	if ( is_array( $cached ) && ! empty( $cached['ok'] ) ) {
		$cached['stale'] = false;
		return $cached;
	}

	$backup = get_option( CSR_RECORDS_BACKUP );
	if ( is_array( $backup ) && ! empty( $backup['groups'] ) ) {
		// Zálohu vracíme hned a obnovu necháme na naplánované úloze.
		$backup['stale'] = ( time() - (int) $backup['time'] ) > DAY_IN_SECONDS;
		return $backup;
	}

	$fresh = csr_records_refresh();
	$fresh['stale'] = false;
	return $fresh;
}

/* -------------------------------------------------------------------------
 * Naplánovaná obnova
 * ---------------------------------------------------------------------- */

/**
 * Naplánuje denní stažení rekordů.
 */
function csr_records_schedule() {
	if ( ! wp_next_scheduled( 'csr_records_cron' ) ) {
		// Poprvé za pět minut, pak jednou denně — ať se cache naplní dřív,
		// než na stránku přijde první návštěvník.
		wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'daily', 'csr_records_cron' );
	}
}
add_action( 'init', 'csr_records_schedule' );

add_action( 'csr_records_cron', 'csr_records_refresh' );

/**
 * Po vypnutí šablony úlohu zrušíme, ať nezůstane viset.
 */
function csr_records_unschedule() {
	$next = wp_next_scheduled( 'csr_records_cron' );
	if ( $next ) {
		wp_unschedule_event( $next, 'csr_records_cron' );
	}
}
add_action( 'switch_theme', 'csr_records_unschedule' );

/* -------------------------------------------------------------------------
 * Pomocné
 * ---------------------------------------------------------------------- */

/**
 * Datum z API do českého tvaru.
 *
 * Server posílá 2000-01-16, čtenář čeká 16. 1. 2000.
 *
 * @param string $iso Datum ve tvaru RRRR-MM-DD.
 * @return string
 */
function csr_record_date( $iso ) {
	$iso = trim( (string) $iso );
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m ) ) {
		return $iso;
	}
	return sprintf( '%d. %d. %s', (int) $m[3], (int) $m[2], $m[1] );
}

/**
 * Trať jako popisek.
 *
 * @param int $meters Metry.
 * @return string
 */
function csr_record_distance( $meters ) {
	$meters = (int) $meters;
	// Bez oddělovače tisíců — trať se píše „10000 m", ne „10 000 m".
	return $meters ? $meters . ' m' : '';
}

/* -------------------------------------------------------------------------
 * Vykreslení
 * ---------------------------------------------------------------------- */

/**
 * Vykreslí jednu skupinu rekordů.
 *
 * @param string $key   Klíč skupiny.
 * @param string $label Popisek.
 * @param array  $rows  Rekordy.
 */
function csr_render_record_group( $key, $label, $rows ) {
	if ( ! $rows ) {
		return;
	}
	?>
	<section class="csr-restable csr-reveal" id="rekordy-<?php echo esc_attr( $key ); ?>"
	         data-csr-item data-csr-cat="<?php echo esc_attr( $key ); ?>"
	         data-csr-text="<?php
				$csr_hay = $label;
				foreach ( $rows as $csr_r ) {
					$csr_hay .= ' ' . $csr_r['name'] . ' ' . $csr_r['location'] . ' ' . $csr_r['distance'];
				}
				echo esc_attr( $csr_hay );
			?>">
		<header class="csr-restable__head">
			<h2 class="csr-restable__title"><?php echo esc_html( $label ); ?></h2>
		</header>

		<div class="csr-restable__scroll" tabindex="0" role="region"
		     aria-label="<?php echo esc_attr( 'České rekordy — ' . $label ); ?>">
			<table class="csr-table" data-csr-table>
				<caption class="screen-reader-text">České rekordy — <?php echo esc_html( $label ); ?></caption>
				<thead>
					<tr>
						<th scope="col" data-csr-sort="num">Trať</th>
						<th scope="col" class="csr-table__num" data-csr-sort="num">Čas</th>
						<th scope="col" data-csr-sort="text">Jméno</th>
						<th scope="col" data-csr-sort="text">Kde</th>
						<th scope="col" data-csr-sort="text">Kdy</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $csr_r ) : ?>
						<tr>
							<th scope="row" data-csr-label="Trať"><?php echo esc_html( csr_record_distance( $csr_r['distance'] ) ); ?></th>
							<td class="csr-table__num csr-table__time" data-csr-label="Čas"><?php echo esc_html( $csr_r['time'] ); ?></td>
							<td data-csr-label="Jméno"><strong><?php echo esc_html( $csr_r['name'] ); ?></strong></td>
							<td data-csr-label="Kde"><?php echo esc_html( $csr_r['location'] ); ?></td>
							<td data-csr-label="Kdy"><?php echo esc_html( csr_record_date( $csr_r['date'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
	<?php
}

/**
 * Řádek o původu dat pod tabulkami.
 *
 * @param array $data Výstup csr_records_get().
 */
function csr_render_records_source( $data ) {
	$when = ! empty( $data['time'] ) ? wp_date( 'j. n. Y H:i', (int) $data['time'] ) : '';
	?>
	<p class="csr-restable__source">
		Zdroj dat:
		<a href="https://speedskatingresults.com/" target="_blank" rel="noopener noreferrer">speedskatingresults.com</a><?php
		if ( $when ) :
			?>, staženo <time datetime="<?php echo esc_attr( wp_date( 'c', (int) $data['time'] ) ); ?>"><?php echo esc_html( $when ); ?></time><?php
		endif;
		?>.
		<?php if ( ! empty( $data['stale'] ) ) : ?>
			<span class="csr-restable__stale">Novější data se zatím nepodařilo stáhnout — vidíte poslední ověřená.</span>
		<?php endif; ?>
	</p>
	<?php
}

/* -------------------------------------------------------------------------
 * Stav v administraci
 * ---------------------------------------------------------------------- */

/**
 * Podstránka se stavem stahování.
 */
function csr_records_admin_page() {
	add_submenu_page(
		'edit.php?post_type=csr_result',
		'České rekordy',
		'České rekordy',
		'edit_posts',
		'csr-records',
		'csr_records_admin_render'
	);
}
add_action( 'admin_menu', 'csr_records_admin_page' );

/**
 * Stav stahování a tlačítko na okamžitou obnovu.
 */
function csr_records_admin_render() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Na tohle nemáte oprávnění.' );
	}

	$refreshed = false;
	if ( isset( $_POST['csr_records_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['csr_records_nonce'] ), 'csr_records_refresh' ) ) {
		delete_transient( 'csr_records' );
		csr_records_refresh();
		$refreshed = true;
	}

	$data = csr_records_get();
	$next = wp_next_scheduled( 'csr_records_cron' );
	?>
	<div class="wrap">
		<h1>České rekordy</h1>

		<?php if ( $refreshed ) : ?>
			<div class="notice notice-success"><p>Data stažena znovu.</p></div>
		<?php endif; ?>

		<p>Rekordy se stahují ze serveru <a href="https://speedskatingresults.com/" target="_blank" rel="noopener noreferrer">speedskatingresults.com</a>
			jednou denně na pozadí a ukládají se sem. Stránka pak nečeká na cizí server
			a funguje, i když je nedostupný.</p>

		<table class="widefat striped" style="max-width:760px">
			<tbody>
				<tr>
					<th style="width:220px">Stav</th>
					<td>
						<?php if ( ! empty( $data['groups'] ) && empty( $data['stale'] ) ) : ?>
							<span style="color:#00694e">✓ data jsou aktuální</span>
						<?php elseif ( ! empty( $data['groups'] ) ) : ?>
							<span style="color:#996800">⚠ používá se záloha</span> — novější data se nepodařilo stáhnout
						<?php else : ?>
							<span style="color:#b32d2e">✗ žádná data</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th>Naposledy staženo</th>
					<td><?php echo ! empty( $data['time'] ) ? esc_html( wp_date( 'j. n. Y H:i', (int) $data['time'] ) ) : '—'; ?></td>
				</tr>
				<tr>
					<th>Další stažení</th>
					<td><?php echo $next ? esc_html( wp_date( 'j. n. Y H:i', (int) $next ) ) : 'není naplánováno'; ?></td>
				</tr>
				<?php foreach ( csr_record_groups() as $csr_k => $csr_g ) : ?>
					<tr>
						<th><?php echo esc_html( $csr_g[0] ); ?></th>
						<td>
							<?php
							$csr_n = isset( $data['groups'][ $csr_k ] ) ? count( $data['groups'][ $csr_k ] ) : 0;
							echo $csr_n
								? esc_html( sprintf( '%d tratí', $csr_n ) )
								: '<span style="color:#b32d2e">chybí</span>';
							?>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! empty( $data['error'] ) ) : ?>
					<tr>
						<th>Poslední chyba</th>
						<td><code><?php echo esc_html( $data['error'] ); ?></code></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<form method="post" style="margin-top:1.4rem">
			<?php wp_nonce_field( 'csr_records_refresh', 'csr_records_nonce' ); ?>
			<?php submit_button( 'Stáhnout teď', 'secondary' ); ?>
		</form>

		<p class="description" style="max-width:760px">
			Short track rekordy tenhle server nevede. Pokud je chcete na stránce mít,
			přidejte je jako obyčejnou tabulku v <em>Výsledky → Přidat tabulku</em>
			a zaškrtněte u ní <em>Zobrazit na stránce Českých rekordů</em>.
		</p>
	</div>
	<?php
}
