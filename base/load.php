<?php
define("APP_ROOT",realpath(dirname(dirname(dirname(__FILE__)))).'/');
define("BASE_ROOT",realpath(APP_ROOT.'lib/base').'/');
define("CONFIG_ROOT",realpath(APP_ROOT.'config').'/');
define("TEMPLATE_ROOT",realpath(APP_ROOT.'templates').'/');

define("DS",DIRECTORY_SEPARATOR);

include BASE_ROOT."tapped/tapped.php";

$tapped = Base\Tapped::getInstance();
$tapped->setCache(5);
$tapped->addPath(APP_ROOT."lib");
$tapped->addPath(APP_ROOT."controllers");
$tapped->addPath(APP_ROOT."models");
$tapped->addPath(APP_ROOT."resources");
$tapped->addPath(APP_ROOT."vendor");
$tapped->addPath(APP_ROOT."modules");

$tapped->registerAutoloader();
