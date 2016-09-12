<?php
define("ROOT_PATH", realpath(dirname(__FILE__) . '/..'));/* 指向public的上一级 */
define("APPLICATION_PATH",  ROOT_PATH . '/application');
$application = new Yaf_Application( ROOT_PATH . "/conf/application.ini", "develop");
$application->bootstrap()->run();
