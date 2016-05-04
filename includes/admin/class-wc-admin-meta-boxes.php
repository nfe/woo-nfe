<?php

/**
 * WooCommerce Nfe.io Admin Meta Boxes
 *
 * @author   Renato Alves
 * @category Admin
 * @package  Nfe_WooCommerce/Classes/Admin
 * @version  1.0.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * WC_Nfe_Admin_Meta_Boxes
 */
class WC_Nfe_Admin_Meta_Boxes {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 25 );
		add_action( 'save_post',      array( $this, 'save'         ) );
	}

	/**
	 * Add WC Meta boxes
	 */
	public function add_meta_boxes() {
		add_meta_box( 'nfe-woocommerce-data', 
			_x( 'Fiscal Activities', 'meta box title', 'nfe-woocommerce' ), 
			array( $this, 'output' ), 
			'product', 'normal', 'high' );
	}

	/**
     * Save the meta when the post is saved.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save( $post_id ) {
 
        // Check if our nonce is set.
        if ( ! isset( $_POST['nfe_woocommerce_box_nonce'] ) ) {
            return $post_id;
        }
 
        $nonce = $_POST['nfe_woocommerce_box_nonce'];
 
        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $nonce, 'nfe_woocommerce_box' ) ) {
            return $post_id;
        }
 
        /*
         * If this is an autosave, our form has not been submitted,
         * so we don't want to do anything.
         */
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }
 
        // Check the user's permissions.
        if ( 'product' === $_POST['post_type'] ) {
            if ( ! current_user_can( 'edit_page', $post_id ) ) {
                return $post_id;
            }
        } else {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return $post_id;
            }
        }
 
        // Sanitize the user input.
        $mydata = sanitize_text_field( $_POST['nfe_woocommerce_fiscal_activity'] );
 
        // Update the meta field.
        update_post_meta( $post_id, '_nfe_woocommerce_fiscal_activity_key', $mydata );
    }

	/**
	 * Meta box display callback.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function output( $post ) {
	    // Add an nonce field so we can check for it later.
        wp_nonce_field( 'nfe_woocommerce_box', 'nfe_woocommerce_box_nonce' );
 
        // Use get_post_meta to retrieve an existing value from the database.
        $value = get_post_meta( $post->ID, '_nfe_woocommerce_fiscal_activity_key', true );
 
        // Display the form, using the current value.
        ?>
        <label for="nfe_woocommerce_fiscal_activity">
            <?php _e( 'Add the Fiscal Activity for this product', 'nfe-woocommerce' ); ?>
        </label>
        <input type="text" id="nfe_woocommerce_fiscal_activity" name="nfe_woocommerce_fiscal_activity" value="<?php echo esc_attr( $value ); ?>" size="25" />
        <?php
	}
}

new WC_Nfe_Admin_Meta_Boxes();
