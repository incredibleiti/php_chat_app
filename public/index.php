<?php
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

require __DIR__ . '/../src/database.php';
require __DIR__ . '/../src/middleware.php';
require __DIR__ . '/../src/routes.php';

setupRoutes($app, $container); // <-- this must be called here

$app->run();
