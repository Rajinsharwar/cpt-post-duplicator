<?php
/*
Plugin Name: CPT Post Duplicator
Description: Duplicate any Post types with a single click.
Author: Rajin Sharwar
Version: 1.0
Author URI: https://linkedin.com/in/rajinsharwar
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_filter( 'post_row_actions', 'rjn_duplicate_cpt_post_type', 10, 2 );
add_action( 'admin_notices', 'rjn_cpt_duplication_admin_notice' );
add_action( 'admin_action_rjn_duplicate_cpt_as_draft', 'rjn_duplicate_cpt_as_draft' );

/**
 * Add Duplicate button in the Post row.
 */
function rjn_duplicate_cpt_post_type( $actions, $post ) {

	if ( ! current_user_can( 'edit_posts' ) ) {
		return $actions;
	}

	$url = wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'rjn_duplicate_cpt_as_draft',
				'post' => $post->ID,
			),
			'admin.php'
		),
		basename(__FILE__),
		'duplicate_item_nonce'
	);

	$actions[ 'duplicate_item' ] = '<a href="' . $url . '" title="Duplicate this item" rel="permalink">Duplicate</a>';

	return $actions;
}

/*
 * Function creates post duplicate as a draft and redirects then to the edit post screen
 */
function rjn_duplicate_cpt_as_draft(){

	if ( empty( $_GET[ 'post' ] ) ) {
		wp_die( 'No post to duplicate has been provided!' );
	}

	// Nonce verification
	if ( ! isset( $_GET[ 'duplicate_item_nonce' ] ) || ! wp_verify_nonce( $_GET[ 'duplicate_item_nonce' ], basename( __FILE__ ) ) ) {
		return;
	}

	$post_id = absint( $_GET[ 'post' ] );
	$post = get_post( $post_id );

	$current_user = wp_get_current_user();
	$new_post_author = $current_user->ID;

	if ( $post ) {

		$args = array(
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $new_post_author,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_name'      => $post->post_name,
			'post_parent'    => $post->post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => 'draft',
			'post_title'     => $post->post_title,
			'post_type'      => $post->post_type,
			'to_ping'        => $post->to_ping,
			'menu_order'     => $post->menu_order
		);

		$new_post_id = wp_insert_post( $args );

		/*
		 * Gets all current post terms ad set them to the new post draft
		 */
		$taxonomies = get_object_taxonomies( get_post_type( $post ) );
		if( $taxonomies ) {
			foreach ( $taxonomies as $taxonomy ) {
				$post_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
				wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
			}
		}

		// Duplicate all post meta
		$post_meta = get_post_meta( $post_id );
		if( $post_meta ) {

			foreach ( $post_meta as $meta_key => $meta_values ) {

				if( '_wp_old_slug' == $meta_key ) { // do nothing for this meta key
					continue;
				}

				foreach ( $meta_values as $meta_value ) {
					add_post_meta( $new_post_id, $meta_key, $meta_value );
				}
			}
		}

		// Do a safe redirect.
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => ( 'post' !== get_post_type( $post ) ? get_post_type( $post ) : false ),
					'saved' => 'post_duplication_created' // just a custom slug here
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	} else {
		wp_die( 'Post creation failed, could not find original post.' );
	}
}

/*
 * Adding a Success Admin Notice.
 */
function rjn_cpt_duplication_admin_notice() {
	$screen = get_current_screen();

	if ( 'edit' !== $screen->base ) {
		return;
	}

    if ( isset( $_GET[ 'saved' ] ) && 'post_duplication_created' == $_GET[ 'saved' ] ) {
		 echo '<div class="notice notice-success is-dismissible"><p>Post copy created.</p></div>';
    }
}
