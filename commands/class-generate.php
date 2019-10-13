<?php
/**
 * Handles the initialization of the Generate command.
 *
 * @package plugish\CLI\RandomPosts
 * @sub-package Command
 */
namespace plugish\CLI\RandomPosts\Command;

use Faker\Factory;
use plugish\CLI\RandomPosts\Util\HTML_Randomizer;
use WP_CLI;
use function WP_CLI\Utils\make_progress_bar;
use function WP_CLI\Utils\wp_version_compare;

class Generate {

	/**
	 * The minimum WordPress version required to run this script.
	 */
	const WP_VERSION = '4.6';

	/**
	 * @var string A meta key that is used throughout the script to allow removal of the data later.
	 */
	const META_KEY = '_jwrp_test_data';

	/**
	 * @param string The key for MD5 checks.
	 */
	const IMAGE_MD5_KEY = '_jwrp_image_md5';

	/**
	 * The WP-CLI Command Arguments
	 * Nothing to see here.
	 * @var array
	 */
	public $args, $assoc_args;

	/**
	 * @var object Instance of the progress bar.
	 */
	private $progress_bar;

	/**
	 * @var \Faker\Generator
	 */
	private $faker;

	/**
	 * @param array $args
	 * @param array $assoc_args
	 *
	 * @throws WP_CLI\ExitException
	 */
	public function __invoke( $args, $assoc_args ) {
		$this->wp_version_check();

		// Properties so it can be reused later.
		$this->args       = $args;
		$this->assoc_args = $assoc_args;
		$this->faker      = Factory::create();

		// Setup some variables.
		$post_type      = ! empty( $assoc_args['type'] ) ? $assoc_args['type'] : 'post';
		$featured_image = ! empty( $assoc_args['featured-image'] ) ? true : false;
		$taxonomies     = ! empty( $assoc_args['taxonomies'] ) ? $assoc_args['taxonomies'] : [];
		$term_count     = ! empty( $assoc_args['term-count'] ) ? intval( $assoc_args['term-count'] ) : 3;
		$img_size       = ! empty( $assoc_args['image-size'] ) ? $assoc_args['image-size'] : '1024,768';
		$number_posts   = ! empty( $args[0] ) ? intval( $args[0] ) : 1;
		$post_status    = ! empty( $assoc_args['post-status'] ) ? $assoc_args['post-status'] : 'publish';
		$post_author    = ! empty( $assoc_args['post-author'] ) ? $this->get_author_id( $assoc_args['post-author'] ) : 1;

		if ( ! post_type_exists( $post_type ) ) {
			WP_CLI::error( sprintf( 'The %s post type does not exist, make sure it is registered properly.', $post_type ) );
		}

		$image_size_arr = array_filter( explode( ',', $img_size ) );
		if ( ! empty( $image_size_arr ) && 2 !== count( $image_size_arr ) ) {
			WP_CLI::error( "You either have too many, or too little attributes for image size. Ensure you're using a comma delimited string like 1024,768" );
		}

		if ( $featured_image ) {
			WP_CLI::warning( 'You are using featured images, this can take some time.' );
		}

		if ( ! empty( $taxonomies ) ) {
			$taxonomies = array_filter( explode( ',', $taxonomies ) );
		}
		$taxonomies = $this->validate_taxonomies( $taxonomies ?? [] );
		$term_data  = $this->get_terms( $taxonomies, $term_count );

		// Begin the loop
		for ( $i = 0; $i < $number_posts; $i++ ) {
			$post_content = $this->get_post_content();
			if ( empty( $post_content ) ) {
				continue;
			}

			$post_title = $this->get_post_title();
			if ( empty( $post_title ) ) {
				continue;
			}

			$post_result = wp_insert_post( array(
				'post_type'    => $post_type,
				'post_title'   => $post_title,
				'post_content' => $post_content,
				'post_status'  => $post_status,
				'post_author'  => $post_author,
				'meta_input'   => array(
					self::META_KEY => true,
				),
			), true );

			if ( is_wp_error( $post_result ) ) {
				WP_CLI::debug( sprintf( 'Received an error when trying to insert a post, got: %s', $post_result->get_error_message() ) );
				continue;
			}

			if ( ! empty( $term_data ) ) {
				foreach ( $term_data as $taxonomy => $terms ) {
					shuffle( $terms );
					$random_terms = array_slice( $terms, 0, mt_rand( 1, count( $terms ) ) );
					$is_set       = wp_set_object_terms( $post_result, $random_terms, $taxonomy );
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

				update_post_meta( $image_id, self::META_KEY, true );

				set_post_thumbnail( $post_result, $image_id );
			}

			$this->progress_bar( 'tick' );
		}

		$this->progress_bar( 'finish' );
		WP_CLI::success( 'Awesomesauce! You now have some test data, now go out there and build something amazing!' );
	}

	/**
	 * Downloads the images from lorempixel.com
	 *
	 * @param array $sizes   An array of sizes.
	 * @param int   $post_id The post id.
	 *
	 * @return int|null The new attachment ID
	 */
	private function download_image( $sizes, $post_id = 0 ) {
		global $wpdb;
		$sizes = implode( '/', array_filter( $sizes ) );

		$img_type = isset( $this->assoc_args['img-type'] ) ? $this->assoc_args['img-type'] : 'business';

		$url = 'http://lorempixel.com/' . $sizes;
		if ( ! empty( $img_type ) ) {
			$url .= '/' . $img_type;
		}
		$url .= '/';

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			@unlink( $tmp ); // @codingStandardsIgnoreLine
			WP_CLI::debug( sprintf( 'Got an error with tmp: %s', $tmp->get_error_message() ) );
			return null;
		}

		$file_md5 = md5_file( $tmp );
		$id       = $wpdb->get_var( $wpdb->prepare( "select post_id from {$wpdb->postmeta} where meta_key = %s and meta_value = %s", self::IMAGE_MD5_KEY, $file_md5 ) );
		if ( $id ) {
			WP_CLI::line( 'Found an id for the image - ' . $id );
			return absint( $id );
		}

		$type       = getimagesize( $tmp )['mime'];
		$extension  = explode( '/', $type );
		$file_array = array(
			'name'     => 'placeholderImage_' . mt_rand( 30948, 40982 ) . '_' . str_replace( '/', 'x', $sizes ) . '.' . end( $extension ),
			'tmp_name' => $tmp,
		);

		$id = media_handle_sideload( $file_array, $post_id );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp ); // @codingStandardsIgnoreLine
			WP_CLI::debug( sprintf( 'Got an error with id: %s', $id->get_error_message() ) );
			return null;
		}

		update_post_meta( $id, self::IMAGE_MD5_KEY, $file_md5 );
		update_post_meta( $id, self::META_KEY, true );

		return $id;
	}

	/**
	 * Gets a list of terms
	 *
	 * @param array $taxonomies An array of taxonomy slugs to get terms from.
	 *
	 * @return array
	 */
	private function get_terms( array $taxonomies, int $term_count ) : array {
		// Setup terms
		$term_data = [];
		if ( empty( $taxonomies ) || empty( $term_count ) ) {
			return $term_data;
		}

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

				// Some security fixes.
				$name = sanitize_text_field( $name );

				// Check if the term exists prior to inserting it.
				if ( term_exists( $name, $taxonomy ) ) {
					WP_CLI::debug( sprintf( 'Term name - %s - already exists.', $name ) );
					$this->progress_bar( 'tick' );
					continue;
				}

				$term_result = wp_insert_term( $name, $taxonomy );
				if ( is_wp_error( $term_result ) ) {
					WP_CLI::debug( sprintf( 'Received an error inserting %1$s term into the %2$s taxonomy: %3$s', $name, $taxonomy, $term_result->get_error_message() ) );
					$this->progress_bar( 'tick' );
					continue;
				}

				if ( ! isset( $term_result['term_id'] ) ) {
					WP_CLI::debug( sprintf( 'For some reason the term_id key is not set for %1$s term after inserting, instead we got: %2$s', $name, print_r( $term_result, 1 ) ) );
					$this->progress_bar( 'tick' );
					continue;
				}

				if ( ! isset( $term_data[ $taxonomy ] ) ) {
					$term_data[ $taxonomy ] = array();
				}

				$term_meta_added = add_term_meta( $term_result['term_id'], self::META_KEY, true );
				if ( is_wp_error( $term_meta_added ) ) {
					WP_CLI::debug( sprintf( 'Error setting term meta for deletion: %s', $term_meta_added->get_error_message() ) );
				}

				if ( false === $term_meta_added ) {
					WP_CLI::debug( sprintf( 'There was a general error inserting the term meta for term ID #%d', $term_result['term_id'] ) );
				}

				$term_data[ $taxonomy ][] = $term_result['term_id'];
				$this->progress_bar( 'tick' );
			}
			$this->progress_bar( 'finish' );
		}

		return $term_data;
	}

	/**
	 * Checks taxonomy array passed in against registered taxonomies
	 *
	 * @param array $taxonomies The taxonomy slugs.
	 *
	 * @return array
	 */
	private function validate_taxonomies( array $taxonomies ) : array {
		if ( empty( $taxonomies ) ) {
			return $taxonomies;
		}

		$taxonomies = array_filter( $taxonomies );
		$errors     = array();
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

		return $taxonomies;
	}

	/**
	 * Compares the current installed version against script requirements.
	 *
	 * @throws WP_CLI\ExitException
	 */
	public function wp_version_check() {
		if ( ! wp_version_compare( self::WP_VERSION, '>=' ) ) {
			WP_CLI::error( sprintf( 'Your WordPress needs updated to the latest version, this script requires v%s or later.', self::WP_VERSION ) );
		}
	}

	/**
	 * Gets the Author ID from the passed in data.
	 * @param string|int $author_id_name_or_email Author info from CLI.
	 *
	 * @return int
	 * @throws WP_CLI\ExitException
	 */
	public function get_author_id( $author_id_name_or_email ) : int {
		if ( is_int( $author_id_name_or_email ) ) {
			return $author_id_name_or_email;
		}

		if ( is_email( $author_id_name_or_email ) ) {
			$author = get_user_by_email( $author_id_name_or_email );
		} else {
			$author = get_user_by( 'slug', $author_id_name_or_email );
		}

		if ( ! isset( $author->ID ) ) {
			WP_CLI::error( sprintf('There was an error getting the author ID for %s, verify they exist', $author_id_name_or_email ) );
		}

		return absint( $author->ID );
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
	public function progress_bar( $param, $object_type = '', $action = 'Migrating' ) {
		if ( $param && is_numeric( $param ) ) {
			$this->progress_bar = make_progress_bar( "$action $param $object_type.", $param );
		} elseif ( $this->progress_bar && 'tick' == $param ) {
			$this->progress_bar->tick();
		} elseif ( $this->progress_bar && 'finish' == $param ) {
			$this->progress_bar->finish();
		}

		return $this->progress_bar;
	}

	/**
	 * Gets the post content text, if possible.
	 *
	 * @author JayWood
	 * @return string
	 */
	private function get_post_content() {
		$html_randomizer = new HTML_Randomizer( $this->faker );
		return $html_randomizer->random_html( 4, 10, 256 );
	}

	private function get_post_title() {
		return $this->faker->sentence( $nbWords = 6, $variableNbWords = true );
	}

	/**
	 * Contacts a random word generator for terms.
	 * @author JayWood
	 * @return string
	 */
	private function get_term() {
		return $this->faker->word;
	}
}
