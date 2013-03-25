<?php

/*
 * Copyright (c) 2013 Thomas Hourdel

 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

session_start();

require_once dirname(__FILE__) . '/app/config.php';
require_once dirname(__FILE__) . '/app/vendor/idiorm.php';
$app = require_once dirname(__FILE__) . '/app/vendor/f3/base.php';

if ($config['database_type'] == 'sqlite')
{
	ORM::configure('sqlite:' . dirname(__FILE__) . $config['sqlite_path']);
	install_sqlite();
}
else if ($config['database_type'] == 'mysql')
{
	try
	{
		ORM::configure('mysql:host=' . $config['mysql_host'] . ';dbname=' . $config['mysql_dbname'] . ';');
		ORM::configure('username', $config['mysql_username']);
		ORM::configure('password', $config['mysql_password']);
		ORM::for_table('zeta_feed');
		install_mysql();
	}
	catch (Exception $e)
	{
		die($e->getMessage());
	}
}

$app->set('CACHE', false);
$app->set('DEBUG', 3);
$app->set('UI', dirname(__FILE__) . '/app/templates/');
$app->set('TEMP', dirname(__FILE__) . $config['cache_dir']);
$app->set('base', $config['base']);
$app->set('cache', $config['cache_dir']);
$app->set('refreshTimer', $config['check_for_new_items']);
$app->set('page', $config['start_page']);
$app->set('force_update', $config['show_force_update_button']);

$app->route('GET /', function($app)
{
	check_user($app);
	echo Template::instance()->render('home.html');
});

$app->route('GET /login', function($app)
{
	echo Template::instance()->render('login.html');
});

$app->route('POST /login', function($app)
{
	require_once dirname(__FILE__) . '/app/vendor/bcrypt.php';
	global $config;

	$correct = Bcrypt::verify($app->get('POST.inputPassword'), $config['password']) &&
			   $app->get('POST.inputUsername') == $config['username'];

	if ($correct)
	{
		$_SESSION['zeta'] = $config['password'];
		setcookie('zeta', $config['password'], time() + $config['cookie_expiration']);
		echo 'success';
	}
	else echo 'failed';
});

$app->route('GET /logout', function($app)
{
	$_SESSION['zeta'] = '';
	setcookie('zeta', '', time() - 3600);
	$app->reroute('/login');
});

$app->route('GET /password', function($app)
{
	echo Template::instance()->render('password.html');
});

$app->route('POST /password', function($app)
{
	require_once dirname(__FILE__) . '/app/vendor/bcrypt.php';
	$hash = Bcrypt::hash($app->get('POST.inputPassword'));
	echo $hash;
});

$app->route('GET /query/all/@start/@count', function($app, $params)
{
	check_user($app);
	
	$query = ORM::for_table('zeta_item')
				->join('zeta_feed', array('zeta_item.feedid', '=', 'zeta_feed.id'))
				->order_by_desc('date')
				->limit($params['count'])
				->offset($params['start'])
				->find_many();

	echo prepare_items($app, $query);
});

$app->route('GET /query/unread/@start/@count', function($app, $params)
{
	check_user($app);
	
	$query = ORM::for_table('zeta_item')
				->join('zeta_feed', array('zeta_item.feedid', '=', 'zeta_feed.id'))
				->order_by_desc('date')
				->where('unread', 1)
				->limit($params['count'])
				->offset($params['start'])
				->find_many();

	echo prepare_items($app, $query);
});

$app->route('GET /query/starred/@start/@count', function($app, $params)
{
	check_user($app);
	
	$query = ORM::for_table('zeta_item')
				->join('zeta_feed', array('zeta_item.feedid', '=', 'zeta_feed.id'))
				->order_by_desc('date')
				->where('starred', 1)
				->limit($params['count'])
				->offset($params['start'])
				->find_many();

	echo prepare_items($app, $query);
});

$app->route('GET /query/star/@guid', function($app, $params)
{
	check_user($app);
	
	ORM::for_table('zeta_item')
	   ->where('guid', $params['guid'])
	   ->find_result_set()
	   ->set('starred', 1)
	   ->save();
});

$app->route('GET /query/unstar/@guid', function($app, $params)
{
	check_user($app);
	
	ORM::for_table('zeta_item')
	   ->where('guid', $params['guid'])
	   ->find_result_set()
	   ->set('starred', 0)
	   ->save();
});

$app->route('GET /query/markasread/@guid', function($app, $params)
{
	check_user($app);
	
	ORM::for_table('zeta_item')
	   ->where('guid', $params['guid'])
	   ->find_result_set()
	   ->set('unread', 0)
	   ->save();
});

$app->route('GET /query/markallasread', function($app)
{
	check_user($app);
	
	// Way faster with a raw query, don't really know the idiorm equivalent
	ORM::for_table('zeta_item')
	   ->raw_query('UPDATE `zeta_item` SET `unread` = 0 WHERE `unread` = 1')
	   ->find_many();
});

$app->route('GET /query/since/@id', function($app, $param)
{
	check_user($app);
	
	echo ORM::for_table('zeta_item')
			->where_gt('id', $param['id'])
			->count();
});

$app->route('GET /query/content/@guid', function($app, $params)
{
	check_user($app);
	
	$i = ORM::for_table('zeta_item')
			->where('guid', $params['guid'])
			->find_one();

	echo format_content($i->content);
});

$app->route('GET /update', function($app)
{
	$time_start = microtime(true);
	require_once dirname(__FILE__) . '/app/vendor/simplepie.php';
	global $config;

	$guiddb = ORM::for_table('zeta_item')
				 ->select('guid')
				 ->find_array();

	$find_guid = function($id) use($guiddb)
	{
		foreach ($guiddb as $g)
			if ($g['guid'] == $id)
				return true;
		return false;
	};

	foreach ($config['feed_urls'] as $feedURL)
	{
		set_time_limit(90);
		echo('Parsing ' . $feedURL . '<br>');

		// Setup simplepie first
		$feed = new SimplePie();
		$feed->set_feed_url($feedURL);
		$feed->set_timeout($config['feed_timeout']);
		$feed->enable_cache(true);
		$feed->set_cache_duration($config['feed_expiration']);
		$feed->set_cache_location(dirname(__FILE__) . $config['cache_dir']);
		$feed->init();
		$feed->handle_content_type();

		// Check if the feed exists in the db, add it otherwise
		$feedHash = md5($feedURL);
		$feeddb = ORM::for_table('zeta_feed')
					 ->where('hash', $feedHash)
					 ->find_one();

		if (!$feeddb)
		{
			echo('New feed detected: ' . $feed->get_title() . '<br>');
			$feeddb = ORM::for_table('zeta_feed')->create();
			$feeddb->hash = $feedHash;
			$feeddb->basetitle = $feed->get_title();
			$feeddb->baseurl = $feed->get_base();
			$feeddb->xmlurl = $feedURL;
			$feeddb->save();
		}

		// Favicon caching
		if (file_exists(dirname(__FILE__) . $config['cache_dir']) &&
			is_dir(dirname(__FILE__) . $config['cache_dir']) &&
			is_writable(dirname(__FILE__) . $config['cache_dir']))
		{
			$path = dirname(__FILE__) . $config['cache_dir'] . '/' . $feedHash . '.png';

			if (!file_exists($path) || (filemtime($path) <= (time() - $config['favicon_expiration'])))
			{
				echo 'Icon not found or expired, refreshing<br>';

				$curl_handle = curl_init('http://g.etfv.co/' . $feed->get_base() . '?defaulticon=bluepng');
				$fp = fopen($path, 'wb');
				curl_setopt($curl_handle, CURLOPT_FILE, $fp);
				curl_setopt($curl_handle, CURLOPT_HEADER, 0);
				curl_exec($curl_handle);
				curl_close($curl_handle);
				fclose($fp);
			}
		}

		// Look for new items in the feed
		$feedid = $feeddb->id;

		foreach ($feed->get_items() as $item)
		{
			$guid = $item->get_id(true);

			// New item
			if (!$find_guid($guid))
			{
				echo('New item detected: ' . $item->get_title() . '<br>');
				$date = $item->get_date('Y-m-d H:i:s');

				// Some feed don't provide any date, so we'll set it to the current one instead
				if (is_null($date))
					$date = date('Y-m-d H:i:s');

				// Don't insert items older than the expiration time
				$exp_date = date('Y-m-d H:i:s', time() - $config['read_feed_expiration']);
				if ($date < $exp_date)
				{
					echo('  Skipping, too old.<br>');
					continue;
				}

				$itemdb = ORM::for_table('zeta_item')->create();
				$itemdb->feedid = $feedid;
				$itemdb->guid = $guid;
				$itemdb->date = $date;
				$itemdb->unread = 1;
				$itemdb->url = $item->get_permalink();
				$itemdb->title = strip_tags($item->get_title());
				$itemdb->content = $item->get_content();
				$itemdb->starred = 0;
				$itemdb->save();
			}
		}

		// PHP <5.3 memory leak fix
		// Zeta is PHP 5.3+ only, but anyway...
		$feed->__destruct(); 
		unset($feed);
		
		echo('<br>');
	}

	// Clean old items
	$exp_date = date('Y-m-d H:i:s', time() - $config['read_feed_expiration']);
	echo 'Purging unread and unstarred feeds older than ' . $exp_date . ' <br>';
	$itemdb = ORM::for_table('zeta_item')
				 ->where('unread', 0)
				 ->where('starred', 0)
				 ->where_lt('date', $exp_date)
				 ->delete_many();

	$time = microtime(true) - $time_start;
	echo('Time: ' + $time);
});

$app->run();

function prepare_items($app, $query)
{
	global $config;
	$items = array();

	foreach ($query as $i)
	{
		$items[] = array(
				$i->hash,
				$i->baseurl,
				$i->basetitle,
				$i->title,
				$i->url,
				$i->unread,
				$i->starred,
				format_preview($i->content),
				$i->guid,
				format_date($i->date)
			);
	}

	$app->set('items', $items);
	$htmldata = Template::instance()->render('items.html');

	// Highest ID (used to check for any new items on the JS side)
	$highest = ORM::for_table('zeta_item')
				  ->order_by_desc('id')
				  ->find_one();

	$json = json_encode(array(
			'lastid'       => $highest->id,
			'htmldata'     => $htmldata,
			'totalCount'   => ORM::for_table('zeta_item')->count(),
			'starredCount' => ORM::for_table('zeta_item')->where('starred', 1)->count(),
			'unreadCount'  => ORM::for_table('zeta_item')->where('unread', 1)->count()
		));

	return $json;
}

function format_preview($c)
{
	global $config;
	$r = substr(strip_tags($c), 0, $config['preview_length']);
	return $r;
}

function format_content($c)
{
	if (strlen(trim($c)) == 0)
		return '[...]';
	
	$r = str_replace('<a ', '<a target="_blank" ', $c);
	return $r;
}

function format_date($d)
{
	$today = date('Y-m-d') . ' 00:00:00';

	if ($d > $today)
		return substr($d, 11, 5);

	return date('M d, Y', strtotime($d));
}

function install_sqlite()
{
	global $config;

	if (!is_file(dirname(__FILE__) . $config['sqlite_path']))
		touch(dirname(__FILE__) . $config['sqlite_path'])
		or die('Cannot create ' . dirname(__FILE__) . $config['sqlite_path'] . '. Please make sure the directory is writable.');

	$tables = ORM::for_table('zeta_feed')->raw_query('SELECT name FROM sqlite_master WHERE type="table" ORDER BY name')->find_many();
	$table_names = array();

	foreach ($tables as $table)
		$table_names[] = $table->name;

	foreach (array('zeta_feed', 'zeta_item') as $table)
	{
		if (!in_array($table, $table_names))
		{
			$query = file_get_contents(dirname(__FILE__) . '/app/dbdumps/' . $table . '.sqlite');
			ORM::raw_execute($query);
		}
	}
}

function install_mysql()
{
	global $config;

	$tables = ORM::for_table('zeta_feed')->raw_query('SHOW TABLES')->find_many();
	$table_names = array();
	$prop = 'Tables_in_' . $config['mysql_dbname'];

	foreach ($tables as $table)
		$table_names[] = $table->$prop;

	foreach (array('zeta_feed', 'zeta_item') as $table)
	{
		if (!in_array($table, $table_names))
		{
			$query = file_get_contents(dirname(__FILE__) . '/app/dbdumps/' . $table . '.mysql');
			ORM::raw_execute($query);
		}
	}
}

function check_user($app)
{
	require_once dirname(__FILE__) . '/app/vendor/bcrypt.php';
	global $config;

	if (isset($_SESSION['zeta']) && $_SESSION['zeta'] == $config['password'])
		return;

	else if (isset($_COOKIE['zeta']) && $_COOKIE['zeta'] == $config['password'])
		return;

	else $app->reroute('/login');
}

?>
