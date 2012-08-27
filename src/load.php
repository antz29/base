<?php
if (!defined('TAPPED_CACHE')) define("TAPPED_CACHE",5);

define('ROOT',realpath(dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])))) . '/');
define('APP_ROOT', ROOT . 'src/');
define('PUBLIC_ROOT',APP_ROOT . 'public/');

define("BASE_ROOT",realpath(ROOT . 'vendor/antz29/base/src') . '/');

define("CONFIG_ROOT",realpath(APP_ROOT . 'config') . '/');
define("TEMPLATE_ROOT",realpath(APP_ROOT .'templates') . '/');
define("DS",DIRECTORY_SEPARATOR);

include BASE_ROOT . "tapped/tapped.php";

$tapped = Base\Tapped::getInstance();
$tapped->setCache(TAPPED_CACHE);

$tapped->addPath(ROOT."vendor");
$tapped->addPath(APP_ROOT."lib");
$tapped->addPath(APP_ROOT."controllers");
$tapped->addPath(APP_ROOT."models");
$tapped->addPath(APP_ROOT."resources");
$tapped->addPath(APP_ROOT."modules");

$tapped->registerAutoloader();

if (file_exists(ROOT.'/VERSION')) {
	define('VERSION',trim(file_get_contents(ROOT.'/VERSION')));
} else {
	define('VERSION','?');
}

$b = new Base\Base();
$b->run();