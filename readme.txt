=== Shared Article Repository ===
Contributors: iverok
Tags: Shared Articles
Requires at least: 4.4.2
Tested up to: 5.5.1
Stable tag: trunk
License: AGPLv3 or later
License URI: http://www.gnu.org/licenses/agpl-3.0.html

Implements a database of shared articles that can be published to and subscribed to by using the Article Adopter client plugin.

== Description ==

This plugin implements a database of shared articles, posts and pages;
a client using the Article Adopter plugin can publish local posts and
pages to this repository, and subscribe to shared articles published by other 
clients to the repository.

The database itself is implemented as a custom post type added by this
plugin, and the API used is the WP REST api. Therefore the WP REST API
plugin is currently a requirement. The Wordpress instance must also
use 'pretty' permalinks for the same reason: they are required by the
REST API.

To add a participating Wordpress installation, you will need to add a
new user to the user database, with the role "Library", added by this
plugin. This role has no privileges except for adding to and subscribing
to the repository. To connect, the users will have to add a public key
generated by the plugin as this is used for authentication.

To remove an article from the repository, it can be moved to the
trash. It should not be completely deleted, because the clients will
use the 'trash' status of the article to get synchronized. It is also
possible to add categories to the shared articles, but most operations on
the shared articles will be overwritten by the client libraries when/if
these update the posts. Therefore the shared articles can be considered
almost read-only to the database.

== Installation ==

1. Install the WP REST API plugin if neccessary.
1. Ensure that the permalink options are for "pretty" permalinks - the post name variant.
1. Upload the plugin files to the `/wp-content/plugins/shared-article-repository` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Add the participating libraries by adding users with the Library role. 
1. Make the library users install the Article Adopter plugin


== Frequently Asked Questions ==

None yet.

== Screenshots ==

None yet.

== Changelog ==

= 0.10 =
Testing for latest WP version

= 0.09 =
Updated translations

= 0.08 =
Auto-tag of incoming articles based on the author

= 0.07 =
Add REST path for getting number of subscribers to a post

= 0.06 =
Fix support for categories and tags after the REST api changes in 4.7

= 0.05 =
Internationalization added, with Norwegian as a test case

= 0.04 =
Allow use of iframe in shared posts

= 0.03 =
* Fix a bug/issue in how excerpts are copied

= 0.02 = 
* Added support for excerpt

= 0.01 =
* Initial release

