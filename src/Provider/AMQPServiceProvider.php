<?php


namespace Provider;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use Pimple\Container;
use Pimple\ServiceProviderInterface;


class AMQPServiceProvider implements ServiceProviderInterface
{

    /**
     * @param Container $app
     */
    public function register(Container $app)
    {

        $app['amqp'] = function ($app) {

            return new AMQPStreamConnection(
                $app['amqp.options']['host'],
                $app['amqp.options']['port'],
                $app['amqp.options']['user'],
                $app['amqp.options']['pass']
            );
        };
    }
}
