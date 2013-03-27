<?php

// NOTE: all paths need a starting slash unless told otherwise

// Hardcoded login & password hash
// To generate a new password, jump to http://your.zeta/password,
$config['username'] = 'usename';
$config['password'] = 'hash';

// Login cookie expiration (in seconds)
$config['cookie_expiration'] = 60 * 60 * 24 * 30;

// If zeta is in a sub-folder, don't forget to change this settings
// and the RewriteBase in .htaccess
$config['base'] = '/zeta';

// Default start page without slash ('all', 'unread', 'starred')
$config['start_page'] = 'unread';

// Timeout for each feed (in seconds)
$config['feed_timeout'] = 10;

// Cache folder used for favicons, feeds and more (must be writable)
$config['cache_dir'] = '/cache';

// Feed cache expiration (in seconds)
$config['feed_expiration'] = 60 * 15;

// Favicon cache expiration (in seconds)
$config['favicon_expiration'] = 60 * 60 * 24 * 15;

// How much time to keep read feeds in the database in seconds (this will
// note delete starred feeds)
$config['read_feed_expiration'] = 60 * 60 * 24 * 30;

// Client timer (in seconds) used to check for new items available on the
// server.
$config['check_for_new_items'] = 60 * 5;

// Maximum length of the preview displayed next to the item title.
// You may want to ramp this up if you have a very wide screen...
$config['preview_length'] = 150;

// Database type (sqlite, mysql)
$config['database_type'] = 'sqlite';

// SQLite settings
$config['sqlite_path'] = '/app/zeta.db';

// MySQL settings
$config['mysql_host'] = 'localhost';
$config['mysql_username'] = '';
$config['mysql_password'] = '';
$config['mysql_dbname'] = '';

// Show/hide the force update button
$config['show_force_update_button'] = true;

// Show/hide the unread count in the favicon
$config['show_favicon_counter'] = true;

// Feeds
$config['feed_urls'] = array(
	'http://rss.slashdot.org/Slashdot/slashdot',
);

?>
