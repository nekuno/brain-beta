<?php


namespace Provider;

use ApiConsumer\Factory\GoutteClientFactory;
use ApiConsumer\Images\ImageAnalyzer;
use ApiConsumer\LinkProcessor\LinkAnalyzer;
use ApiConsumer\LinkProcessor\LinkProcessor;
use ApiConsumer\LinkProcessor\LinkResolver;
use ApiConsumer\LinkProcessor\UrlParser\UrlParser;
use ApiConsumer\LinkProcessor\Processor\ScraperProcessor\ScraperProcessor;
use Pimple\Container;
use Silex\Application;
use Pimple\ServiceProviderInterface;

class LinkProcessorServiceProvider implements ServiceProviderInterface
{

    /**
     * { @inheritdoc }
     */
    public function register(Container $app)
    {

        $app['api_consumer.link_processor.processor.scrapper'] = function ($app) {

            return new ScraperProcessor($app['api_consumer.link_processor.goutte_factory'], $app['brain_base_url']);
        };

        $app['api_consumer.link_processor.link_analyzer'] = function () {

            return new LinkAnalyzer();
        };

        $app['api_consumer.link_processor.image_analyzer'] = function ($app) {

            return new ImageAnalyzer($app['guzzle.client']);
        };

        $app['api_consumer.link_processor.link_resolver'] = function ($app) {

            return new LinkResolver($app['api_consumer.link_processor.goutte_factory']);
        };
        //TODO: Only dependency to ScrapperProcessor
        $app['api_consumer.link_processor.url_parser.parser'] = function ($app) {

            return new UrlParser();
        };

        $app['api_consumer.link_processor'] = function ($app) {

            return new LinkProcessor(
                $app['api_consumer.processor_factory'], $app['api_consumer.link_processor.image_analyzer'], $app['users.tokens.model']
            );
        };

        $app['api_consumer.link_processor.goutte_factory'] = function () {
            return new GoutteClientFactory();
        };

    }

    /**
     * { @inheritdoc }
     */
    public function boot(Application $app)
    {
        // TODO: Implement boot() method.
    }
}
