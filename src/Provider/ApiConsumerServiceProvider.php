<?php

namespace Provider;

use ApiConsumer\Factory\FetcherFactory;
use ApiConsumer\Fetcher\FetcherService;
use ApiConsumer\Registry\Registry;
use Http\OAuth\Factory\ResourceOwnerFactory;
use Igorw\Silex\ConfigServiceProvider;
use Psr\Log\LoggerAwareInterface;
use Silex\Application;
use Silex\ServiceProviderInterface;

class ApiConsumerServiceProvider implements ServiceProviderInterface
{

    /**
     * { @inheritdoc }
     */
    public function register(Application $app)
    {

        $app->register(new ConfigServiceProvider(__DIR__ . "/../ApiConsumer/config/apiConsumer.yml"));

        // Resource Owners
        $app['api_consumer.resource_owner_factory'] = $app->share(
            function ($app) {

                $resourceOwnerFactory = new ResourceOwnerFactory($app['api_consumer.config']['resource_owner'], $app['guzzle.client'], $app['dispatcher']);

                return $resourceOwnerFactory;
            }
        );

        $app['api_consumer.resource_owner.google'] = $app->share(
            function ($app) {

                $resourceOwnerFactory = $app['api_consumer.resource_owner_factory'];

                /* @var $resourceOwnerFactory ResourceOwnerFactory */

                return $resourceOwnerFactory->build('google');
            }
        );

        $app['api_consumer.resource_owner.twitter'] = $app->share(
            function ($app) {

                $resourceOwnerFactory = $app['api_consumer.resource_owner_factory'];

                /* @var $resourceOwnerFactory ResourceOwnerFactory */

                return $resourceOwnerFactory->build('twitter');
            }
        );

        $app['api_consumer.resource_owner.spotify'] = $app->share(
            function ($app) {

                $resourceOwnerFactory = $app['api_consumer.resource_owner_factory'];

                /* @var $resourceOwnerFactory ResourceOwnerFactory */

                return $resourceOwnerFactory->build('spotify');
            }
        );

        $app['api_consumer.resource_owner.facebook'] = $app->share(
            function ($app) {

                $resourceOwnerFactory = $app['api_consumer.resource_owner_factory'];

                /* @var $resourceOwnerFactory ResourceOwnerFactory */

                return $resourceOwnerFactory->build('facebook');
            }
        );

        //Registry
        $app['api_consumer.registry'] = $app->share(
            function ($app) {

                $registry = new Registry($app['orm.ems']['mysql_brain']);

                return $registry;
            }
        );

        // Fetcher Service
        $app['api_consumer.fetcher_factory'] = $app->share(
            function ($app) {

                $resourceOwnerFactory = new FetcherFactory($app['api_consumer.config']['fetcher'], $app['api_consumer.resource_owner_factory']);

                return $resourceOwnerFactory;
            }
        );

        $app['api_consumer.fetcher'] = $app->share(
            function ($app) {

                $fetcher = new FetcherService(
                    $app['users.tokens.model'],
                    $app['api_consumer.link_processor'],
                    $app['links.model'],
                    $app['users.rate.model'],
                    $app['api_consumer.fetcher_factory'],
                    $app['dispatcher'],
                    $app['api_consumer.config']['fetcher']
                );

                if ($fetcher instanceof LoggerAwareInterface) {
                    $fetcher->setLogger($app['monolog']);
                }

                return $fetcher;
            }
        );
    }

    /**
     * { @inheritdoc }
     */
    public function boot(Application $app)
    {

    }

}
