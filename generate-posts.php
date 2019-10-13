<?php
/**
 * Handles the initialization of the commands.
 *
 * @package plugish\CLI\RandomPosts
 */

namespace plugish\CLI\RandomPosts\Command;

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	require_once 'vendor/autoload.php';

	try {
		/*
		 * The Generate Command.
		 */
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

		/*
		 * The Cleanup Command.
		 */
		WP_CLI::add_command( 'jw-random cleanup', __NAMESPACE__ . '\\Cleanup', [
			'shortdesc' => '',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'type',
					'optional'    => true,
					'description' => 'A comma separated list ( no spaces ) of post type slugs to cleanup.',
				],
				[
					'type'        => 'flag',
					'name'        => 'force-delete',
					'optional'    => true,
					'default'     => false,
					'description' => 'Forcefully deletes, skips the trash.',
				],
				[
					'type'        => 'assoc',
					'name'        => 'taxonomies',
					'optional'    => true,
					'description' => 'A comma separated list ( no spacing ) of taxonomy slugs to cleanup.',
				],
				[
					'type'        => 'assoc',
					'name'        => 'post-author',
					'optional'    => true,
					'description' => 'The post author ID, email or Login to cleanup posts for.',
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
		die( $e->getMessage() );
	}
}
