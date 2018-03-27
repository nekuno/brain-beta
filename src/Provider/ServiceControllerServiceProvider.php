<?php

namespace Provider;

use Controller\ArgumentValueResolver;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\AppArgumentValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;

class ServiceControllerServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        $app['argument_value_resolvers'] = function ($app) {
            return array_merge([new ArgumentValueResolver($app)], [new AppArgumentValueResolver($app)], ArgumentResolver::getDefaultArgumentValueResolvers());
        };
    }
}
