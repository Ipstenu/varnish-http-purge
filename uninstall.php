<?php
/**
	Copyright 2016-2017 Mika Epstein (email: ipstenu@halfelf.org)

	This file is part of Varnish HTTP Purge, a plugin for WordPress.

	Varnish HTTP Purge is free software: you can redistribute it and/or modify
	it under the terms of the Apache License 2.0 license.

	Varnish HTTP Purge is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

// This is the uninstall script.

if( !defined( 'ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') )
	exit();

delete_site_option('vhp_varnish_url');
delete_site_option('vhp_varnish_ip');