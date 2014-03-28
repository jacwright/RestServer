<?php

require '../RestServer.php';
require 'TestController.php';

$server = new RestServer('debug');
$server->addClass('TestController');
$server->handle();
