=== Twitter SP2 ===
Contributors: de-ce
Tags: integrate, notify, digest, Post, integration, tweet, twitter, api, links 
Requires at least: 2.6
Tested up to: 2.7.1
Stable tag: 0.1.1

A Wordpress plugin that posts on Twitter a link to your post shorten via sp2.ro when you publish a blog post. 

== Description ==

Twitter SP2 is a simple WordPress plugin developed for romanian bloggers that posts on Twitter a link to your post shorten via a romanian service sp2.ro when you publish a blog post.

When a post is published the plugin makes a shorter link for the permalink using sp2.ro API, stores it in a custom field called `sp2_link`, then sends it on Twitter in a way chosed in Options.

= Features =
* the shorter link is stored in a custom field
* predefined and custom formats of text to send
* twitter notificantion can be turned off for individual posts

= Changelog =
* v0.1.1 minor input error in Options
* v0.1 initial release


== Installation ==

Instructions for installing the Twitter SP2 plugin.

1. Download the plugin and unzip it to a folder on your computer.
1. Upload `twitter-sp2` folder to the `/wp-content/plugins/` directory
1. Activate `Twitter SP2` plugin through the `Plugins` menu in WordPress
1. Go to `wp-Admin => Settings => Twitter SP2` to enter your twitter account info and modify other settings.

== Frequently Asked Questions ==

= If I edit an old post it will be sent on Twitter? =

Yes, if the updates are not turn off for that post.

= How does the plugin knows if a notification was sent? =

After a tweet was sent, the plugin makes a custom field called sp2_tweet_sent with a value of '1'


== Screenshots ==

1. the Options in WP 2.7