Varnish HTTP Purge
==================

**THIS IS NOT WHERE YOU SHOULD DOWNLOAD THIS PLUGIN FROM!**

Yes, I accept pull requests and stuff here, but if you're installing the plugin from this location directly, you're silly. This is where dev things live. 

Install the plugin from the [WordPress.org Repository](http://wordpress.org/plugins/varnish-http-purge/) please and thank you.

## What is here?

* A beta copy of the plugin, for testing
* Language packs
* VCLs

## Note about Varnish 4

Supposedly this VCL works:

```
acl invalidators {
        "127.0.0.1"
        "localhost";
        "<servers_hostname>";
        "<servers_ip>";
}
sub vcl_recv {
        if (req.method == "PURGE") {
               if (!client.ip ~ invalidators) {
                        return (synth(405, "Not allowed"));
               }
        return (purge);
        }
}
```

You will also need to add your servers public IP in the authorized client list. 