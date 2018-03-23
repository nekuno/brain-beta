<?php

namespace Provider;

use GuzzleHttp\Client;
use Pimple\Container;
use Service\LookUp\LookUpFullContact;
use Service\LookUp\LookUpPeopleGraph;
use Silex\Application;
use Pimple\ServiceProviderInterface;

class LookUpServiceProvider implements ServiceProviderInterface
{

    /**
     * { @inheritdoc }
     */
    public function register(Container $app)
    {

        $app['fullContact.client'] = function ($app) {
            return new Client(array('base_url' => $app['fullContact.url']));
        };

        $app['peopleGraph.client'] = function ($app) {
            return new Client(array('base_url' => $app['peopleGraph.url']));
        };

        $app['lookUp.fullContact.service'] = function ($app) {
            return new LookUpFullContact($app['fullContact.client'], $app['fullContact.consumer_key'], $app['url_generator']);
        };

        $app['lookUp.peopleGraph.service'] = function ($app) {
            return new LookUpPeopleGraph($app['peopleGraph.client'], $app['peopleGraph.consumer_key'], $app['url_generator']);
        };
    }

    /**
     * { @inheritdoc }
     */
    public function boot(Application $app)
    {

    }

}
