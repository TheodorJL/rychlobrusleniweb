<?php
/**
 * Náborová stránka — nastavení.
 *
 * @package CSR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Box u stránky s náborovou šablonou.
 */
function csr_landing_metabox() {
	add_meta_box( 'csr_landing', 'Náborová stránka', 'csr_landing_metabox_render', 'page', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'csr_landing_metabox' );

/**
 * Vykreslí pole.
 *
 * @param WP_Post $post Upravovaná stránka.
 */
function csr_landing_metabox_render( $post ) {
	wp_nonce_field( 'csr_landing_save', 'csr_landing_nonce' );

	if ( CSR_LANDING_TEMPLATE !== get_post_meta( $post->ID, '_wp_page_template', true ) ) {
		echo '<p class="description">Vyberte nahoře šablonu <strong>„ČSR — Náborová stránka"</strong> a stránku uložte. Pak se tu objeví nastavení.</p>';
		return;
	}
	?>
	<p>
		<label for="csr_landing_badge"><strong>Odznak nad nadpisem</strong></label>
		<input type="text" id="csr_landing_badge" name="csr_landing_badge" style="width:100%"
		       value="<?php echo esc_attr( get_post_meta( $post->ID, '_csr_landing_badge', true ) ); ?>"
		       placeholder="Seminář zdarma">
		<span class="description">Nepovinné.</span>
	</p>
	<p>
		<label for="csr_landing_lead"><strong>Úvodní text pod nadpisem</strong></label>
		<textarea id="csr_landing_lead" name="csr_landing_lead" rows="4" style="width:100%"
		          placeholder="Nechte prázdné a použije se začátek obsahu stránky."><?php
			echo esc_textarea( get_post_meta( $post->ID, '_csr_landing_lead', true ) );
		?></textarea>
	</p>
	<?php
}

/**
 * Uloží nastavení.
 *
 * @param int $post_id ID stránky.
 */
function csr_landing_save( $post_id ) {
	if ( ! isset( $_POST['csr_landing_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['csr_landing_nonce'] ), 'csr_landing_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['csr_landing_badge'] ) ) {
		update_post_meta( $post_id, '_csr_landing_badge', sanitize_text_field( wp_unslash( $_POST['csr_landing_badge'] ) ) );
	}
	if ( isset( $_POST['csr_landing_lead'] ) ) {
		update_post_meta( $post_id, '_csr_landing_lead', sanitize_textarea_field( wp_unslash( $_POST['csr_landing_lead'] ) ) );
	}
}
add_action( 'save_post_page', 'csr_landing_save' );
