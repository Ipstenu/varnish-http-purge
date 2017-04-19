=== Varnish HTTP Purge ===
Contributors: Ipstenu, mikeschroder, techpriester
Tags: varnish, purge, cache
Requires at least: 4.7
Tested up to: 4.8
Stable tag: 4.1.1

Automatically empty a Varnish Cache when content on your site is modified.

== Description ==

Varnish HTTP Purge sends a PURGE request to delete the cached data of a page or post every time it it modified. This happens when updating, publishing, commenting on, or deleting an post, and when changing themes.

<a href="https://www.varnish-cache.org/">Varnish</a> is a web application accelerator also known as a caching HTTP reverse proxy. You install it in front of any server that speaks HTTP and configure it to cache the contents. This plugin <em>does not</em> install Varnish for you, nor will it configure Varnish for WordPress. It's expected you already did that on your own.

Not all page caches are deleted every time, depending on your Varnish configuration. For example, when a post, page, or custom post type is edited, or a new comment is added, <em>only</em> the following pages will purge:

* The front page
* The post/page edited
* Any categories or tags associated with the page

In addition, your <em>entire</em> cache will be deleted on the following actions:

* Changing themes
* Press the 'Purge Varnish Cache' button on the dashboard
* Press the 'Purge Varnish' button on the toolbar

Plugins can hook into the purge actions as well, to filter their own events to trigger a purge.

And if you're into WP-CLI, you can use that too: `wp varnish purge`

Please note: On a multisite network using subfolders, only <strong>network admins</strong> can purge the main site. This is a security decision.

= Requirements =

* Pretty Permalinks enabled
* Varnish 3.x or higher

== Frequently Asked Questions ==

**Please report all issues in the [support forums](https://wordpress.org/support/plugin/varnish-http-purge)**

If you have code patches, [pull requests are welcome](https://github.com/Ipstenu/varnish-http-purge).

= How can I tell everything's working? =

From your WordPress Dashboard, go to Tools -> Varnish Status. There a page will auto-scan your main plugin page and report back any issues found. This includes any known problematic plugins.

= Does every WordPress plugin and theme work with Varnish? =

No. Some of them have behavior that causes Varnish not to cache. While I can't debug that for you, there is an "Is Varnish Working?" tool (see WP Admin -> Tools -> Varnish Status) that tries to detect most of the common issues and direct you to resolutions.

= How can I debug my site? =

Use the Varnish Status page. It'll try and help you figure out what's wrong.

= Will you fix my site if there's a conflict with a theme or plugin? =

I wish I could. I don't have that much free time. What I can do is try and point you to how you can fix it yourself. Bear in mind, that may mean you have to decide if using a specific plugin or theme is worth an imperfect cache.

= What version of Varnish is supported? =

This was built and tested on Varnish 3.x. While it is reported to work on 2.x and 4.x, it is only supported on v3 at this time.

= Why doesn't every page cache get deleted when I make a new post? =

The only cache pages that should be deleted are the post's page, the front page, categories, and tags. The reason why is a little philosophical.

When building out this plugin, there were a couple pathways on how best to handle purging caches and they boiled down to two: Decisions (the plugin deletes what it deletes when it deems it appropriate) and Options (you decide what to delete, when and why). It's entirely possible to make this plugin delete everything in a cache, every time a 'trigger' happens, have it only delete prt of the cache, or have it be so you can pick what gets deleted.

It was decided that the KISS principle was key. Since you can configure Varnish itself to always delete all pages recursively (i.e. purging `http://example.com/` would purge all pages below it), I felt it prudent to allow you to make that choice yourself in Varnish, not the plugin. There are also other plugins that allow for more granular control of Varnish and deleting caches (including W3 Total Cache).

In the interest of simplicity, this plugin will not be gaining a whole bunch of options to handle that.

= Why doesn't my cache get deleted when I edit my theme? =

Because the plugin only deletes caches of your <em>content</em> when you edit it. That means if you edit a page/post, or someone leaves a comment, it'll delete the impacted cached content. Otherwise, you have to delete the whole cache. The plugin will do this for you if you activate a new theme, but not when you edit your current theme's files, and not when you use customizer to change your widgets etc.

However... If you use the CSS editor in customizer, it will empty the whole cache for your site on publish.

= How do I manually delete the whole cache? =

Click the 'Empty Varnish Cache' button on the "Right Now" Dashboard (see the screenshot if you can't find it).

There's also an "Empty Cache" button on the admin toolbar.

= I don't see a button! =

If you're on a Multisite Network and you're on the primary site in the network, only the <em>network</em> admins can empty the cache for that site

On a subfolder network if you empty the cache for `example.com`, then everything under that (like `example.com/site1` and `example.com/site99` and everything in between) would also get flushed. That means that deleting the cache on the main site deletes the cache for the entire network. Which would really suck.

In order to mitigate the destructive nature of that power, only the network admins can empty the cache of the main site of a subfolder network.

= Why is nothing caching when I use PageSpeed? =

PageSpeed likes to put in Caching headers to say <em>not</em> to cache. To fix this, you need to put this in your `.htaccess` section for PageSpeed: `ModPagespeedModifyCachingHeaders off`

If you're using nginx, it's `pagespeed ModifyCachingHeaders off;`

= Can I use this with a proxy service like CloudFlare? =

Yes, but you'll need to make some additional changes (see "Why aren't my changes showing when I use CloudFlare or another proxy?" below).

= Why aren't my changes showing when I use CloudFlare or another proxy? =

When you use CloudFlare or any other similar service, you've got a proxy in front of the Varnish proxy. In general this isn't a bad thing, though it can introduce some network latency (that means your site may run slower because it has to go through multiple layers to get to the content). The problem arises when the DNS shenanigans send the purge request to your domain name. When you've got an additional proxy like CloudFlare, you don't want the request to go to the proxy, you want it to go to Varnish server.

On single-site, you can edit this via the Tools -> Varnish Status page. On Multisite, you'll need to add the following to your wp-config.php file:

`define('VHP_VARNISH_IP','123.45.67.89');`

Replace "123.45.67.89" with the IP of your <em>Varnish Server</em> (not CloudFlare, Varnish). <em>DO NOT</em> put in http in this define.

If you want to use WP-CLI, you can set an option in the database. This will NOT take precedence over the define, it's just there to let hosts who are using something like wp-cli do this for you in an automated fashion:

`wp option add vhp_varnish_ip 123.45.67.89`

and

`wp option update vhp_varnish_ip 123.45.67.890`

= How do I find my Varnish IP? =

Your Varnish IP must be one of the IPs that Varnish is listening on. If you use multiple IPs, or if you've customized your ACLs, you'll need to pick on that doesn't conflict with your other settings. For example, if you have Varnish listening on a public and private IP, you'll want to pick the private. On the other hand, if you told Varnish to listen on 0.0.0.0 (i.e. "listen on every interface you can") you would need to check what IP you set your purge ACL to allow (commonly 127.0.0.1 aka localhost), and use that (i.e. 127.0.0.1).

If your webhost set up Varnish for you, you may need to ask them for the specifics if they don't have it documented. I've listed the ones I know about here, however you should still check with them if you're not sure.

<ul>
    <li><strong>DreamHost</strong> - If you're using DreamPress and Cloudflare, go into the Panel and click on the DNS settings for the domain. The entry for <em>resolve-to.domain</em> is your varnish server: `resolve-to.www A 208.97.157.172` -- If you're <em>NOT</em> using Cloudflare, you don't need it; it's just your normal IP.</li>
</ul>

= What if I have multiple varnish IPs? =

Multiple IPs are not supported at this time.

I have a major issue with writing code I don't use, which means that since I'm only using one IP right now, I don't want to be on the ball for supporting multiple IPs. I don't even have a place to test it, which is just insane to attempt to code if you think about it. Yes, I could accept pull requests, but that means everyone's at some other person's discretion. So no, I won't be doing that at this time.

= Why don't my gzip'd pages get deleted? =

Make sure your Varnish VCL is configured correctly to purge all the right pages. This is normally an issue with Varnish 2, which is not supported by this plugin.

= Why isn't the whole cache deletion working? =

The plugin sends a PURGE command of <code>/.*</code> and `X-Purge-Method` in the header with a value of regex. If your Varnish server doesn't doesn't understand the wildcard, you can configure it to check for the header.

= How can I see what the plugin is sending to Varnish? =

Danger! Here be dragons! If you're command line savvy, you can monitor the interactions between the plugin and Varnish. This can help you understand what's not working quite right, but it can very confusing.

To see every request made to varnish, use this:
`varnishncsa -F "%m %U"`

If you want to grab the last purge requests, it's this:
`varnishlog -d -c -m RxRequest:PURGE`

And this will show you if the WP button was used:
`varnishlog -d -c -m RxURL:.*vhp_flush_all.*`

In general, I leave the first command up and test the plugin.

A full Varnish flush looks like this:
`PURGE /.*`

And a new-post (or edited post) would look like this:

<pre>
PURGE /category/uncategorized/
PURGE /author/ipstenu/
PURGE /author/ipstenu/feed/
PURGE /2015/08/test-post/
PURGE /feed/rdf/
PURGE /feed/rss/
PURGE /feed/
PURGE /feed/atom/
PURGE /comments/feed/
PURGE /2015/08/test-post/feed/
PURGE /
</pre>

It's just a matter of poking at things from then on.

= How do I configure my VCL? =

This is a question beyond the support of plugin. I don't offer any Varnish Config help due to resources. I will say this, you absolutely must have PURGE set up in your VCL. This is still supported in Varnish v3, though may not be set up by default. Also, here are some links to other people who use this plugin and have made public their VCLs:

* <a href="https://github.com/dreamhost/varnish-vcl-collection">DreamHost's Varnish VCL Collection</a>

All of these VCLs work with this plugin.

= Can I filter things to add special URLs? =

Yes! 


* `vhp_home_url` - Change the home URL (default is `home_url()`)
* `vhp_purge_urls` - Add additional URLs to what will be purged
* `varnish_http_purge_headers` - Allows you to change the HTTP headers to send with a PURGE request. 
* `varnish_http_purge_schema` - Allows you to change the schema (default is http)
* `varnish_http_purge_events` - Add a specific event to trigger a page purge
* `varnish_http_purge_events_full` - Add a specific event to trigger a full site purge

I strongly urge you to use the last one with caution. If you trigger a full site purge too often, you'll obviate the usefulness of caching!

= I added in an event to purge on and it's not working =

If you're using `varnish_http_purge_events` then you have to make sure your event spits out a post ID.

If you don't have a post ID and you still need this, add it to *both* `varnish_http_purge_events_full` and `varnish_http_purge_events` - but please use this with caution, otherwise you'll be purging everything all the time, and you're a terrible person.

= Hey, don't you work at DreamHost? Is this Official or DreamHost only? =

* Yes, I do work for DreamHost.
* No, this plugin is not really official nor DreamHost Only

This plugin is installed by default for _all_ DreamPress installs on DreamHost, and I maintain it for DreamHost, but it was not originally an official DH plugin which means I will continue to support all users to the best of my ability.

== Changelog ==

= 4.1 =

* JSON / REST API Support
* Fix for Varnish Status Page on MAMP (props @jeremyclarke)
* Filter for purge headers (props @ocean90)
* Disallow people from editing the Varnish IP on Multisite
* Drop support for pre 4.7 because of JSON / REST API
* Support flushing cache for private pages

== Screenshots ==

1. Purge button on Right Now (Dashboard Admin)
2. Purge button on Toolbar
3. Scanner results
4. Change Varnish IP address