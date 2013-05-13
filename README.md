Zeta
====

Introduction
------------
This is a web-based feed reader I quickly put together after the announcement that Google Reader would close on July 1, 2013. At that time, most alternatives were too heavy for my taste and full of useless features. I like to keep things simple, easy to use and own my data, so I made Zeta.

It's lightweight with a streamlined interface, supports most common feed standards (RSS, RDF, Atom), and doesn't have any social sharing link (yes, I consider this a feature).

Zeta draws inspiration from [Google Reader](http://en.wikipedia.org/wiki/Google_Reader), [Feedly](http://en.wikipedia.org/wiki/Feedly) and [selfoss](http://selfoss.aditu.de/).

![Zeta Screenshot](https://github.com/Chman/Zeta/raw/master/thumbnail1.png) ![Zeta Screenshot](https://github.com/Chman/Zeta/raw/master/thumbnail2.png)

Requirements
------------
* A webserver with `mod_rewrite` enabled
* PHP 5.3+
* MySQL or SQLite support
* A way to setup a cron job

On the client-side, Zeta has been tested with Chrome 25, Firefox 17 and Safari 6.

Installation
------------
The installation process is mostly manual as I didn't feel the need to make a fully graphical user interface for that purpose.

* Upload the whole `/zeta` folder to your webserver (do not skip the `.htaccess` files).
* Make sure `/zeta/cache` is writable, as well as `/zeta/app` if you plan to use SQLite instead of MySQL.
* Open the `/zeta/app/config.php` file and fill in your database settings. It's set to use SQLite by default.
* Open your browser and go to `http://your.web.host/zeta/`. If all goes well, it will automatically create the needed tables in your database and you'll end up on the login page.
* Jump to `http://your.web.host/zeta/password` to generate a password and copy the result into `$config['password']` in the configuration file along with an username of your choice.

Next, you'll need to set up a cron job to update your feeds automatically. If your host doesn't allow this, check out [Webcron](http://webcron.org/), they're cheap and reliable. I would recommend setting the cron to execute every 30 to 120 minutes or more if you know the websites you track aren't updated that often. The URL to call from your cron job is `http://your.web.host/zeta/update`.

You're all set ! You can now fill `$config['feed_urls']` with all the feeds you want to track. Once you're done, do a manual update to check if everything's ok by going to `http://your.web.host/zeta/update` (the very first update can take up to a few minutes depending on your host speed and bandwidth).

> **Note:** if you want to host your Zeta instance in a root or deeper folder instead of the default `/zeta`, don't forget to update `$config['base']` in the configuration file and the `RewriteBase` in `/zeta/.htaccess`.

Powered by
----------
* [F3](https://github.com/bcosca/fatfree)
* [Idiorm](http://github.com/j4mie/idiorm)
* [SimplePie](https://github.com/simplepie/simplepie/)
* [JQuery](http://jquery.com/)
* [Bootstrap](http://twitter.github.com/bootstrap/)

License
-------
GPLv3
