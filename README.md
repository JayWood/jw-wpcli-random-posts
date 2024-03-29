# WP CLI Random Posts Generator

This WP CLI posts generator, unlike the core generator in WP CLI, supports the following:

* Terms
* Term Counts
* Taxonomies
* Post Types
* Post Counts
* Post Author
* Post Status
* Featured Images
* Image Download sizes
* Multi-site ( specify site id if necessary )

**NEW** - using `wp jw-random cleanup <options>` this script now cleans up after itself.

> Thanks to @fzaninotto for the [Faker](https://github.com/fzaninotto/Faker) library.

## What this does NOT do
Currently this CLI command does not support meta-data, mainly due to the amount of commands you would need to run for large sites. Still a great script if you need to generate some placeholder posts fast, especially with featured images and terms.

## Installation
Installing the random post generator is SUPER easy, for the latest and greatest, do the following:
* `wp package install jaywood/jw-wpcli-random-posts:dev-master`

If you'd like a specific version, say 2.0, it's this simple:
* `wp package install jaywood/jw-wpcli-random-posts:2.0`

## Sample Commands

### Generate 50 posts, no feature image
Possibly the simplest way to use the generator.
* `wp jw-random generate 50`

### Create 10 posts with featured business images for an author
First find the author you want to attach the Post to
* `wp user list`

Now you know the author ID just use the `--author` flag like so:
* `wp jw-random generate 10 --author=13 --featured-image`

The author field also supports **slug** ( login ), and **email**.

### Create 10 posts with categories, tags, and featured images ( the usual stuff )
_( `--term-count` tells the script to also add 15 terms to each taxonomy )_
* `wp jw-random generate 10 --featured-image --taxonomies=category,post_tag --term-count=15`

### Clean up posts, terms, and media that was generated
_( `--force-delete` permanently deletes posts and media instead of just trashing them )_
* `wp jw-random cleanup --tax=post_tag,category --force-delete`

## Options

In the interest of keeping this readme slim, all options have been moved [to the Wiki](https://github.com/JayWood/jw-wpcli-random-posts/wiki).

## Changelog

### 2.0
* A bit of house cleaning.
* Moved generate and cleanup commands to separate files.
* Renamed a lot of CLI arguments to make more sense.
* Now using the Faker library instead of relying on API calls.
* Author now supports email, slug ( login ), or ID.
* Removed the media flag, no sense in having it if post type is empty it defaults to all.
* Removed the `--site` option, use `--url` instead.
* Made use of `md5_file()` to prevent image duplication within the media library.

### 1.4
* Removed some CLI default values causing the script to complain when it shouldn't have.
* Cleaned up get_post_content() a bit.
* Ignore some PHPCS complaints about @unlink
* Small message updates.
* Some PHPCS fixes ( alignment, assignments, etc.. )

### 1.3
* Changed ipsum generator to loripsum.net, fixes #11
* Update API url for random word getter ( setgetgo )
* Remove dependancy on exif PHP library.

### 1.2
* Fixed - [#6](https://github.com/JayWood/jw-wpcli-random-posts/issues/6) - Error message duplication
* Fixed - [#10](https://github.com/JayWood/jw-wpcli-random-posts/issues/10) - Removed a lot of log messages, added progress bars in their place.
* Changed - `posts` command to `generate` - makes more sense.
* Remove the flag `--n` for specfying post count, make post count required positional argument instead.
* Significant readme updates.

### 1.1
* Fixed possible bug with `post_type_exists` checks on multisite installs.
* Added cleanup method to allow users to undo/remove posts, terms, and media that was added via this generator. _This is not backwards compatible, with earlier versions, sorry guys!_
* Added `post_status` flag for generating posts, you can now set your own status.   
**Note:** status does not validate, so you can technically set this to anything, its up to you as a developer to expose custom statuses in the admin.
* Added taxonomy validation: Script will now validate if a taxonomy is even registered, and allow you to continue if you want.

### 1.0
Initial Release
