# WP CLI Random Posts Generator

This WP CLI posts generator, unlike the core generator in WP CLI, supports the following:

* Terms
* Term Counts
* Taxonomies
* Post Types
* Post Counts
* Post Author
* Post Status
* Featured Images ( thanks to [lorempixel.com](http://lorempixel.com) )
* Featured Image Types ( thanks to [lorempixel.com](http://lorempixel.com) )
* Image Download sizes
* Multi-site ( specify site id if necessary )

**NEW** - using `wp jw-random cleanup <options>` this script now cleans up after itself.

> Thanks to [Loripsum.net](http://loripsum.net/) for providing the API for the content - Also thanks to [SetGetGo.com](http://randomword.setgetgo.com/) for the random word generator API
 
## Support on Beerpay
Enjoy this nifty tool, or want to see a feature come to life, buy me a few :beers: and let's do this!

[![Beerpay](https://beerpay.io/JayWood/jw-wpcli-random-posts/badge.svg?style=beer-square)](https://beerpay.io/JayWood/jw-wpcli-random-posts)  [![Beerpay](https://beerpay.io/JayWood/jw-wpcli-random-posts/make-wish.svg?style=flat-square)](https://beerpay.io/JayWood/jw-wpcli-random-posts?focus=wish)

## What this does NOT do
Currently this CLI command does not support meta-data, mainly due to the amount of commands you would need to run for large sites. Still a great script if you need to generate some placeholder posts fast, especially with featured images and terms.

## Installation
Installing the random post generator is SUPER easy, for the latest and greatest, do the following:
* `wp package install jaywood/jw-wpcli-random-posts:dev-master`

If you'd like a specific version, say 1.1, it's this simple:
* `wp package install jaywood/jw-wpcli-random-posts:1.1`

## Sample Commands

### Generate 50 posts, no feature image
Possibly the simplest way to use the generator.
* `wp jw-random generate 50`

### Create 10 posts with featured business images for an author
First find the author you want to attach the Post to
* `wp user list`

Now you know the author ID just use the `--author` flag like so:
* `wp jw-random generate 10 --author=13 --featured-image --img-type=business`

### Create 10 posts with categories, tags, and featured images ( the usual stuff )
_( `--tax-n` tells the script to also add 15 terms to each taxonomy )_
* `wp jw-random generate 10 --featured-image --tax=category,post_tag --tax-n=15`

### Clean up posts, terms, and media that was generated
_( `--force-delete` permanently deletes posts and media instead of just trashing them )_
* `wp jw-random cleanup --tax=post_tag,category --media --force-delete`

## Options

In the interest of keeping this readme slim, all options have been moved [to the Wiki](https://github.com/JayWood/jw-wpcli-random-posts/wiki).

## Changelog

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
