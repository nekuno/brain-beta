<?php

namespace Provider;

use Everyman\Neo4j\Client;
use Model\Neo4j\Constraints;
use Model\Neo4j\GraphManager;
use Model\Neo4j\Neo4jHandler;
use Monolog\Logger;
use Pimple\Container;
use Psr\Log\LoggerAwareInterface;
use Silex\Application;
use Pimple\ServiceProviderInterface;

class Neo4jPHPServiceProvider implements ServiceProviderInterface
{

    /**
     * { @inheritdoc }
     */
    public function register(Container $app)
    {

        // Initialize neo4j
        $app['neo4j.client'] = function ($app) {

            $client = new Client($app['neo4j.options']['host'], $app['neo4j.options']['port']);

            if (isset($app['neo4j.options']['auth']) && $app['neo4j.options']['auth']) {
                $client
                    ->getTransport()
                    ->setAuth($app['neo4j.options']['user'], $app['neo4j.options']['pass']);
            }

            return $client;
        };

        $app['neo4j.graph_manager'] = function ($app) {

            $manager = new GraphManager($app['neo4j.client']);

            if ($manager instanceof LoggerAwareInterface) {
                $manager->setLogger($app['monolog']);
            }

            return $manager;
        };

        $app['neo4j.constraints'] = function ($app) {

            return new Constraints($app['neo4j.graph_manager']);
        };
        
        $app['neo4j.logger.handler'] = function ($app) {

            return new Neo4jHandler(Logger::ERROR);
        };
        
        $app['monolog']->pushHandler($app['neo4j.logger.handler']);

    }

    /**
     * { @inheritdoc }
     */
    public function boot(Application $app)
    {

    }

}
