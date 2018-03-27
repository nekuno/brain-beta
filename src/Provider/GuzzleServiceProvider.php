<?php

namespace Provider;

use GuzzleHttp\Client;
use Pimple\Container;
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

            $config = array();
            if ($app['guzzle.verify']) {
                $config['verify'] = $app['guzzle.verify'];
            }

            return new Client($config);
        };

        $app['instant.client'] = function ($app) {

            $config = array(
                'base_uri' => $app['instant.host'],
                'auth', array('brain', $app['instant_api_secret'])
            );
            if ($app['guzzle.verify']) {
                $config['verify'] = $app['guzzle.verify'];
            }

            return new Client($config);
        };
    }
}
