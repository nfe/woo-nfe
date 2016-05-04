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
		add_action( 'add_meta_boxes',         array( $this, 'add_meta_boxes' ), 25 );
		add_action( 'save_post',              array( $this, 'save'         ) );
	}

	/**
	 * Add WC Meta box
	 */
	public function add_meta_boxes() {
		add_meta_box( 'nfe-woocommerce-data', 
			_x( 'Nfe.io - Product Fiscal Activities', 'meta box title', 'nfe-woocommerce' ), 
			array( $this, 'output' ), 
			'product', 'normal', 'default' );
	}

	/**
	 * Meta box display callback.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function output( $post ) {
        $fiscal_activities = get_post_meta( $post->ID, 'nfe_woo_fiscal_activities', true );

	    // Add an nonce field so we can check for it later.
        wp_nonce_field( 'nfe_woocommerce_box_nonce', 'nfe_woocommerce_box_nonce' ); ?>
  
        <table id="nfe-woo-fieldset-one" width="100%">
            <thead>
                <tr>
                    <th width="50%"><?php esc_html_e( 'Activity Name', 'nfe-woocommerce' ); ?></th>
                    <th width="42%"><?php esc_html_e( 'Code', 'nfe-woocommerce' ); ?></th>
                    <th width="8%"></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $fiscal_activities ) :
                
                    foreach ( (array) $fiscal_activities as $activity ) { ?>
                    <tr>
                        <td>
                            <input type="text" class="widefat" name="name[]" value="<?php if( $activity['name'] !== '' ) echo esc_attr( $activity['name'] ); ?>" />
                        </td>
                    
                        <td>
                            <input type="text" class="widefat" name="code[]" value="<?php if ( $activity['code'] !== '') echo esc_attr( $activity['code'] ); ?>" />
                        </td>
                    
                        <td>
                            <a class="button remove-row" href="#"><?php esc_html_e( 'Remove', 'nfe-woocommerce' ); ?></a>
                        </td>
                    </tr>
                    <?php }
                
                else : // show a blank one ?>

                    <tr>
                        <td><input type="text" class="widefat" name="name[]" /></td>
                        <td><input type="text" class="widefat" name="code[]" value="" /></td>
                        <td><a class="button remove-row" href="#"><?php esc_html_e( 'Remove', 'nfe-woocommerce' ); ?></a></td>
                    </tr>

                <?php endif; ?>
                
                <!-- empty hidden one for jQuery -->
                <tr class="empty-row screen-reader-text">
                    <td><input type="text" class="widefat" name="name[]" /></td>
                    <td><input type="text" class="widefat" name="code[]" value="" /></td>
                    <td><a class="button remove-row" href="#"><?php esc_html_e( 'Remove', 'nfe-woocommerce' ); ?></a></td>
                </tr>
            </tbody>
        </table>
    
        <p>
            <a id="add-row" class="button" href="#">
                <?php esc_html_e( 'Add Another', 'nfe-woocommerce' ); ?>
            </a>
        </p>
        <?php
	}

    /**
     * Save the meta when the post is saved.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save( $post_id ) {
        if ( 'product' !== get_post_type() ) {
            return;
        }
    
        $nonce = $_POST['nfe_woocommerce_box_nonce'];

        // Check if our nonce is set and verify that the nonce is valid.
        if ( ! isset( $nonce ) || ! wp_verify_nonce( $nonce, 'nfe_woocommerce_box_nonce' ) ) {
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
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return $post_id;
        }

        $old = get_post_meta( $post_id, 'nfe_woo_fiscal_activities', true );
        $new = array();

        $names = $_POST['name'];
        $codes = $_POST['code'];

        $count = count( $names );

        for ( $i = 0; $i < $count; $i++ ) {
            if ( $names[$i] !== '' ) :
                $new[$i]['name'] = sanitize_text_field( $names[$i] );
            endif;

             if ( $codes[$i] !== '' ) :
                $new[$i]['code'] = sanitize_text_field( sanitize_key( $codes[$i] ) );
            endif;
        }

        // Update meta fields
        if ( !empty( $new ) && $new !== $old ) {
            update_post_meta( $post_id, 'nfe_woo_fiscal_activities', $new );
        } elseif ( empty($new) && $old ) {
            delete_post_meta( $post_id, 'nfe_woo_fiscal_activities', $old );
        }
    }
}

new WC_Nfe_Admin_Meta_Boxes();
