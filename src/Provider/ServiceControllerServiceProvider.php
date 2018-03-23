<?php

namespace Provider;

use Controller\ControllerResolver;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Application;

class ServiceControllerServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        $app['resolver'] = function ($app) {
            return new ControllerResolver($app, $app['logger']);
        };
    }

    public function boot(Application $app)
    {
        // noop
    }
}
