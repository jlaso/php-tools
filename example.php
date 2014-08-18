<?php

error_reporting( E_ALL & ~E_NOTICE & ~E_DEPRECATED );

define ("_ROOT_", dirname(__FILE__));
define ("_SERVER_", "http://".$_SERVER['SERVER_NAME'].'/'.basename(_ROOT_)."/");

@define ("__DIR__",dirname(__FILE__));
require_once __DIR__.'/config.php';

require_once __DIR__.'/php-tools.php';


