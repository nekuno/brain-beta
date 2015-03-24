<?php

use ApiConsumer\EventListener\OAuthTokenSubscriber;
use Dflydev\Silex\Provider\DoctrineOrm\DoctrineOrmServiceProvider;
use EventListener\UserAnswerSubscriber;
use EventListener\UserDataStatusSubscriber;
use Provider\AMQPServiceProvider;
use Provider\ApiConsumerServiceProvider;
use Provider\GuzzleServiceProvider;
use Provider\LinkProcessorServiceProvider;
use Provider\Neo4jPHPServiceProvider;
use Provider\PaginatorServiceProvider;
use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\SwiftmailerServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ValidatorServiceProvider;

$app = new Application();

$app['env'] = getenv('APP_ENV') ?: 'prod';

$app->register(new MonologServiceProvider(), array('monolog.name' => 'brain'));

$app->register(new DoctrineServiceProvider());
$app->register(new DoctrineOrmServiceProvider());
$app->register(new Neo4jPHPServiceProvider());

$app->register(new UrlGeneratorServiceProvider());
$app->register(new GuzzleServiceProvider());
$app->register(new ValidatorServiceProvider());
$app->register(new ServiceControllerServiceProvider());

$app->register(new ApiConsumerServiceProvider());
$app->register(new LinkProcessorServiceProvider());
$app->register(new PaginatorServiceProvider());

$app->register(new SwiftmailerServiceProvider());
$app->register(new AMQPServiceProvider());

//Config
$app->register(new Igorw\Silex\ConfigServiceProvider(__DIR__ . "/../config/params.yml"));

$replacements = array_merge($app['params'], array('app_root_dir' => __DIR__));
$app->register(new Igorw\Silex\ConfigServiceProvider(__DIR__ . "/../config/config.yml", $replacements));
$app->register(new Igorw\Silex\ConfigServiceProvider(__DIR__ . "/../config/config_{$app['env']}.yml", $replacements));

$app->register(new Igorw\Silex\ConfigServiceProvider(__DIR__ . "/../config/fields.yml", array(), null, 'fields'));

/**
 * Event system configuration. Initialize the listeners and subscribers below.
 */

/** @var \Symfony\Component\EventDispatcher\EventDispatcher $dispatcher */
$dispatcher = $app['dispatcher'];

$tokenRefreshedSubscriber = new OAuthTokenSubscriber(
    $app['api_consumer.user_provider'],
    $app['mailer'],
    $app['monolog'],
    $app['amqp']
);
$dispatcher->addSubscriber($tokenRefreshedSubscriber);

$statusSubscriber = new UserDataStatusSubscriber($app['orm.ems']['mysql_brain'], $app['amqp']);
$dispatcher->addSubscriber($statusSubscriber);

$answerSubscriber = new UserAnswerSubscriber($app['amqp']);
$dispatcher->addSubscriber($answerSubscriber);

return $app;
