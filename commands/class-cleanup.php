<?php

namespace plugish\CLI\RandomPosts\Command;

use Exception;
use WP_CLI;

class Cleanup {

	public function __invoke( $args, $assoc_args ) {

	}
}

try {
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
