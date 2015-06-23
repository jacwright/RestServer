<?php

require __DIR__ . '/../source/Jacwright/RestServer/RestServer.php';
require 'TestController.php';

$server = new \Jacwright\RestServer\RestServer('debug');
$server->addClass('TestController');
$server->handle();
