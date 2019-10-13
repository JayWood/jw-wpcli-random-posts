<?php
/**
 * Handles the initialization of the Cleanup command.
 *
 * @package plugish\CLI\RandomPosts
 * @sub-package Command
 */

namespace plugish\CLI\RandomPosts\Command;

use WP_CLI;
use WP_Term;
use WP_Term_Query;

class Cleanup extends Generate {

	public function __invoke( $args, $assoc_args ) {
		$this->wp_version_check();

		// Properties so it can be reused later.
		$this->args       = $args;
		$this->assoc_args = $assoc_args;

		$post_type    = isset( $assoc_args['type'] ) ? $assoc_args['type'] : 'any';
		$taxonomies   = isset( $assoc_args['taxonomies'] ) ? explode( ',', $assoc_args['taxonomies'] ) : array();
		$post_author  = isset( $assoc_args['author'] ) ? $this->get_author_id( $assoc_args['author'] ) : 1;
		$force_delete = isset( $assoc_args['force-delete'] );

		if ( $force_delete ) {
			WP_CLI::confirm( 'You have elected to completely remove the test posts, this cannot be undone, are you sure?' );
		}

		$this->validate_command( $post_type, $post_author );
		$taxonomies = $this->validate_taxonomies( $taxonomies );
		if ( ! empty( $taxonomies ) ) {
			$this->delete_terms( $force_delete, $taxonomies );
		}

		$this->delete_posts( $post_author, $post_type, $force_delete );
	}

	/**
	 * Checks taxonomy array passed in against registered taxonomies.
	 *
	 * @param array $taxonomies An array of taxonomies to check.
	 *
	 * @return array
	 */
	private function validate_taxonomies( array $taxonomies ) : array {
		if ( ! empty( $taxonomies ) ) {
			$taxonomies = array_filter( $taxonomies );
			// Validate the taxonomies exist first
			$errors = array();
			foreach ( $taxonomies as $taxonomy_slug ) {
				if ( ! taxonomy_exists( $taxonomy_slug ) ) {
					$errors[] = $taxonomy_slug;
				}
			}

			if ( ! empty( $errors ) ) {
				WP_CLI::warning( sprintf( 'The following taxonomies seem to not be registered: %s', implode( ',', $errors ) ) );
				WP_CLI::confirm( 'Would you like to ignore those and continue?' );

				// If we continue, return only taxonomies that are present.
				return array_diff( $taxonomies, $errors );
			} else {
				unset( $errors ); // Probably not needed but why not right?
			}
		}

		return $taxonomies;
	}

	/**
	 * Ensures the command can run without issue.
	 *
	 * @param string $post_type The post type.
	 * @param int    $post_author The author ID.
	 *
	 * @throws WP_CLI\ExitException
	 */
	private function validate_command( string $post_type, int $post_author ) : void {
		if ( is_array( $post_type ) ) {
			foreach ( $post_type as $p_type ) {
				if ( 'post' !== $p_type && ! post_type_exists( $p_type ) ) {
					WP_CLI::error( sprintf( 'The %s post type does not exist, make sure it is registered properly.', $p_type ) );
				}
			}
		} elseif ( 'any' !== $post_type && ! post_type_exists( $post_type ) ) {
			WP_CLI::error( sprintf( 'The %s post type does not exist, make sure it is registered properly.', $post_type ) );
		}

		// Validate the author exists
		if ( ! get_user_by( 'ID', $post_author ) ) {
			WP_CLI::error( sprintf( 'User ID %d does not exist within the WordPress database, cannot continue.', $post_author ) );
		}
	}

	/**
	 * Deletes terms from a given taxonomy list.
	 *
	 * @param bool  $force_delete Rather or not to confirm deletion really.
	 * @param array $taxonomies   An array of taxonomies.
	 */
	private function delete_terms( bool $force_delete, array $taxonomies ) : void {
		// Let's walk over and delete terms in these taxonomies first.
		if ( ! $force_delete ) {
			WP_CLI::warning( 'It looks like you aren\'t force deleting posts. If we continue we cannot recover any terms deleted, terms do not have a \'trash\' status.' );
			WP_CLI::confirm( 'Do you want to continue?' );
		}

		$term_query = new WP_Term_Query( array(
			'taxonomy'   => $taxonomies,
			'get'        => 'all',
			'hide_empty' => false,
			'meta_key'   => self::META_KEY,
			'meta_value' => true,
		) );

		$terms = $term_query->get_terms();

		if ( ! empty( $terms ) ) {
			$this->progress_bar( count( $terms ), 'Terms', 'Removing' );
			/** @var WP_Term $term_data */
			foreach ( $terms as $term_data ) {
				wp_delete_term( $term_data->term_id, $term_data->taxonomy );
				$this->progress_bar( 'tick' );
			}
			WP_CLI::success( sprintf( 'Deleted %d terms', count( $terms ) ) );
			$this->progress_bar( 'finish' );
		} else {
			WP_CLI::success( 'No terms for specified taxonomy found, skipped term deletion.' );
		}
	}

	/**
	 * Deletes posts.
	 *
	 * @param int    $post_author  The author ID.
	 * @param string $post_type    A single or list of post types.
	 * @param bool   $force_delete Forcefully deletes the post, no trash.
	 */
	private function delete_posts( int $post_author, string $post_type, bool $force_delete ) : void {
		// Now build the arguments for posts
		$posts = get_posts( array(
			'meta_key'       => self::META_KEY,
			'meta_value'     => true,
			'posts_per_page' => - 1, // ALL the posts
			'post_author'    => $post_author,
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'fields'         => 'ids',
		) );

		if ( ! empty( $posts ) ) {
			$this->progress_bar( count( $posts ), 'Posts', 'Removing' );
			foreach ( $posts as $post_id ) {
				wp_delete_post( $post_id, $force_delete );
				$this->progress_bar( 'tick' );
			}
			$this->progress_bar( 'finish' );
			WP_CLI::success( sprintf( 'Deleted %d posts', count( $posts ) ) );
		} else {
			WP_CLI::success( 'No posts for the specified post type were found, skipped post deletion' );
		}

		WP_CLI::success( 'Cleanup complete, now put up the broom and get back to coding!' );
	}
}
