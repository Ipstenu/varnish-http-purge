=== Varnish HTTP Purge ===
Contributors: Ipstenu, mikeschroder, techpriester
Tags: varnish, purge, cache
Requires at least: 4.7
Tested up to: 4.9
Stable tag: 4.5.0
Requires PHP: 5.6

Automatically empty Varnish Cache when content on your site is modified.

== Description ==

<a href="https://www.varnish-cache.org/">Varnish</a> is a web application accelerator also known as a caching HTTP reverse proxy. You install it in front of any server that speaks HTTP and configure it to cache the contents. This plugin <em>does not</em> install Varnish for you, nor will it configure Varnish for WordPress.

The Varnish HTTP Purge plugin sends a request to delete (aka flush) the cached data of a page or post every time it it modified. This happens when updating, publishing, commenting on, or deleting an post, and when changing themes.

In addition, it provides debugging tools to help you determine how effective your site setup is with Varnish. In order to provide the most up to date compatibility information, this tool contacts a service hosted on DreamObjects. [Public information about this service is available on DreamObjects](https://varnish-http-purge.objects-us-east-1.dream.io/readme.txt). The service is <em>ONLY</em> accessed when using the Varnish Debugging tool.

Not all page caches are deleted every time, depending on your Varnish configuration. For example, when a post, page, or custom post type is edited, or a new comment is added, <em>only</em> the following pages will purge:

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

=== WP CLI ===

* `wp varnish purge` - Flush the entire cache
* `wp varnish debug` - Help for debugging how well Varnish is (or isn't) working

=== Debugging ===

If you're working on a site and need to turn off caching, add this to your wp-config file: `define( 'VHP_DEBUG', true );`

That will break cache on page loads. It is _not_ recommended for production!

=== Requirements ===

* Pretty Permalinks enabled
* Varnish 3.x or higher

=== Privacy Policy ===

By default, no data is tracked. If you use the site scanner/debugging tool, your domain and IP address will access [a remote service hosted on DreamObjects](https://varnish-http-purge.objects-us-east-1.dream.io/readme.txt). No personally identifying transaction data is recorded or stored, only overall usage. IP addresses of the website making the request may be recorded by the service, but there is no way to access them and use it to correspond with individuals or processes.

Use of this service is required for the debugging tool, in order to provide up to date compatibility checks on plugins and themes that may conflict with running a server based cache (such as Varnish or Nginx) without needing to update the plugin every day.

== Frequently Asked Questions ==

**Please report all issues in the [support forums](https://wordpress.org/support/plugin/varnish-http-purge)**

If you have code patches, [pull requests are welcome](https://github.com/Ipstenu/varnish-http-purge).

=== Is this plugin caching my data? ===

No. This plugin tells your cache system when content is updated, and to delete the cached data at that time.

=== How does this plugin know what to delete? ===

When you update content on your site, like making a post or editing one, or someone leaving a comment, WordPress triggers a command on your server to purge (aka empty) the cache for any related pages, including the REST API.

=== Why doesn't the plugin automatically delete the whole cache? ===

Philosophy. There are many other plugins out there which will allow you to granularly select what pages should and should not be deleted on updates. With that in mind, the choice was made for decisions instead of options, and simplicity was the driving principle. The plugin decides what's best to delete on updates, and provides hooks for developers to use as needed.

=== Can I delete the entire cache? ===

Yes! Click the 'Empty Cache' button on the "Right Now" Dashboard (see the screenshot if you can't find it). There's also an "Empty Cache" button on the admin toolbar.

If you don't see a button, then your account doesn't have the appropriate permissions. Only administrators can empty the entire cache. In the case of a subfolder multisite network, only the <em>network</em> admins can empty the cache for the primary site.

=== Will the plugin delete my cache when I edit my theme or plugins? ===

No. WordPress can't detect file changes like that, and you really don't want it to. That would empty the cache every time you edited any file, which would cause your site to become unstable. You will need to use the Empty Cache buttons when you're done editing your code.

=== Does every WordPress plugin and theme work with Varnish? ===

No. Some of them have behaviour that causes Varnish not to cache, either by accident or design.

=== I'm a developer, can I tell your cache to empty in my plugin/theme? ===

Yes! [Full documentation can be found on Custom Filters in the wiki](https://github.com/Ipstenu/varnish-http-purge/wiki/Custom-Filters).

=== Can I turn off caching? ===

Yes and no. Remember, the plugin isn't doing the caching so it really depends on your server setup. You can set the following define in your `wp-config.php` file to attempt and disable caching, however this may not work on all setups: `define( 'VHP_DEBUG', true );`

=== How can I tell if everything's caching? ===

From your WordPress Dashboard, go to <em>Tools</em> -> <em>Varnish Debugging</em>. There a page will auto-scan your front page and report back any issues found. This includes any known problematic plugins. You can use it to scan any URL on your domain (but ONLY on your own domain).

=== Why doesn't the debug page autoload anymore? ===

The scan files were off-loaded to a service to allow for more frequent updates without having to require people to update the plugin. In order to ensure no one is scanned without consent, the auto-scanning was disabled.

=== Why is nothing caching when I use PageSpeed? ===

PageSpeed likes to put in Caching headers to say <em>not</em> to cache. To fix this, you need to put this in your `.htaccess` section for PageSpeed: `ModPagespeedModifyCachingHeaders off`

If you're using nginx, it's `pagespeed ModifyCachingHeaders off;`

=== Why aren't my changes showing when I use CloudFlare or another proxy? ===

When you use CloudFlare or any other similar service, you've put a proxy in front of the Varnish proxy. In general this isn't a bad thing, though it can introduce some network latency (that means your site may run slower because it has to go through multiple layers to get to the content). The problem arises when WordPress tries to send the purge request to your domain name and, with a proxy, that means the proxy service and not your website.

On single-site, you can edit this via the Tools -> Varnish Status page. On Multisite, you'll need to add the following to your wp-config.php file: `define('VHP_VARNISH_IP','123.45.67.89');`

Replace "123.45.67.89" with the IP of your <em>Varnish Server</em> (not CloudFlare, Varnish). <em>DO NOT</em> put in http in this define.

If you want to use WP-CLI, you can set an option in the database. This will NOT take precedence over the define, it's just there to let hosts who are using something like wp-cli do this for you in an automated fashion: `wp option update vhp_varnish_ip 123.45.67.890`

=== How do I find my Varnish IP? ===

Your Varnish IP must be one of the IPs that Varnish is listening on. If you use multiple IPs, or if you've customized your ACLs, you'll need to pick on that doesn't conflict with your other settings. For example, if you have Varnish listening on a public and private IP, you'll want to pick the private. On the other hand, if you told Varnish to listen on 0.0.0.0 (i.e. "listen on every interface you can") you would need to check what IP you set your purge ACL to allow (commonly 127.0.0.1 aka localhost), and use that (i.e. 127.0.0.1).

If your webhost set up Varnish, you may need to ask them for the specifics if they don't have it documented. I've listed the ones I know about here, however you should still check with them if you're not sure.

<ul>
	<li><strong>DreamHost</strong> - If you're using DreamPress and Cloudflare, go into the Panel and click on the DNS settings for the domain. The entry for <em>resolve-to.domain</em> is your varnish server: `resolve-to.www A 208.97.157.172` -- If you're <em>NOT</em> using Cloudflare, you don't need it; it's just your normal IP.</li>
</ul>

=== What if I have multiple varnish IPs? ===

Multiple IPs are not supported at this time.

=== Will you fix my site? ===

No. I will try to point you towards solving it on your own. This may mean you have to decide if using a specific plugin or theme is worth an imperfect cache.

=== What version of Varnish is supported? ===

This was built and tested on Varnish 3.x. While it is reported to work on 2.x and 4.x, it is only supported on v3 at this time.

=== Does this work with Nginx caching? ===

It can, if you configured nginx caching to respect the curl PURGE request.

=== Will you write my cache rules for me? ===

This is a question beyond the support of plugin. I do not have the resources available to offer any configuration help. Here are some basic gotchas to be aware of:

* To empty any cached data, the service will need to respect the PURGE command
* Not all cache services set up PURGE by default
* When flushing the whole cache, the plugin sends a PURGE command of <code>/.*</code> and sets the `X-Purge-Method` header to `regex`.

=== How can I see what the plugin is sending to Varnish? ===

Danger! Here be dragons! If you're command line savvy, you can monitor the interactions between the plugin and Varnish. This can help you understand what's not working quite right, but it can very confusing. [Detailed directions can be found on the debugging section on GitHub](https://github.com/Ipstenu/varnish-http-purge/wiki#debugging).

=== Hey, don't you work at DreamHost? Is this Official or DreamHost only? ===

* Yes, I do work for DreamHost.
* No, this plugin is not really official nor DreamHost Only

This plugin is installed by default for _all_ DreamPress installs on DreamHost, and I maintain it for DreamHost, but it was not originally an official DreamHost plugin which means I will continue to support all users to the best of my ability.

== Changelog ==

= 4.5.0 =
* May 2018
* Remote storage of problem plugins/themes
* Prevent auto-loading of scan for improved disclosure and compliance
* Changed colour of the purge button for improved visibility
* Support for nginx proxy headers

== Screenshots ==

1. Purge button on Right Now (Dashboard Admin)
2. Purge button on Toolbar
3. Scanner results
4. Change Varnish IP address

== Upgrade Notice ==

= 4.5.0 =

As of this release, the Varnish debugger uses remote data to collect a list of cookies, plugins, and themes known to conflict with Varnish. This will reduce the need to update the plugin for informational changes only. [Public information about this service is available on DreamObjects](https://varnish-http-purge.objects-us-east-1.dream.io/readme.txt).