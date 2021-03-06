<?php
/**
 * lynx.io site
 *
 * Everything should go through this file: index.php/article/article-slug
 */

if (!is_writable('./cache')) {
	die('./cache is not writable');
}
if (!is_writable('./articles/comments')) {
	die('./articles/comments is not writable');
}

require('config.php');
if (DEBUG) {
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
}

if (!empty($_GET['build_key']) && $_GET['build_key'] === $config['build_key']) {
	echo '<pre>' . `php build.php` . '</pre>';
	exit;
}

require('vendor/autoload.php');
Twig_Autoloader::register();

use dflydev\markdown\MarkdownParser;
$markdownParser = new MarkdownParser();

$loader = new Twig_Loader_Filesystem('template');
$twig_config = array();
if (!DEBUG) {
	$twig_config['cache'] = 'cache';
}
$twig = new Twig_Environment($loader, $twig_config);

// Latest articles
$all_articles = json_decode(file_get_contents('articles/articles.json'));
$comments = file_get_contents('articles/comments/comments.json');
$comments = json_decode($comments, true);
$latest_articles = array();
for ($i = 0; $i < count($all_articles); $i++) {
	$article = $all_articles[$i];

	if (isset($comments[$article->slug])) {
		$article->comments = $comments[$article->slug];
	} else {
		$article->comments = 0;
	}

	if ($i < 5) {
		$latest_articles[] = $all_articles[$i];
	}
}

// Global template stuff
$tmpl_vars = array(
	'assets_path'		=> $config['site_url'] . '/assets',
	'latest_articles'	=> $latest_articles,
	'site'				=> array(
		'name'	=> 'lynx.io',
		'url'	=> $config['site_url'],
		'desc'	=> 'lynx.io was founded by Callum Macrae in 2011 with the aim of providing web developers with a good and reliable source of information, tutorials and tips.',
	),
);

// Path
$path = str_replace('index.php', '', $_SERVER['SCRIPT_NAME']);

$pos = strpos($path, $_SERVER['REQUEST_URI']);
$path = substr($_SERVER['REQUEST_URI'], $pos + strlen($path));

$path = explode('?', $path);
$path = $path[0];

if (empty($path)) {
	include('lib/index.php');
} else if (preg_match('/\/$/', $path)) {
	$redirect = $_SERVER['REQUEST_URI'];
	$redirect = substr($redirect, 0, strlen($redirect) - 1);
	header("Location: $redirect");
	exit;
} else if (preg_match('/^([a-z]+)\/([^\/]+)$/i', $path, $matches)
		&& is_readable('lib/' . $matches[1] . '.php')) {
	include('lib/' . $matches[1] . '.php');
} else if (preg_match('/^([a-z]+)$/i', $path, $matches)) {
	if (is_readable('pages/' . $matches[1] . '.php')) {
		include('pages/' . $matches[1] . '.php');
	} else if (is_readable('pages/' . $matches[1] . '.md')) {
		include('lib/page.php');
	} else {
		throw404();
	}
} else {
	throw404();
}

/**
 * Throws a 404 error and displays the 404 page.
 */
function throw404() {
	global $tmpl_vars, $twig;

	header('HTTP/1.0 404 Not Found');
	$twig->display('404.twig.html', $tmpl_vars);
}

/**
 * Detect whether the request is an AJAX request or not, and marks the request
 * as JSON if required.
 *
 * @param boolean $json Mark the request as JSON?
 *
 * @return boolean True if request is boolean.
 */
function is_xhr($json = false) {
	$is_xhr = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
		strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

	if ($is_xhr) {
		header('Content-type: application/json');
	}

	return $is_xhr;
}