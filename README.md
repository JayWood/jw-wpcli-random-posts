# WP CLI Random Posts Generator

This WP CLI posts generator, unlike the core generator in WP CLI, supports the following:

* Terms
* Term Counts
* Taxonomies
* Post Types
* Post Counts
* Post Author
* Featured Images ( thanks to [lorempixel.com](http://lorempixel.com) )
* Featured Image Types ( thanks to [lorempixel.com](http://lorempixel.com) )
* Image Download sizes
* Multi-site ( specify site id if necessary )

> Thanks to [BaconIpsum.com](https://baconipsum.com/) for providing the API for the content and [Justin Sternberg](https://twitter.com/Jtsternberg) for letting me know about it in his old code :) - Also thanks to [SetGetGo.com](http://randomword.setgetgo.com/) for the random word generator API 

## What this does NOT do
Currently this CLI command does not support meta-data, mainly due to the amount of commands you would need to run for large sites. Still a great script if you need to generate some placeholder posts fast, especially with featured images and terms.

## Cleanup Options

>wp jw-random cleanup <options>

**--type=\<post_type\>** - **Default: post**   
Determines the post type of the generated posts.

**--author=\<id\>** - **Default: 1**   
Sets the author ID of the posts, defaults to the site admin ( typically ID 1 ).

**--tax=\<taxonomy_slug\>**   
What taxonomies to generate terms for, if not set, no terms will be created.
> Taxonomy slugs can be separated by commas if you need more than one.

**--site=\<site_id\>**   
IF this is set, and the site is multi-site enabled.  A switch to blog occurs to this blog ID so posts are imported to this ID.

**--media**
Shorthand for typing `--type=attachment` and will save you from typing a second command.

## Generate Options

>wp jw-random posts <options>

**--type=\<post_type\>** - **Default: post**   
Determines the post type of the generated posts.

**--n=\<post_count\>** - **Default: 1**   
How many posts you want to generate

**--author=\<id\>** - **Default: 1**   
Sets the author ID of the posts, defaults to the site admin ( typically ID 1 ).

**--tax=\<taxonomy_slug\>**   
What taxonomies to generate terms for, if not set, no terms will be created.
> Taxonomy slugs can be separated by commas if you need more than one.

**--tax-n=\<term_count\>** - **Default 3**   
How many terms to generate _per_ taxonomy slug.

**--featured-image**   
If this is set, featured images will be set for the posts.

**--image-size=\<width,height\>** - **Default: 1024,768**      
Determines the image size from lorempixel.com when downloading. It's typically a good idea to set this large enough so your image resizing can handle it without squishing or stretching. 

**--img-type=\<provider_slug\>** - **Default: random**      
Sets the image category from lorempixel.com, the following options are available:

* abstract
* sports
* city
* people
* transport
* animals
* food
* nature
* business
* cats
* fashion
* nightlife
* fashion
* technics

**--site=\<site_id\>**   
IF this is set, and the site is multi-site enabled.  A switch to blog occurs to this blog ID so posts are imported to this ID.

## Changelog

### 1.1
* Added cleanup method to allow users to undo/remove posts, terms, and media that was added via this generator. _This is not backwards compatible, with earlier versions, sorry guys!_
* Added taxonomy validation: Script will now validate if a taxonomy is even registered, and allow you to continue if you want.


### 1.0
Initial Release