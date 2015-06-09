<?php

require __DIR__ . '/../vendor/autoload.php';
require 'TestController.php';

$server = new \Jacwright\RestServer\RestServer('debug');
$server->addClass('TestController');
$server->handle();
