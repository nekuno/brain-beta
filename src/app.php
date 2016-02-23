<?php

use Dflydev\Silex\Provider\DoctrineOrm\DoctrineOrmServiceProvider;
use Igorw\Silex\ConfigServiceProvider;
use Provider\AMQPServiceProvider;
use Provider\ApiConsumerServiceProvider;
use Provider\GuzzleServiceProvider;
use Provider\LinkProcessorServiceProvider;
use Provider\LookUpServiceProvider;
use Provider\Neo4jPHPServiceProvider;
use Provider\ModelsServiceProvider;
use Provider\PaginatorServiceProvider;
use Provider\ServicesServiceProvider;
use Provider\SubscribersServiceProvider;
use Provider\ParserProvider;
use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Provider\SwiftmailerServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Sorien\Provider\PimpleDumpProvider;
use Symfony\Component\HttpFoundation\RequestMatcher;

$app = new Application();

$app['env'] = getenv('APP_ENV') ?: 'prod';
$app->register(new ConfigServiceProvider(__DIR__ . "/../config/params.yml"));
$replacements = array_merge($app['params'], array('app_root_dir' => __DIR__));
$app->register(new ConfigServiceProvider(__DIR__ . "/../config/config.yml", $replacements));
$app->register(new ConfigServiceProvider(__DIR__ . "/../config/config_{$app['env']}.yml", $replacements));
$app->register(new ConfigServiceProvider(__DIR__ . "/../config/fields.yml", array(), null, 'fields'));
$app->register(new MonologServiceProvider(), array('monolog.name' => 'brain', 'monolog.logfile' => __DIR__ . "/../var/logs/silex_{$app['env']}.log"));
$app->register(new DoctrineServiceProvider());
$app->register(new DoctrineOrmServiceProvider());
$app->register(new Neo4jPHPServiceProvider());
$app->register(new UrlGeneratorServiceProvider());
$app->register(new GuzzleServiceProvider());
$app->register(new ValidatorServiceProvider());
$app->register(new ServiceControllerServiceProvider());
$app->register(new ApiConsumerServiceProvider());
$app->register(new LinkProcessorServiceProvider());
$app->register(new ParserProvider());
$app->register(new LookUpServiceProvider());
$app->register(new PaginatorServiceProvider());
$app->register(new SwiftmailerServiceProvider());
$app->register(new AMQPServiceProvider());
$app->register(new TranslationServiceProvider(), array('locale_fallbacks' => array('en', 'es')));
$app->register(new ServicesServiceProvider());
$app->register(new ModelsServiceProvider());
$app['security.firewalls'] = array(
    'login' => [
        'pattern' => '^/login$',
        'anonymous' => true,
    ],
    'public_get' => array(
        'pattern' => new RequestMatcher('(^/users/find)|(^/users/tokens/)|(^/tokens/)|(^/lookup)|(^/profile/metadata$)|(^/users/available/)|(^/client/version$)',
                null, 'GET'),
        'anonymous' => true,
    ),
    'public_post' => array(
        'pattern' => new RequestMatcher('(^/users$)|(^/invitations/token/validate/)|(^/lookUp/webHook$)|(^/users/validate$)|(^/profile/validate$)',
                null, array('POST')),
        'anonymous' => true,
    ),
    'instant' => array(
        'pattern' => new RequestMatcher('^/instant/', null, null, $app['valid_ips']),
        'anonymous' => true,
    ),
    'admin' => array(
        'pattern' => new RequestMatcher('^/admin/', null, null, $app['valid_ips']),
        'anonymous' => true,
    ),
    'secured' => array(
        'pattern' => '^.*$',
        'users' => $app['security.users_provider'],
        'jwt' => array(
            'use_forward' => true,
            'require_previous_session' => false,
            'stateless' => true,
        )
    ),

);
$app->register(new Silex\Provider\SecurityServiceProvider());
$app['security.jwt'] = [
    'secret_key' => $app['secret'],
    'life_time' => 86400,
    'options' => [
        'username_claim' => 'sub', // default name, option specifying claim containing username
        'header_name' => 'Authorization', // default null, option for usage normal oauth2 header
        'token_prefix' => 'Bearer',
    ]
];
$app->register(new Silex\Provider\SecurityJWTServiceProvider());
$app->register(new SubscribersServiceProvider());
$app->register(new TwigServiceProvider(), array('twig.path' => __DIR__ . '/views'));
$app->register(new PimpleDumpProvider());

return $app;
