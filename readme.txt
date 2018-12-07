= Proxy Cache Purge =
Contributors: Ipstenu, mikeschroder, techpriester, danielbachhuber
Tags: proxy, purge, cache, varnish, nginx
Requires at least: 4.7
Tested up to: 5.0
Stable tag: 4.7.3
Requires PHP: 5.6

Automatically empty proxy cached content when your site is modified.

== Description ==

<strong>This plugin <em>does not</em> install nor configure a cache proxy. It acts as an interface with such services.</strong>

One common method of caching content for websites is via the use of reverse proxy caching. Common examples of this are <a href="https://www.varnish-cache.org/">Varnish</a> and <a href="https://www.nginx.com/">Nginx</a>. These systems allow a website to update content and have the visitor's experience cached without the need for complex plugins storing the files locally and using up a user's disk space.

A reverse proxy cache is installed in front of a server and reviews requests. If the page being requested is already cached, it delivers the cached content. Otherwise it generates the page and the cache on demand.

The Proxy Cache Purge plugin sends a request to delete (aka flush) the cached data of a page or post every time it it modified. This happens when updating, publishing, commenting on, or deleting an post, and when changing themes.

= How It Works =

When content on a site is updated by WordPress, the plugin reaches out to the proxy cache service with the URL of the page, requesting the cache be deleted.

Not all page are deleted from the cache on every change. For example, when a post, page, or custom post type is edited, or a new comment is added, <em>only</em> the following pages will purge:

* The front page
* The post/page edited
* Any categories, tags, and/or custom taxonomies associated with the page
* Related feeds
* Associated JSON API pages

In addition, your <em>entire</em> cache will be deleted on the following actions:

* Changing themes
* Pressing the <strong>Empty Cache</strong> button on the toolbar

Plugins can hook into the purge actions as well, to filter their own events to trigger a purge.

On a multisite network using subfolders, only <strong>network admins</strong> can purge the main site.

= Development Mode =

If you're working on a site and need to turn off caching in one of two ways:

1. Add `define( 'VHP_DEVMODE', true );` to your `wp-config.php` file
2. Go to Proxy Cache -> Settings and enable debug mode for 24 hours at a time

That will break cache on page loads. It is _not_ recommended for production!

= WP CLI =

* `wp varnish purge` - Flush the entire cache
* `wp varnish debug [<url>]` - Help for debugging how well your cache is (or isn't) working
* `wp varnish devmode [<activate|deactivate|toggle>` - Change development mode state

= Privacy Policy =

By default, no data is tracked. If you use the site scanner/debugging tool, your domain and IP address will access [a remote service hosted on DreamObjects](https://varnish-http-purge.objects-us-east-1.dream.io/readme.txt). No personally identifying transaction data is recorded or stored, only overall usage. IP addresses of the website making the request may be recorded by the service, but there is no way to access them and use it to correspond with individuals or processes.

Use of this service is required for the cache checking in order to provide up to date compatibility checks on plugins and themes that may conflict with running a server based cache without needing to update the plugin every day.

<em>No visitor information from your site is tracked.</em>

== Installation ==

No special instructions apply.

If you have a 3rd party proxy service (such as Sucuri or Cloudflare) you will need to add an IP address on the <em>Proxy Cache -> Settings</em> page. Alternatively you can add a define to your `wp-config.php` file: `define('VHP_VARNISH_IP','123.45.67.89');`

When using Nginx based proxies, your IP will likely be `localhost`.

= Requirements =

* Pretty Permalinks enabled
* A server based proxy cache service (such as Varnish or Nginx)

== Frequently Asked Questions ==

**Please report all issues in the [support forums](https://wordpress.org/support/plugin/varnish-http-purge)**

If you have code patches, [pull requests are welcome](https://github.com/Ipstenu/varnish-http-purge).

= Is this plugin caching my data? =

No. This plugin tells your cache system when content is updated, and to delete the cached data at that time.

= Why doesn't the plugin automatically delete the whole cache? =

Speed and stability. Emptying too much of a cache on every change can slow a server down. This plugin does it's best to determine what needs to be deleted and when, while providing hooks for developers to use as necessary.

= Can I delete the entire cache? =

Yes. Click the 'Empty Cache' button on the "Right Now" Dashboard (see the screenshot if you can't find it). There's also an "Empty Cache" button on the admin toolbar.

If you don't see a button, then your account doesn't have the appropriate permissions. Only administrators can empty the entire cache. In the case of a subfolder multisite network, only the <em>network</em> admins can empty the cache for the primary site.

= Will the plugin delete my cache when I edit files on the server? =

No. WordPress can't detect those file changes so it can't tell your cache what to do. You will need to use the Empty Cache buttons when you're done editing your code.

= Does every WordPress plugin and theme work with a proxy cache? =

No. Some of them have behavior that causes them not to cache, either by accident or design.

= I'm a developer, can I tell your cache to empty in my plugin/theme? =

Yes. [Full documentation can be found on Custom Filters in the wiki](https://github.com/Ipstenu/varnish-http-purge/wiki/Custom-Filters).

= Can I turn off caching? =

Kind of. You can use development mode to have WordPress tell your proxy service not to serve cached content, but the content will still be cached by the service.

There are three ways to do this:

1. Chose 'Pause Cache (24hrs)' from the Cache dropdown menu in your toolbar
2. Go to Proxy Cache -> Settings and enable development mode
3. Add `define( 'VHP_DEVMODE', true );` to your `wp-config.php` file

The first two options will enable development mode for 24 hours. If you're working on long term development, you can should use the define.

It is _not_ recommended you use development mode on production sites for extended periods of time, as it _will_ will slow your site down and lose all the benefits of caching in the first place.

= Why don't I have access to development mode? =

Due to the damage this can cause a site, access is limited to admins only. In the case of a multisite network, only <em>Network Admins</em> can disable caching and they must do so via `wp-config.php` for security.

= Why do I still see cached content in development mode? =

While development mode is on, your server will continue to cache content but the plugin will tell WordPress not to use the cached content. That means files that exist outside of WordPress (like CSS or images) _may_ serve cached content. The plugin does its best to add a No Cache parameter to javascript and CSS, however if a theme or plugin _doesn't_ use proper WordPress enqueues, then their cached content will be shown.

= How can I tell if everything's caching? =

From your WordPress Dashboard, go to <em>Proxy Cache > Check Caching</em>. There a page will auto-scan your front page and report back any issues found. This includes any known problematic plugins. You can use it to scan any URL on your domain.

= Why is nothing caching when I use PageSpeed? =

PageSpeed likes to put in Caching headers to say <em>not</em> to cache. To fix this, you need to put this in your `.htaccess` section for PageSpeed: `ModPagespeedModifyCachingHeaders off`

If you're using nginx, it's `pagespeed ModifyCachingHeaders off;`

= Why aren't my changes showing when I use CloudFlare or another proxy? =

When you use CloudFlare or any other similar service, you've put a proxy in front of the server's proxy. In general this isn't a bad thing, though it can introduce some network latency (that means your site may run slower because it has to go through multiple layers to get to the content). The problem arises when WordPress tries to send the purge request to your domain name and, with a proxy, that means the proxy service and not your website.

On single-site, you can edit this via the <em>Proxy Cache > Check Caching</em> page. On Multisite, you'll need to add the following to your wp-config.php file: `define('VHP_VARNISH_IP','123.45.67.89');`

Replace `123.45.67.89` with the IP of your <em>Proxy Cache Server</em> (_not_ CloudFlare). **DO NOT** put http in this define. If you're on nginx, you'll want to use `localhost` instead of an IP address.

If you want to use WP-CLI, you can set an option in the database. This will not take precedence over the define, and exists for people who want to use automation tools: `wp option update vhp_varnish_ip 123.45.67.890`

= Why do I get a 503 or 504 error on every post update? =

Your IP address is incorrect. Check the IP of your server and then the setting for your proxy cache IP. If they're _not_ the same, that's likely why.

= How do I find the right IP address? =

Your proxy IP must be one of the IPs that the service is listening on. If you use multiple IPs, or if you've customized your ACLs, you'll need to pick on that doesn't conflict with your other settings.

For example, if you have a Varnish based cache and it's listening on a public and private IP, you'll want to pick the private. On the other hand, if you told Varnish to listen on 0.0.0.0 (i.e. "listen on every interface you can") you would need to check what IP you set your purge ACL to allow (commonly 127.0.0.1 aka localhost), and use that (i.e. 127.0.0.1).

If your web host set up your service, check their documentation.

= What if I have multiple proxy cache IPs? =

Multiple IPs are not supported at this time.

= What version of Varnish is supported? =

This was built and tested on Varnish 3.x. While it is reported to work on 2.x and 4.x, it is only supported on v3 at this time.

= Does this work with Nginx caching? =

It can, if you've configured Nginx caching to respect the curl PURGE request. If this doesn't work, I recommend setting your Varnish IP to `localhost` as Nginx requires a service control installed for the IP address to work.

= What should my cache rules be? =

This is a question beyond the support of plugin. I do not have the resources available to offer any configuration help. Here are some basic gotchas to be aware of:

* To empty any cached data, the service will need to respect the PURGE command
* Not all cache services set up PURGE by default
* When flushing the whole cache, the plugin sends a PURGE command of <code>/.*</code> and sets the `X-Purge-Method` header to `regex`
* Nginx expects the IP address to be 'localhost'

= How can I see what the plugin is sending to the cache service? =

Yes _IF_ the service has an interface. Sadly Nginx does not. [Detailed directions can be found on the debugging section on GitHub](https://github.com/Ipstenu/varnish-http-purge/wiki#debugging). Bear in mind, these interfaces tend to be command-line only.

= Don't you work at DreamHost? Is this Official or DreamHost only? =

* Yes, I do work for DreamHost
* No, this plugin is not DreamHost Only

This plugin is installed by default for _all_ DreamPress installs on DreamHost, and I maintain it for DreamHost, but it was not originally an official DreamHost plugin which means I will continue to support all users to the best of my ability.

== Changelog ==

= 4.7.3 =
* December 2018
* Bugfix for Jetpack (Props @jherve)

= 4.7.2 =
* October 2018
* Fix regression with IP function name
* Restore "Right Now" activity box _only_ for people who use WP.com toolbar

= 4.7.1 =
* October 2018
* Documentation: Cleaning up language and spelling

= 4.7.0 =
* October 2018
* WP-CLI: documentation
* Bugfix: Nginx compatibility
* Bugfix: Only enqueue CSS on front0end if the admin bar is used (props @mathieuhays)
* Feature: Rebranding
* Deprecation: "Right Now" button (not needed anymore)

== Screenshots ==

1. Purge button on Right Now (Dashboard Admin)
2. Toolbar menu (with cache enabled)
3. Toolbar menu (with cache disabled)
4. Scanner results
5. Change Proxy IP address
6. Activate Dev Mode
7. Dev Mode Warning (24 hour notice)
