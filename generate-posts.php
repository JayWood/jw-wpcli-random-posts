<?php

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * A robust random post generator built for developers.
	 */
	class JW_Random_Posts extends WP_CLI_Command {

		private $args, $assoc_args, $progress_bar;

		/**
		 * @var string A meta key that is used throughout the script to allow removal of the data later.
		 */
		private $meta_key = '_jwrp_test_data';

		/**
		 * The minimum WordPress version required to run this script.
		 */
		const WP_VERSION = '4.6';

		/**
		 * Cleans up generated content.
		 *
		 * ## OPTIONS
		 *
		 * [--type=<post_type>]
		 * : The post type
		 * ---
		 * default: post
		 * ---
		 *
		 * [--force-delete]
		 * : Force deletes posts, skips trash
		 * ---
		 * default: false
		 * ---
		 *
		 * [--tax=<taxonomy>]
		 * : The Taxonomies, comma separated
		 * ---
		 * default: none
		 * ---
		 *
		 * [--media]
		 * : Cleans up all media as well.
		 * ---
		 * default: false
		 * ---
		 *
		 * [--author=<id>]
		 * : The post author id
		 * ---
		 * default: 1
		 * ---
		 *
		 * [--site=<site_id>]
		 * : If multisite is enabled, you can specify a site id
		 * ---
		 * default: false
		 * ---
		 */
		public function cleanup( $args, $assoc_args ) {
			$this->args       = $args;
			$this->assoc_args = $assoc_args;

			$post_type    = isset( $assoc_args['type'] ) ? $assoc_args['type'] : 'post';
			$taxonomies   = isset( $assoc_args['tax'] ) ? explode( ',', $assoc_args['tax'] ) : array();
			$post_author  = isset( $assoc_args['author'] ) ? intval( $assoc_args['author'] ) : 1;
			$blog_id      = isset( $assoc_args['site'] ) ? intval( $assoc_args['site'] ) : false;
			$force_delete = isset( $assoc_args['force-delete'] );

			if ( $blog_id && is_multisite() ) {
				switch_to_blog( $blog_id );
			}

			$this->wp_version_check();

			if ( $force_delete ) {
				WP_CLI::confirm( 'You have elected to completely remove the test posts, this cannot be undone, are you sure?' );
			}

			if ( isset( $assoc_args['media'] ) ) {
				$post_type = array( $post_type, 'attachment' );
			}

			if ( is_array( $post_type ) ) {
				foreach ( $post_type as $p_type ) {
					if ( 'post' !== $p_type && ! post_type_exists( $p_type ) ) {
						WP_CLI::error( sprintf( 'The %s post type does not exist, make sure it is registered properly.', $p_type ) );
					}
				}
			} elseif ( 'post' !== $post_type && ! post_type_exists( $post_type ) ) {
				WP_CLI::error( sprintf( 'The %s post type does not exist, make sure it is registered properly.', $post_type ) );
			}

			// Validate the author exists
			if ( ! get_user_by( 'ID', $post_author ) ) {
				WP_CLI::error( sprintf( 'User ID %d does not exist within the WordPress database, cannot continue.', $post_author ) );
			}

			// Validate the taxonomies data
			$taxonomies = $this->validate_taxonomies( $taxonomies );

			if ( ! empty( $taxonomies ) ) {
				// Let's walk over and delete terms in these taxonomies first.

				if ( ! $force_delete ) {
					WP_CLI::warning( 'It looks like you aren\'t force deleting posts. If we continue we cannot recover any terms deleted, terms do not have a \'trash\' status.' );
					WP_CLI::confirm( 'Do you want to continue?' );
				}

				$term_query = new WP_Term_Query( array(
					'taxonomy'   => $taxonomies,
					'get'        => 'all',
					'hide_empty' => false,
					'meta_key'   => $this->meta_key,
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
					WP_CLI::success( "No terms for specified taxonomy found, skipped term deletion." );
				}
			}

			// Now build the arguments for posts
			$posts = get_posts( array(
				'meta_key'       => $this->meta_key,
				'meta_value'     => true,
				'posts_per_page' => -1, // ALL the posts
				'post_author'    => $post_author,
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'fields'         => 'ids',
			) );

			if ( ! empty( $posts ) ) {
				$progress = \WP_CLI\Utils\make_progress_bar( 'Now removing posts', count( $posts ) );
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

			if ( $blog_id && is_multisite() ) {
				restore_current_blog();
			}

			WP_CLI::success( 'Cleanup complete, now put up the broom and get back to coding!' );
		}

		/**
		 * Generates a Random set of posts
		 *
		 * ## OPTIONS
		 *
		 * <number>
		 * : The number of posts to generate
		 *
		 * [--type=<posttype>]
		 * : The post type
		 * ---
		 * default: post
		 * ---
		 *
		 * [--post_status=<status_slug>]
		 * : The post status these posts should be set to.
		 * ---
		 * default: publish
		 * ---
		 *
		 * [--tax=<taxonomy>]
		 * : The taxonomies to tie to the post.
		 * ---
		 * default: none
		 * ---
		 *
		 * [--tax-n=<int>]
		 * : The amount of terms to insert per taxonomy.
		 * ---
		 * default: 3
		 * ---
		 *
		 * [--featured-image]
		 * : Sets a featured image for the post.
		 *
		 * [--image-size=<width,height>]
		 * : Sets the featured image size during download - CAUTION: This downloads the images, so expect a bit of time.
		 * ---
		 * default: 1024,768
		 * ---
		 *
		 * [--img-type=<providerslug>]
		 * : Sets the image provider
		 * ---
		 * default: none
		 * options:
		 *  - abstract
		 *  - sports
		 *  - city
		 *  - people
		 *  - transport
		 *  - animals
		 *  - food
		 *  - nature
		 *  - business
		 *  - cats
		 *  - fashion
		 *  - nightlife
		 *  - fashion
		 *  - technics
		 * ---
		 *
		 * [--author=<id>]
		 * : The post author id
		 * ---
		 * default: 1
		 * ---
		 *
		 * [--site=<site_id>]
		 * : If multisite is enabled, you can specify a site id
		 * ---
		 * default: false
		 * ---
		 */
		public function generate( $args, $assoc_args ) {

			$this->args = $args;
			$this->assoc_args = $assoc_args;

			$post_type      = isset( $assoc_args['type'] ) ? $assoc_args['type'] : 'post';
			$featured_image = isset( $assoc_args['featured-image'] ) ? true : false;
			$number_posts   = isset( $args[0] ) ? intval( $args[0] ) : 1;
			$taxonomies     = isset( $assoc_args['tax'] ) ? explode( ',', $assoc_args['tax'] ) : array();
			$term_count     = isset( $assoc_args['tax-n'] ) ? intval( $assoc_args['tax-n'] ) : 3;
			$post_author    = isset( $assoc_args['author'] ) ? intval( $assoc_args['author'] ) : 1;
			$blog_id        = isset( $assoc_args['site'] ) ? intval( $assoc_args['site'] ) : false;
			$post_status    = isset( $assoc_args['post_status'] ) ? $assoc_args['post_status'] : 'publish';

			if ( $blog_id && is_multisite() ) {
				switch_to_blog( $blog_id );
			}

			$this->wp_version_check();

			if ( 'post' !== $post_type && ! post_type_exists( $post_type ) ) {
				WP_CLI::error( sprintf( 'The %s post type does not exist, make sure it is registered properly.', $post_type ) );
			}

			if ( isset( $assoc_args['img-type'] ) && ! in_array( $assoc_args['img-type'], $this->get_image_types() ) ) {
				WP_CLI::error( sprintf( 'The image provider %s is not available, you may only use one of the following:', $assoc_args['img-type'] ), false );
				\WP_CLI\Utils\format_items( 'table', $this->transform_img_types_to_table(), array( 'Valid Types' ) );
				WP_CLI::error( 'Halting Script' );
			}

			// Validate the author exists
			$user_exists = get_user_by( 'ID', $post_author );
			if ( ! $user_exists ) {
				WP_CLI::error( sprintf( 'User ID %d does not exist within the WordPress database, cannot continue.', $post_author ) );
			}

			$image_size_arr = isset( $assoc_args['image-size'] ) ? explode( ',', $assoc_args['image-size'] ) : array( 1024, 768 );
			if ( 2 !== count( $image_size_arr ) ) {
				WP_CLI::error( "You either have too many, or too little attributes for image size. Ensure you're using a comma delimited string like 1024,768" );
			}

			$taxonomies = $this->validate_taxonomies( $taxonomies );

			// Setup terms
			$term_data = array();
			if ( ! empty( $taxonomies ) && 0 < $term_count ) {

				if ( ! function_exists( 'add_term_meta' ) ) {
					WP_CLI::warning( 'Your installation does not include the add_term_meta() function.' );
					WP_CLI::confirm( 'You will not be able to remove terms created, do you want to continue?' );
				}

				WP_CLI::line( sprintf( 'Generating %1$d separate terms for %2$d taxonomies, this may take awhile.', $term_count, count( $taxonomies ) ) );
				foreach ( $taxonomies as $taxonomy ) {
					$term_names = array();
					for ( $n = 0; $n < $term_count; $n ++ ) {
						$term = $this->get_term();
						if ( empty( $term ) ) {
							continue;
						}
						$term_names[] = ucfirst( $term );
					}

					$this->progress_bar( count( $term_names ), sprintf( 'Terms into the `%s` Taxonomy', $taxonomy ), 'Inserting' );
					foreach ( $term_names as $name ) {
						$term_result = wp_insert_term( $name, $taxonomy );
						$this->progress_bar( 'tick' );
						if ( is_wp_error( $term_result ) ) {
							WP_CLI::warning( sprintf( 'Received an error inserting %1$s term into the %2$s taxonomy: %3$s', $name, $taxonomy, $term_result->get_error_message() ) );
							continue;
						}

						if ( ! isset( $term_result['term_id'] ) ) {
							WP_CLI::warning( sprintf( 'For some reason the term_id key is not set for %1$s term after inserting, instead we got: %2$s', $name, print_r( $term_result, 1 ) ) );
							continue;
						}

						if ( ! isset( $term_data[ $taxonomy ] ) ) {
							$term_data[ $taxonomy ] = array();
						}

						$term_meta_added = add_term_meta( $term_result['term_id'], $this->meta_key, true );
						if ( is_wp_error( $term_meta_added ) ) {
							WP_CLI::warning( sprintf( 'Error setting term meta for deletion: %s', $term_meta_added->get_error_message() ) );
						}

						if ( false === $term_meta_added ) {
							WP_CLI::warning( sprintf( "There was a general error inserting the term meta for term ID #%d", $term_result['term_id'] ) );
						}

						$term_data[ $taxonomy ][] = $term_result['term_id'];
					}
					$this->progress_bar( 'finish' );
				}
			}

			if ( $featured_image ) {
				WP_CLI::warning( "Looks like you're adding featured images, this may be slightly slower." );
			}

			// Now make some posts shall we?
			$this->progress_bar( $number_posts, 'Posts', 'Creating' );
			for ( $i = 0; $i < $number_posts; $i++ ) {
				$post_content = $this->get_post_content();
				if ( empty( $post_content ) ) {
					continue;
				}

				$post_title = $this->get_title_from_text( $post_content );
				if ( empty( $post_title ) ) {
					continue;
				}

				$post_result = wp_insert_post( $post_insert_args = array(
					'post_type'    => $post_type,
					'post_title'   => $post_title,
					'post_content' => $post_content,
					'post_status'  => $post_status,
					'post_author'  => $post_author,
					'meta_input' => array(
						$this->meta_key => true,
					),
				), true );

				if ( is_wp_error( $post_result ) ) {
					WP_CLI::warning( sprintf( 'Received an error when trying to insert a post, got: %s', $post_result->get_error_message() ) );
					continue;
				}

				if ( isset( $term_data ) && ! empty( $term_data ) ) {
					foreach ( $term_data as $taxonomy => $terms ) {
						shuffle( $terms );
						$random_terms = array_slice( $terms, 0, mt_rand( 1, count( $terms ) ) );
						$is_set = wp_set_object_terms( $post_result, $random_terms, $taxonomy );
						if ( false === $is_set ) {
							WP_CLI::warning( sprintf( 'Apparently the post_id of %d is not actually an integer.', $post_result ) );
							continue;
						}

						if ( is_wp_error( $is_set ) ) {
							WP_CLI::warning( sprintf( 'Got an error when attempting to assign terms to post id %d: %s', $post_result, $is_set->get_error_message() ) );
							continue;
						}
					}
				}

				if ( $featured_image ) {
					$image_id = $this->download_image( $image_size_arr, $post_result );
					if ( empty( $image_id ) ) {
						continue;
					}

					update_post_meta( $image_id, $this->meta_key, true );

					set_post_thumbnail( $post_result, $image_id );
				}

				$this->progress_bar( 'tick' );
			}

			$this->progress_bar( 'finish' );
			WP_CLI::success( 'Awesomesauce! You now have some test data, now go out there and build something amazing!' );

			if ( $blog_id && is_multisite() ) {
				restore_current_blog();
			}

		}

		/**
		 * Generates a randomly sized title from a block of text.
		 * @param $text
		 *
		 * @author JayWood
		 * @return string
		 */
		private function get_title_from_text( $text ) {
			$title = array_values( array_filter( explode( "\n", $text ) ) );

			if ( empty( $title ) || ! is_array( $title ) ) {
				WP_CLI::warning( sprintf( 'Got an error when working with title, we got: %s', $title ) );
				return '';
			}

			$offset = isset( $title[1] ) ? $title[1] : $title[0];
			return wp_trim_words( $offset, mt_rand( 1, 12 ), '' );
		}

		/**
		 * Randomizes rather or not to use lists, links, decorate, code, etc... in the post content.
		 *
		 * @author JayWood
		 * @return string
		 */
		private function randomize_lipsum_params() {
			$out = array();

			$use_lists = mt_rand( 1, 3 ) == 2 ? true : false;
			if ( $use_lists ) {
				$types = array( 'ol', 'ul' );
				$out[] = $types[ mt_rand( 0, 1 ) ];
			}

			$use_links = mt_rand( 1, 4 ) == 2 ? true : false;
			if ( $use_links ) {
				$out[] = 'link';
			}

			$use_decorate = mt_rand( 1, 5 ) == 3 ? true : false;
			if ( $use_decorate ) {
				$out[] = 'decorate';
			}

			$use_code = mt_rand( 1, 5 ) == 3 ? true : false;
			if ( $use_code ) {
				$out[] = 'code';
			}

			if ( empty( $out ) ) {
				return '';
			}

			return implode( '/', $out );
		}

		/**
		 * Gets the post content text, if possible.
		 *
		 * @author JayWood
		 * @return string
		 */
		private function get_post_content() {
			$paragraphs = mt_rand( 1, 10 );
			// $request = wp_safe_remote_get( sprintf( 'https://baconipsum.com/api/?type=meat-and-filler&paras=%d&format=text', $paragraphs ) );

			$other_params = $this->randomize_lipsum_params();

			$url = sprintf( 'http://loripsum.net/api/%1$d/medium/%2$s', $paragraphs, $other_params );

			$request = wp_safe_remote_get( $url );
			if ( is_wp_error( $request ) ) {
				WP_CLI::warning( sprintf( 'Received an error when trying to make bacon: %s', $request->get_error_message() ) );
				return '';
			}

			return wp_remote_retrieve_body( $request );
		}

		/**
		 * Contacts a random word generator for terms.
		 * @author JayWood
		 * @return string
		 */
		private function get_term() {
			$request = wp_safe_remote_get( 'http://randomword.setgetgo.com/get.php' );
			if ( is_wp_error( $request ) ) {
				WP_CLI::warning( sprintf( 'Error getting a random word for a term: %s', $request->get_error_message() ) );
				return '';
			}

			return wp_remote_retrieve_body( $request );
		}

		/**
		 * Downloads the images from placekitten.com or lorempixel.com
		 * @param array $sizes
		 * @param int $post_id
		 *
		 * @author JayWood
		 *
		 * @return int|null The new attachment ID
		 */
		private function download_image( $sizes, $post_id = 0 ) {
			$sizes = implode( '/', array_filter( $sizes ) );

			$img_type = isset( $this->assoc_args['img-type'] ) ? $this->assoc_args['img-type'] : '';

			$url = 'http://lorempixel.com/' . $sizes;
			if ( ! empty( $img_type ) ) {
				$url .= '/' . $img_type;
			}

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$tmp        = download_url( $url );
			$type       = image_type_to_extension( exif_imagetype( $tmp ) );
			$file_array = array(
				'name'     => 'placeholderImage_' . mt_rand( 30948, 40982 ) . '_' . str_replace( '/', 'x', $sizes ) . $type,
				'tmp_name' => $tmp,
			);

			if ( is_wp_error( $tmp ) ) {
				@unlink( $tmp );
				WP_CLI::warning( sprintf( 'Got an error with tmp: %s', $tmp->get_error_message() ) );
				return null;
			}

			$id = media_handle_sideload( $file_array, $post_id );
			if ( is_wp_error( $id ) ) {
				@unlink( $tmp );
				WP_CLI::warning( sprintf( 'Got an error with id: %s', $id->get_error_message() ) );
				return null;
			}
			return $id;
		}

		private function get_image_types() {
			return array(
				'abstract',
				'sports',
				'city',
				'people',
				'transport',
				'animals',
				'food',
				'nature',
				'business',
				'cats',
				'fashion',
				'nightlife',
				'fashion',
				'technics',
			);
		}

		/**
		 * Checks taxonomy array passed in against registered taxonomies
		 *
		 * @param $taxonomies
		 *
		 * @author JayWood
		 */
		private function validate_taxonomies( $taxonomies ) {
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
					WP_CLI::warning( sprintf( "The following taxonomies seem to not be registered: %s", implode( ',', $errors ) ) );
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
		 * Compares the current installed version against script requirements.
		 *
		 * @author JayWood
		 */
		private function wp_version_check() {
			if ( ! \WP_CLI\Utils\wp_version_compare( self::WP_VERSION, '>=' ) ) {
				WP_CLI::error( sprintf( 'Your WordPress needs updated to the latest version, this script requires v%s or later.', self::WP_VERSION ) );
			}
		}

		private function transform_img_types_to_table() {
			$types = $this->get_image_types();
			$out = array();
			foreach ( $types as $img ) {
				$out[] = array(
					'Valid Types' => $img,
				);
			}
			return $out;
		}

		/**
		 * Wrapper function for WP_CLI Progress bar
		 *
		 * @param int|string $param   If integer, start progress bar, if string, should be tick or finish.
		 * @param string $object_type Type of object being traversed
		 * @param string $action      Action being performed
		 *
		 * @return bool|object False on failure, WP_CLI progress bar object otherwise.
		 */
		private function progress_bar( $param, $object_type = '', $action = 'Migrating' ) {

			if ( $param && is_numeric( $param ) ) {
				$this->progress_bar = \WP_CLI\Utils\make_progress_bar( "$action $param $object_type.", $param );
			} elseif ( $this->progress_bar && 'tick' == $param ) {
				$this->progress_bar->tick();
			} elseif ( $this->progress_bar && 'finish' == $param ) {
				$this->progress_bar->finish();
			}

			return $this->progress_bar;
		}

	}

	WP_CLI::add_command( 'jw-random', 'JW_Random_Posts' );
}
