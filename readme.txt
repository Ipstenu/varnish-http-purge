=== Varnish HTTP Purge ===
Contributors: Ipstenu, mikeschroder, techpriester
Tags: varnish, purge, cache
Requires at least: 4.7
Tested up to: 4.9
Stable tag: 4.3.1
Requires PHP: 5.6

Automatically empty Varnish Cache when content on your site is modified.

== Description ==

Varnish HTTP Purge sends a request to delete (aka flush) the cached data of a page or post every time it it modified. This happens when updating, publishing, commenting on, or deleting an post, and when changing themes.

<a href="https://www.varnish-cache.org/">Varnish</a> is a web application accelerator also known as a caching HTTP reverse proxy. You install it in front of any server that speaks HTTP and configure it to cache the contents. This plugin <em>does not</em> install Varnish for you, nor will it configure Varnish for WordPress.

Not all page caches are deleted every time, depending on your Varnish configuration. For example, when a post, page, or custom post type is edited, or a new comment is added, <em>only</em> the following pages will purge:

* The front page
* The post/page edited
* Any categories, tags, and/or custom taxonomies associated with the page
* Related feeds
* Associated JSON API pages

In addition, your <em>entire</em> cache will be deleted on the following actions:

* Changing themes
* Press the 'Purge Varnish Cache' button on the dashboard or toolbar

Plugins can hook into the purge actions as well, to filter their own events to trigger a purge.

And if you're into WP-CLI, you can use that too: `wp varnish purge`

On a multisite network using subfolders, only <strong>network admins</strong> can purge the main site. This is a security decision.

= Requirements =

* Pretty Permalinks enabled
* Varnish 3.x or higher

== Frequently Asked Questions ==

**Please report all issues in the [support forums](https://wordpress.org/support/plugin/varnish-http-purge)**

If you have code patches, [pull requests are welcome](https://github.com/Ipstenu/varnish-http-purge).

= How can I tell everything's working? =

From your WordPress Dashboard, go to Tools -> Varnish Status. There a page will auto-scan your main plugin page and report back any issues found. This includes any known problematic plugins.

= Does every WordPress plugin and theme work with Varnish? =

No. Some of them have behaviour that causes Varnish not to cache. While I can't debug that for you, there is an "Is Varnish Working?" tool (see WP Admin -> Tools -> Varnish Status) that tries to detect most of the common issues and direct you to resolutions.

= How can I debug my site? =

Use the Varnish Status page. It will try and help you figure out what's wrong.

= Will you fix my site if there's a conflict with a theme or plugin? =

I'm sorry but I can't do that, I don't have that much availability. I will try to point you towards solving it on your own. Bear in mind, that may mean you have to decide if using a specific plugin or theme is worth an imperfect cache.

= What version of Varnish is supported? =

This was built and tested on Varnish 3.x. While it is reported to work on 2.x and 4.x, it is only supported on v3 at this time.

= Why doesn't every page cache get deleted when I make a new post? =

Philosophy.

There are many other plugins out there which will allow you to granularly select what pages should and should not be deleted on updates. With that in mind, the choice was made for decisions instead of options, and simplicity was the driving principle. The plugin decides what's best to delete on updates, and provides hooks for developers to use as needed.

= Why doesn't my cache get deleted when I edit my theme? =

If you activate a new theme, or use the customizer to edit your theme, it will delete your cache.

If you edit theme (or plugin) files directly, WordPress cannot easily detect those changes, therefor the plugin will not delete the cache. In that situation, you will need to empty the cache manually.

= How do I manually delete the whole cache? =

Click the 'Empty Varnish Cache' button on the "Right Now" Dashboard (see the screenshot if you can't find it).

There's also an "Empty Cache" button on the admin toolbar.

= I don't see a button! =

That means your account doesn't have the appropriate permissions. Only administrators can empty the entire cache. In the case of a subfolder multisite network, only the <em>network</em> admins can empty the cache for the primary site.

= Why is nothing caching when I use PageSpeed? =

PageSpeed likes to put in Caching headers to say <em>not</em> to cache. To fix this, you need to put this in your `.htaccess` section for PageSpeed: `ModPagespeedModifyCachingHeaders off`

If you're using nginx, it's `pagespeed ModifyCachingHeaders off;`

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

If your webhost set up Varnish, you may need to ask them for the specifics if they don't have it documented. I've listed the ones I know about here, however you should still check with them if you're not sure.

<ul>
    <li><strong>DreamHost</strong> - If you're using DreamPress and Cloudflare, go into the Panel and click on the DNS settings for the domain. The entry for <em>resolve-to.domain</em> is your varnish server: `resolve-to.www A 208.97.157.172` -- If you're <em>NOT</em> using Cloudflare, you don't need it; it's just your normal IP.</li>
</ul>

= What if I have multiple varnish IPs? =

Multiple IPs are not supported at this time.

= Why isn't the whole cache deletion working? =

The plugin sends a PURGE command of <code>/.*</code> and `X-Purge-Method` in the header with a value of regex. If your Varnish server doesn't doesn't understand the wildcard, you can configure it to check for the header.

= How can I see what the plugin is sending to Varnish? =

Danger! Here be dragons! If you're command line savvy, you can monitor the interactions between the plugin and Varnish. This can help you understand what's not working quite right, but it can very confusing.

[Detailed directions can be found on the debugging section](https://github.com/Ipstenu/varnish-http-purge/wiki#debugging).

= How do I configure my VCL? =

This is a question beyond the support of plugin. I don't offer any Varnish Config help due to resources. I will say this, you absolutely must have PURGE set up in your VCL. This is still supported in Varnish v3, though may not be set up by default. Also, here are some links to other people who use this plugin and have made public their VCLs:

* <a href="https://github.com/dreamhost/varnish-vcl-collection">DreamHost's Varnish VCL Collection</a>

All of those VCLs work with this plugin.

= Can I filter things to add special URLs? =

Yes! [Full documentation can be found on Custom Filters in the wiki](https://github.com/Ipstenu/varnish-http-purge/wiki/Custom-Filters).

= Hey, don't you work at DreamHost? Is this Official or DreamHost only? =

* Yes, I do work for DreamHost.
* No, this plugin is not really official nor DreamHost Only

This plugin is installed by default for _all_ DreamPress installs on DreamHost, and I maintain it for DreamHost, but it was not originally an official DH plugin which means I will continue to support all users to the best of my ability.

== Changelog ==

= 4.3.1 = 
* 10 October 2017
* Copied a wrong line.

= 4.3.0 =
* 10 October 2017
* Add Varnish Flush for "this" page on front end
* Do not flush non-public taxonomies

= 4.2.0 =
* 30 August 2017
* More flexible support for custom cat/tag bases
* Added in support for custom taxonomies
* New function to generate the URLs, so it can be called by external plugins
* Move right now box to be called later, preventing double calls
* Extra check for if it's a URL, because some plugins are weird (props @danielkun)

== Screenshots ==

1. Purge button on Right Now (Dashboard Admin)
2. Purge button on Toolbar
3. Scanner results
4. Change Varnish IP address