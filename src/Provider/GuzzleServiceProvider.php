<?php

namespace Provider;

use GuzzleHttp\Client;
use Pimple\Container;
use Silex\Application;
use Pimple\ServiceProviderInterface;

class GuzzleServiceProvider implements ServiceProviderInterface
{

    /**
     * Register Guzzle with Silex
     *
     * @param Container $app Application to register with
     */
    public function register(Container $app)
    {

        $app['guzzle.client'] = function ($app) {

            $c = new Client();
            if ($app['guzzle.verify']) {
                $c->setDefaultOption('verify', $app['guzzle.verify']);
            }

            return $c;
        };

        $app['instant.client'] = function ($app) {

            $c = new Client(array('base_url' => $app['instant.host']));
            if ($app['guzzle.verify']) {
                $c->setDefaultOption('verify', $app['guzzle.verify']);
            }
            $c->setDefaultOption('auth', array('brain', $app['instant_api_secret']));

            return $c;
        };
    }

    public function boot(Application $app)
    {
    }
}
