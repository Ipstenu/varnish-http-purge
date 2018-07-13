= Varnish HTTP Purge =
Contributors: Ipstenu, mikeschroder, techpriester, danielbachhuber
Tags: varnish, purge, cache
Requires at least: 4.7
Tested up to: 4.9
Stable tag: 4.6.2
Requires PHP: 5.6

Automatically empty Varnish Cache when content on your site is modified.

== Description ==

<a href="https://www.varnish-cache.org/">Varnish</a> is a web application accelerator also known as a caching HTTP reverse proxy. You install it in front of any server that speaks HTTP and configure it to cache the contents. This plugin <em>does not</em> install Varnish for you, nor will it configure Varnish for WordPress.

The Varnish HTTP Purge plugin sends a request to delete (aka flush) the cached data of a page or post every time it it modified. This happens when updating, publishing, commenting on, or deleting an post, and when changing themes.

In addition, it provides debugging tools to help you determine how effective your site setup is with Varnish. In order to provide the most up to date compatibility information, this tool contacts a service hosted on DreamObjects. [Public information about this service is available on DreamObjects](https://varnish-http-purge.objects-us-east-1.dream.io/readme.txt). The service is <em>ONLY</em> accessed when using the Varnish Debugging tool to check if Caching is working properly.

= How it Works =

When content on a site is updated by WordPress, the plugin reaches out to the Varnish service with the URL of the page, requesting the cache be deleted.

Not all page are deleted from the cache on every change. For example, when a post, page, or custom post type is edited, or a new comment is added, <em>only</em> the following pages will purge:

* The front page
* The post/page edited
* Any categories, tags, and/or custom taxonomies associated with the page
* Related feeds
* Associated JSON API pages

In addition, your <em>entire</em> cache will be deleted on the following actions:

* Changing themes
* Pressing the <strong>Empty Cache</strong> button on the dashboard or toolbar

Plugins can hook into the purge actions as well, to filter their own events to trigger a purge.

On a multisite network using subfolders, only <strong>network admins</strong> can purge the main site. This is a security decision, as emptying the cache too often can be computationally expensive and cause server outages for a network.

= Development Mode =

If you're working on a site and need to turn off caching in one of two ways:

1. Add `define( 'VHP_DEVMODE', true );` to your `wp-config.php` file
2. Go to Varnish -> Settings and enable debug mode for 24 hours at a time

That will break cache on page loads. It is _not_ recommended for production!

= WP CLI =

* `wp varnish purge` - Flush the entire cache
* `wp varnish debug [<url>]` - Help for debugging how well Varnish is (or isn't) working
* `wp varnish devmode [<activate|deactivate|toggle>` - Change development mode state

= Privacy Policy =

By default, no data is tracked. If you use the site scanner/debugging tool, your domain and IP address will access [a remote service hosted on DreamObjects](https://varnish-http-purge.objects-us-east-1.dream.io/readme.txt). No personally identifying transaction data is recorded or stored, only overall usage. IP addresses of the website making the request may be recorded by the service, but there is no way to access them and use it to correspond with individuals or processes.

Use of this service is required for the cache checking in order to provide up to date compatibility checks on plugins and themes that may conflict with running a server based cache (such as Varnish or Nginx) without needing to update the plugin every day.

== Installation ==

No special instructions apply. If you have a 3rd party proxy service (such as Sucuri or Cloudflare) you will need to add a Varnish IP address on the <em>Varnish -> Settings</em> page.

= Requirements =

* Pretty Permalinks enabled
* Varnish 3.x or higher

== Frequently Asked Questions ==

**Please report all issues in the [support forums](https://wordpress.org/support/plugin/varnish-http-purge)**

If you have code patches, [pull requests are welcome](https://github.com/Ipstenu/varnish-http-purge).

= Is this plugin caching my data? =

No. This plugin tells your cache system when content is updated, and to delete the cached data at that time.

= Why doesn't the plugin automatically delete the whole cache? =

By design, this plugin embraces decisions, not options, as well as simplicity. Emptying too much of a cache on every change can slow a server down. In addition, users generally want things to 'just work.' With that in mind, this plugin determines what's best to delete on updates, and provides hooks for developers to use as needed.

= Can I delete the entire cache? =

Yes! Click the 'Empty Cache' button on the "Right Now" Dashboard (see the screenshot if you can't find it). There's also an "Empty Cache" button on the admin toolbar.

If you don't see a button, then your account doesn't have the appropriate permissions. Only administrators can empty the entire cache. In the case of a subfolder multisite network, only the <em>network</em> admins can empty the cache for the primary site.

= Will the plugin delete my cache when I edit files on the server? =

No. WordPress can't detect those file changes so it can't tell Varnish what to do. You will need to use the Empty Cache buttons when you're done editing your code.

= Does every WordPress plugin and theme work with Varnish? =

No. Some of them have behaviour that causes Varnish not to cache, either by accident or design.

= I'm a developer, can I tell your cache to empty in my plugin/theme? =

Yes! [Full documentation can be found on Custom Filters in the wiki](https://github.com/Ipstenu/varnish-http-purge/wiki/Custom-Filters).

= Can I turn off caching? =

The plugin itself does not perform caching, but you can use development mode to have WordPress tell Varnish not to serve cached content. In order to do this, you must enter development mode. There are three ways to do this:

1. Chose 'Pause Cache (24hrs)' from the Cache dropdown menu in your toolbar

2. Go to Varnish -> Settings and enable development mode

3. Add `define( 'VHP_DEVMODE', true );` to your `wp-config.php` file

The first two options will enable development mode for 24 hours. If you're working on long term development, you can should use the define.

It is _not_ recommended you use development mode on production sites for extended periods of time, as it _will_ will slow your site down and lose all the benefits of caching in the first place.

= Why don't I have access to development mode? =

Due to the damage this can cause a site, access is limited to admins only. In the case of a multisite network, only <em>Network Admins</em> can disable caching.

= Why do I still see cached content in development mode? =

Remember, the plugin isn't doing the caching itself. While development mode is on, your server will actually continue to cache content but WordPress will tell it not to use the cached content. That means files that exist outside of WordPress (like CSS or images) will still be cached and _may_ serve cached content. The plugin does its best to add a No Cache parameter to javascript and CSS, however if a theme or plugin _doesn't_ use proper WordPress enqueues, then their content will be shown cached.

= How can I tell if everything's caching? =

From your WordPress Dashboard, go to <em>Varnish > Check Caching</em>. There a page will auto-scan your front page and report back any issues found. This includes any known problematic plugins. You can use it to scan any URL on your domain.

= Why is nothing caching when I use PageSpeed? =

PageSpeed likes to put in Caching headers to say <em>not</em> to cache. To fix this, you need to put this in your `.htaccess` section for PageSpeed: `ModPagespeedModifyCachingHeaders off`

If you're using nginx, it's `pagespeed ModifyCachingHeaders off;`

= Why aren't my changes showing when I use CloudFlare or another proxy? =

When you use CloudFlare or any other similar service, you've put a proxy in front of the Varnish proxy. In general this isn't a bad thing, though it can introduce some network latency (that means your site may run slower because it has to go through multiple layers to get to the content). The problem arises when WordPress tries to send the purge request to your domain name and, with a proxy, that means the proxy service and not your website.

On single-site, you can edit this via the <em>Varnish > Check Caching</em> page. On Multisite, you'll need to add the following to your wp-config.php file: `define('VHP_VARNISH_IP','123.45.67.89');`

Replace "123.45.67.89" with the IP of your <em>Varnish Server</em> (not CloudFlare, Varnish). **DO NOT** put http in this define.

If you want to use WP-CLI, you can set an option in the database. This will not take precedence over the define, and exists for people who want to use automation tools: `wp option update vhp_varnish_ip 123.45.67.890`

= Why do I get a 503 or 504 error on every post update? =

Your Varnish IP address is probably wrong. Check the IP of your server and then the setting for your Varnish IP. If they're _not_ the same, that's likely why.

= How do I find my Varnish IP? =

Your Varnish IP must be one of the IPs that Varnish is listening on. If you use multiple IPs, or if you've customized your ACLs, you'll need to pick on that doesn't conflict with your other settings. For example, if you have Varnish listening on a public and private IP, you'll want to pick the private. On the other hand, if you told Varnish to listen on 0.0.0.0 (i.e. "listen on every interface you can") you would need to check what IP you set your purge ACL to allow (commonly 127.0.0.1 aka localhost), and use that (i.e. 127.0.0.1).

If your webhost set up Varnish, you may need to ask them for the specifics if they don't have it documented. I've listed the ones I know about here, however you should still check with them if you're not sure.

<ul>
	<li><strong>DreamHost</strong> - If you're using DreamPress and Cloudflare, go into the Panel and click on the DNS settings for the domain. The entry for <em>resolve-to.domain</em> is your varnish server: `resolve-to.www A 208.97.157.172` -- If you're <em>NOT</em> using Cloudflare, you don't need it; it's just your normal IP.</li>
</ul>

= What if I have multiple varnish IPs? =

Multiple IPs are not supported at this time.

= What version of Varnish is supported? =

This was built and tested on Varnish 3.x. While it is reported to work on 2.x and 4.x, it is only supported on v3 at this time.

= Does this work with Nginx caching? =

It can, if you've configured nginx caching to respect the curl PURGE request. If this doesn't work, I recommend setting your Varnish IP to `localhost` as Nginx requires a service control installed for the IP address to work.

= What should my cache rules be? =

This is a question beyond the support of plugin. I do not have the resources available to offer any configuration help. Here are some basic gotchas to be aware of:

* To empty any cached data, the service will need to respect the PURGE command
* Not all cache services set up PURGE by default
* When flushing the whole cache, the plugin sends a PURGE command of <code>/.*</code> and sets the `X-Purge-Method` header to `regex`.

= How can I see what the plugin is sending to Varnish? =

Danger! Here be dragons! If you're command line savvy, you can monitor the interactions between the plugin and Varnish. This can help you understand what's not working quite right, but it can very confusing. [Detailed directions can be found on the debugging section on GitHub](https://github.com/Ipstenu/varnish-http-purge/wiki#debugging).

= Hey, don't you work at DreamHost? Is this Official or DreamHost only? =

* Yes, I do work for DreamHost.
* No, this plugin is not really official nor DreamHost Only

This plugin is installed by default for _all_ DreamPress installs on DreamHost, and I maintain it for DreamHost, but it was not originally an official DreamHost plugin which means I will continue to support all users to the best of my ability.

== Changelog ==

= 4.6.2 =

* July 2018
* Fixing some translation output.
* Multisite fixes for settings pages.

= 4.6.1 =

* July 2018
* Fix situation where purging wasn't (props @carlalexander)

= 4.6.0 =

* July 2018
* Moved Varnish to it's own menu with a new custom icon (props Olesya)
* Add option to enable development for 24 hours (for super-admins only)
* Change debug mode to development mode and greatly improved overall
* Translation improvements
* Add new action hook for after a full purge (props @futtta)
* Change check for age-header to not require a second run (props @danielbachhuber)
* Confirm plugin and theme blacklist check (props @danielbachhuber)
* WP-CLI: add debug option to show all header output (props @danielbachhuber)
* WP-CLI: add debug option to grep content for known issues (props @danielbachhuber)
* WP-CLI: add new command to change devmode state

= 4.5.2 =

* June 2018
* Bug Fix: Prevent error for non-admins

= 4.5.1 =

* June 2018
* Due to contention (devs hate it, users like it) the empty cache button colour on the toolbar is removed, and replaced with a carrot icon (I did not make it orange, but I wanted to)
* Add carrot icon to collapsed (mobile) toolbar
* Better button hiding
* Fixed a stupid argument issue with flushing memcached and I should have known better but oh well
* FAQ update re nginx

= 4.5.0 =

* May 2018
* Remote storage of problem plugins/themes
* Prevent auto-loading of scan for improved disclosure and compliance
* Changed colour of the purge button for improved visibility
* Support for nginx proxy headers

== Screenshots ==

1. Purge button on Right Now (Dashboard Admin)
2. Toolbar menu (with cache enabled)
3. Toolbar menu (with cache disabled)
4. Scanner results
5. Change Varnish IP address
6. Activate Dev Mode
7. Dev Mode Warning (24 hour notice)

== Upgrade Notice ==

= 4.5.0 =

As of this release, the Varnish debugger uses remote data to collect a list of cookies, plugins, and themes known to conflict with Varnish. This will reduce the need to update the plugin for informational changes only. [Public information about this service is available on DreamObjects](https://varnish-http-purge.objects-us-east-1.dream.io/readme.txt).
