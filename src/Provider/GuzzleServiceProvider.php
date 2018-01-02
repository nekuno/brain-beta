<?php

namespace Provider;

use GuzzleHttp\Client;
use Silex\Application;
use Silex\ServiceProviderInterface;

class GuzzleServiceProvider implements ServiceProviderInterface
{

    /**
     * Register Guzzle with Silex
     *
     * @param Application $app Application to register with
     */
    public function register(Application $app)
    {

        $app['guzzle.client'] = $app->share(
            function () use ($app) {

                $c = new Client();
                if ($app['guzzle.verify']) {
                    $c->setDefaultOption('verify', $app['guzzle.verify']);
                }

                return $c;
            }
        );

        $app['instant.client'] = $app->share(
            function (Application $app) {

                $c = new Client(array('base_url' => $app['instant.host']));
                if ($app['guzzle.verify']) {
                    $c->setDefaultOption('verify', $app['guzzle.verify']);
                }
                $c->setDefaultOption('auth', array('brain', $app['instant_api_secret']));

                return $c;
            }
        );
    }

    public function boot(Application $app)
    {
    }
}
