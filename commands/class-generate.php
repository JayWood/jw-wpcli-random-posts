<?php

namespace plugish\CLI\RandomPosts\Command;

use Exception;
use WP_CLI;
use function WP_CLI\Utils\format_items;
use function WP_CLI\Utils\make_progress_bar;
use function WP_CLI\Utils\wp_version_compare;

class Generate {
	/**
	 * The WP-CLI Command Arguments
	 * Nothing to see here.
	 * @var array
	 */
	private $args, $assoc_args;

	/**
	 * Rather or not to do the actual import.
	 * @var bool
	 */
	private $is_wet_run = false;

	/**
	 * @var object Instance of the progress bar.
	 */
	private $progress_bar;

	/**
	 * @var \Faker\Generator
	 */
	private $faker;

	/**
	 * The minimum WordPress version required to run this script.
	 */
	const WP_VERSION = '4.6';

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

		$this->is_wet_run = ! empty( $assoc_args['wet'] );
		$this->faker      = Faker\Factory::create();

		// Setup some variables.
		$post_type      = isset( $assoc_args['type'] ) ? $assoc_args['type'] : 'post';
		$featured_image = isset( $assoc_args['featured-image'] ) ? true : false;
		$number_posts   = isset( $args[0] ) ? intval( $args[0] ) : 1;
		$taxonomies     = isset( $assoc_args['taxonomies'] ) ? explode( ',', $assoc_args['taxonomies'] ) : array();
		$term_count     = isset( $assoc_args['term-count'] ) ? intval( $assoc_args['term-count'] ) : 3;
		$post_author    = isset( $assoc_args['author'] ) ? intval( $assoc_args['author'] ) : 1;
		$post_status    = isset( $assoc_args['post-status'] ) ? $assoc_args['post-status'] : 'publish';
		$img_type       = isset( $assoc_args['image-type'] ) ? $assoc_args['image-type'] : 'business';
		$img_size       = isset( $assoc_args['image-size'] ) ? $assoc_args['image-size'] : '1024,768';
		$author         = isset( $assoc_args['post-author'] ) ? $this->get_author_id( $assoc_args['post-author'] ) : 1;

		if ( ! post_type_exists( $post_type ) ) {
			WP_CLI::error( sprintf( 'The %s post type does not exist, make sure it is registered properly.', $post_type ) );
		}

		$image_size_arr = explode( ',', $img_size );
		if ( 2 !== count( $image_size_arr ) ) {
			WP_CLI::error( "You either have too many, or too little attributes for image size. Ensure you're using a comma delimited string like 1024,768" );
		}

		$taxonomies = $this->validate_taxonomies( $taxonomies );
		$terms      = $this->get_terms( $taxonomies, $term_count );
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
		if ( ! empty( $taxonomies ) && 0 < $term_count ) {
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

					$term_meta_added = add_term_meta( $term_result['term_id'], $this->meta_key, true );
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
	 * @throws WP_CLI\ExitException
	 */
	private function wp_version_check() {
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
	private function get_author_id( $author_id_name_or_email ) : int {
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
	private function progress_bar( $param, $object_type = '', $action = 'Migrating' ) {
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
		return $this->faker->paragraphs( mt_rand( 1, 10 ) );
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

try {
	WP_CLI::add_command( 'jw-random generate', __NAMESPACE__ . '\\Generate', [
		'shortdesc' => 'Generates posts, terms and attachments based on options passed.',
		'synopsis'  => [
			[
				'type'        => 'flag',
				'name'        => 'wet',
				'optional'    => true,
				'description' => 'Actually runs the command.',
			],
			[
				'type'        => 'assoc',
				'name'        => 'type',
				'optional'    => true,
				'default'     => 'post',
				'description' => 'A comma separated list ( no spaces ) of post type slugs to generate for.',
			],
			[
				'type'        => 'flag',
				'name'        => 'featured-image',
				'optional'    => true,
				'default'     => false,
				'description' => 'Enables featured image support ( see image types )',
			],
			[
				'type'        => 'assoc',
				'optional'    => true,
				'name'        => 'image-size',
				'default'     => '1024,768',
				'description' => 'A comma delimited width and height value for images to import.',
			],
			[
				'type'        => 'assoc',
				'name'        => 'image-type',
				'optional'    => true,
				'description' => 'The type of featured images.',
				'default'     => 'business',
				'options'     => [
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
					'technics'
				],
			],
			[
				'type'        => 'positional',
				'name'        => 'num_posts',
				'optional'    => true,
				'default'     => 10,
				'description' => 'The number of posts to generate for each post type.',
			],
			[
				'type'        => 'assoc',
				'name'        => 'taxonomies',
				'optional'    => true,
				'description' => 'A comma separated list ( no spacing ) of taxonomy slugs to generate terms for.',
			],
			[
				'type'        => 'assoc',
				'name'        => 'term-count',
				'optional'    => true,
				'description' => 'The amount of terms to generate for each taxonomy slug. Terms are randomly assigned to posts.',
			],
			[
				'type'        => 'assoc',
				'name'        => 'post-author',
				'optional'    => true,
				'description' => 'The post author ID, email or Login to assign the posts to.',
				'default'     => 1,
			],
			[
				'type'        => 'assoc',
				'name'        => 'post-status',
				'optional'    => true,
				'description' => 'The post status to set the post to.',
			],
		],
	] );
} catch ( Exception $e ) {
	die( $e->getMessage() ); // @codingStandardsIgnoreLine Do not complain about escaping an exception.
}

