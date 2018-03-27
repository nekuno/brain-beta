<?php

namespace Provider;

use Goutte\Client;
use Model\Parser\LinkedinParser;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class ParserProvider implements ServiceProviderInterface
{

    /**
     * { @inheritdoc }
     */
    public function register(Container $app)
    {
        $app['parser_provider.client'] = function () {
            $client = new Client();
            $client->setMaxRedirects(10);

            return $client;
        };

        $app['parser.linkedin'] = function ($app) {
            return new LinkedinParser($app['parser_provider.client']);
        };
    }
}
