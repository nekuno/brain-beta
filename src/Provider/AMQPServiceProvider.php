<?php


namespace Provider;


use PhpAmqpLib\Connection\AMQPConnection;
use Silex\Application;
use Silex\ServiceProviderInterface;

/**
 * Class AMQPServiceProvider
 * @package Provider
 */
class AMQPServiceProvider implements ServiceProviderInterface
{

    /**
     * @param Application $app
     */
    public function register(Application $app)
    {

        $app['amqp'] = $app->share(
            function () use ($app) {

                return new AMQPConnection(
                    $app['amqp.options']['host'],
                    $app['amqp.options']['port'],
                    $app['amqp.options']['user'],
                    $app['amqp.options']['pass']
                );
            }
        );
    }

    /**
     * @param Application $app
     */
    public function boot(Application $app)
    {
    }

}
