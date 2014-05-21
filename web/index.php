<?php

require_once __DIR__.'/../vendor/autoload.php';


//Neo4j client initializacion
$neo4jclient = new Everyman\Neo4j\Client('localhost', 7474);

//print_r($neo4jclient->getServerInfo());

//Silex app initialization
$app = new Silex\Application();
 
$app->get('/hello/{name}', function ($name) use ($app) {
    return 'Hello '.$app->escape($name);
});
 
$app->run();



