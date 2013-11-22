=== Varnish HTTP Purge ===
Contributors: techpriester, Ipstenu, DH-Shredder
Tags: varnish, purge, cache
Requires at least: 3.4
Tested up to: 3.7
Stable tag: 3.3.1

Purge Varnish Cache when pages are modified.

== Description ==
Varnish HTTP Purge sends a PURGE request to the URL of a page or post every time it it modified. This occurs when editing, publishing, commenting or deleting an item, and when changing themes.

<a href="https://www.varnish-cache.org/">Varnish</a> is a web application accelerator also known as a caching HTTP reverse proxy. You install it in front of any server that speaks HTTP and configure it to cache the contents. This plugin <em>does not</em> install Varnish for you. It's expected you already did that.

Not all pages are purged every time, depending on your Varnish configuration. When a post, page, or custom post type is edited, or a new comment is added, <em>only</em> the following pages will purge:

* The front page
* The post/page edited
* Any categories or tags associated with the page

In addition, your entire cache will be purged on the following actions:

* <del>Changing permalinks</del>
* Changing themes
* Press the 'flush cache' button

= The future ... =

We're going to sit down and look into how the plugin is structured to make it even faster and more organized. Please send coffee. Here's the wish list:

* Only purge all automatically once an hour (manual button click will continue to work)
* Refactor automated purge all to be kinder
* Reorganize code for sanity
* Get rid of the need to parse_url()

== Installation ==
No WordPress configuration needed.

= Requirements =
* Pretty Permalinks enabled
* Varnish 3.x or higher

= Varnish Config Best Practices =

<em>Coming Soon</em>

== Frequently Asked Questions ==

= What version of Varnish is supported? =

This was built and tested on Varnish 3.x, however it is reported to work on 2.x. It is only supported on v3 at this time.

= Why doesn't every page flush when I make a new post? =

The only pages that should purge are the post's page, the front page, categories, and tags. The reason why is a little philosophical. 

When building out this plugin, there were a couple pathways on how best to handle purging caches and they boiled down to two: Decisions (the plugin purges what it purges when it purges) and Options (you decide what to purge, when and why). It's entirely possible to make this plugin purge everything, every time a 'trigger' happens, have it purge some things, or have it be so you can pick that purges.

In the interests of design, we decided that the KISS principle was key. Since you can configure your Varnish to always purge all pages recursively (i.e. purging http://example.com/ would purge all pages below it), if that's a requirement, you can set it yourself. There are also other Varnish plugins that allow for more granular control (including W3 Total Cache), however this plugin will not be gaining a whole bunch of options to handle that.

= Why doesn't my cache purge when I edit my theme? =

Because the plugin only purges your <em>content</em> when you edit it. That means if you edit a page/post, or someone leaves a comment, it'll change. Otherwise, you have to purge the whole cache. The plugin will do this for you if you change your theme, but not when you edit your theme. 

That said, if you use Jetpack's CSS editor, it will purge the whole cache on save.

= How do I manually purge the whole cache? =

Click the 'Purge Varnish Cache' button on the "Right Now" Dashboard (see the screenshot if you can't find it).

= Why is nothing caching when I use PageSpeed? =

Because PageSpeed likes to put in Caching headers to say <em>not</em> to cache. To fix this, you need to put this in your .htaccess section for PageSpeed: `ModPagespeedModifyCachingHeaders off`

If you're using nginx, it's `pagespeed ModifyCachingHeaders off;`

= Can I use this with a proxy service like CloudFlare? =

Yes, but you'll need to make some additonal changes (see "Why aren't my changes showing when I use CloudFlare or another proxy?" below).

If you use the Jetpack CSS editor, however, your changes will show up.

= Why aren't my changes showing when I use CloudFlare or another proxy? =

When you use CloudFlare or any other similar servive, you've got a proxy in front of the Varnish proxy. In general this isn't a bad thing. The problem arises when the DNS shenanigans send the purge request to your domainname. When you've got an additional proxy like CloudFlare, you don't want the request to go to the proxy, you want it to go to Varnish server.

To fix this, add the following to your wp-config.php file:

`define('VHP_VARNISH_IP','123.45.67.89');`

Replace "123.45.67.89" with the IP of your <em>Varnish Server</em> (not CloudFlare, Varnish). <em>DO NOT</em> put in http in this define.

You can also set the option `vhp_varnish_ip` in the database. This will NOT take precedence over the define, it's just there to let hosts who are using something like wp-cli do this for you in an automated fashion:

`wp option add vhp_varnish_ip 123.45.67.89`

and

`wp option update vhp_varnish_ip 123.45.67.890`

= How do I find my Varnish IP? =

Your Varnish IP must be one of the IPs that Varnish is listening on. If you use multiple IPs, or if you've customized your ACLs, you'll need to pick on that doesn't conflict with your other settings. For example, if you have Varnish listening on a public and private IP, you'll want to pick the private. On the other hand, if you told Varnish to listen on 0.0.0.0 (i.e. "listen on every interface you can") you would need to check what IP you set your purge ACL to allow (commonly 127.0.0.1 aka localhost), and use that (i.e. 127.0.0.1).

If your webhost set up Varnish for you, you may need to ask them for the specifics if they don't have it documented. I've listed the ones I know about here, however you should still check with them if you're not sure.

<ul>
    <li><strong>DreamHost</strong> - If you're using DreamPress, go into the Panel and click on the DNS settings for the domain. The entry for <em>resolve-to.domain</em> is your varnish server: `resolve-to.www A 208.97.157.172`</li>
</ul>

= Why don't my gzip'd pages flush? =

Make sure your Varnish VCL is configured correctly to purge all the right pages. This is normally an issue with Varnish 2, which is not supported.

= Why isn't the whole cache purge working? =

The plugin sends a PURGE command of <code>/.*</code> and `X-Purge-Method` in the header with a value of regex. If your Varnish server doesn't doesn't understand the wildcard, you can configure it to check for the header.

= How do I configure my VCL? =

This is a beyond this plugin question in a way, since I don't offer any Varnish Config help. I will say this, you absolutely must have PURGE set up in your VCL. This is still supported in Varnish v3, though may not be set up by default. Also, here are some links to other people who use this plugin and have made public their VCLs:

* <a href="https://github.com/dreamhost/varnish-vcl-collection">DreamHost's Varnish VCL Collection</a>

All of these VCLs work with this plugin.

== Changelog ==

= 3.3.1 =
* Language Pack fixing.

= 3.3 =
* Quick and dirty fix for a plugin that is causing the URLs to purge <em>ALL THE TIME</em>

= 3.2 =
* Correcting conflict with host's default config.

= 3.1 =
* Refactoring Cleanup (otherwise known as Copy/Pasta error in variable name). (props Shredder)

= 3.0 =
* Adds 'Purge Varnish' button
* More selective purging, to account for different server setups
* Tighened up what purges and when
* Flushing categories and tags (per code from WP Super Cache, thanks!)
* Clarify requirements (Varnish and Pretty Permalinks)

= 2.3 =
* Purge images on deletion
* Fix for a VarnishIP when behind proxy servers not working on all hosts (props Berler)

= 2.2.1 = 
* typo (hit . instead of / - Props John B. Manos)

= 2.2 =
* Added in workaround for Varnish purge reqs going AWOL when another proxy server is in place. (props to Shredder and Berler)
* Cache flushes when you change themes

= 2.1 =
* Header Image

= 2.0 =
* Commit access handed to Ipstenu
* Changed CURL to wp_remote_request (thank you <a href="http://wordpress.org/support/topic/incompatability-with-editorial-calendar-plugin?replies=1">Kenn Wilson</a>) so we don't have to do <a href="http://wordpress.org/support/topic/plugin-varnish-http-purge-incompatibility-with-woocommerce?replies=6">CURLOPT_RETURNTRANSFER</a> Remember kids, CURL is okay, but wp_remote_request is more better.

= 1.2.0 =
* Moved actual request execution to "shutdown" event
* Removed GET request due to bad performance impact

== Screenshots ==

1. What the button looks like

== Upgrade Notice ==

3.3.1 is just a language pack fix. Enjoy!