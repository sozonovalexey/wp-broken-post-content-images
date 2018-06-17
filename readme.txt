=== Broken Post Content Images ===
Contributors: sozonovalexey
Website link: https://sozonov-alexey.ru/
Tags: images, pictures, broken images, broken pictures, SEO, admin, post
Requires at least: 1.5
Tested up to: 4.9.6
Stable tag: 0.1

Goes through your posts and handles the broken images that might appear on the posts on your blog.

== Description ==

Broken Post Content Images is a Free and Open Source Wordpress plugin which is able to search for broken images within your posts and replace them with a 1x1 transparent gif. It doesn't matter if you have 1 post or 100,000 posts, it will handle it. You can control exactly which post to check by optionally specifying a post id number (or just put all to check all posts).

Benefit? Your images never become dead because you have full control!

== Installation ==

* SSH into the server as the user that your WordPress install exists under.
* Clone latest stable version of code <https://github.com/sozonovalexey/wp-broken-post-content-images/archive/master.zip>.
* Unpack this archive to the `/wp-content/plugins/` directory.
* Log in to Wordpress Administration area, choose "Plugins" from the main menu, find "Broken Post Content Images", and click the "Activate" button.
* Check all posts with this command: $ wp bpci check all --skip=0 --limit=100
* Check custom post with this command: $ wp bpci check 1234
* Check custom posts with this command: $ wp bpci check 1234 1235 1236
