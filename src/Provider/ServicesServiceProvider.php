<?php

namespace Provider;

use Pimple\Container;
use Service\AffinityRecalculations;
use Service\AMQPManager;
use Service\AuthService;
use Service\ChatMessageNotifications;
use Service\Consistency\ConsistencyCheckerService;
use Service\DeviceService;
use Service\EmailNotifications;
use Service\EventDispatcher;
use Service\GroupService;
use Service\ImageTransformations;
use Service\InstantConnection;
use Service\Links\EnqueueLinksService;
use Service\MetadataService;
use Service\Links\MigrateLinksService;
use Service\LinkService;
use Service\NotificationManager;
use Service\QuestionService;
use Service\RecommendatorService;
use Service\RegisterService;
use Service\SocialNetwork;
use Service\ThreadService;
use Service\TokenGenerator;
use Service\UserAggregator;
use Service\UserService;
use Service\UserStatsService;
use Service\Validator\Validator;
use Service\Validator\ValidatorFactory;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Loader\YamlFileLoader;

class ServicesServiceProvider implements ServiceProviderInterface
{

    /**
     * { @inheritdoc }
     */
    public function register(Container $app)
    {

        $app['auth.service'] = function ($app) {
            return new AuthService($app['users.manager'], $app['security.password_encoder'], $app['security.jwt.encoder'], $app['oauth.service'], $app['dispatcher.service'], $app['users.tokens.model']);
        };

        $app['user.service'] = function ($app) {
            return new UserService($app['users.manager'], $app['users.profile.model'], $app['users.tokens.model'], $app['users.tokenStatus.manager'], $app['users.rate.model'], $app['link.service'], $app['instant.connection.service'], $app['users.photo.manager'], $app['users.gallery.manager']);
        };

        $app['link.service'] = function ($app) {
            return new LinkService($app['links.model'], $app['popularity.manager'], $app['users.affinity.model']);
        };

        $app['register.service'] = function ($app) {
            return new RegisterService($app['users.manager'], $app['users.ghostuser.manager'], $app['users.tokens.model'], $app['users.profile.model'], $app['users.invitations.model'], $app['group.service'], $app['dispatcher']);
        };

        $app['chatMessageNotifications.service'] = function ($app) {
            return new ChatMessageNotifications($app['instant.client'], $app['emailNotification.service'], $app['orm.ems']['mysql_brain'], $app['dbs']['mysql_brain'], $app['translator'], $app['users.manager'], $app['users.profile.model']);
        };

        $app['affinityRecalculations.service'] = function ($app) {
            return new AffinityRecalculations($app['dispatcher.service'], $app['emailNotification.service'], $app['translator'], $app['neo4j.graph_manager'], $app['links.model'], $app['users.manager'], $app['users.affinity.model']);
        };

        $app['socialNetwork.service'] = function ($app) {
            return new SocialNetwork($app['users.socialNetwork.linkedin.model'], $app['users.lookup.model'], $app['users.profile.tag.model'], $app['users.profile.model'], $app['api_consumer.fetcher_factory']);
        };

        $app['emailNotification.service'] = function ($app) {
            return new EmailNotifications($app['mailer'], $app['orm.ems']['mysql_brain'], $app['twig']);
        };

        $app['device.service'] = function ($app) {
            return new DeviceService($app['guzzle.client'], $app['users.device.model'], $app['users.profile.model'], $app['translator'], $app['firebase_url'], $app['firebase_api_key'], $app['push_public_key'], $app['push_private_key']);
        };

        $app['imageTransformations.service'] = function () {
            return new ImageTransformations();
        };

        $app['recommendator.service'] = function ($app) {
            return new RecommendatorService(
                $app['paginator'], $app['paginator.content'], $app['users.groups.model'],
                $app['users.manager'], $app['users.recommendation.users.model'],
                $app['users.recommendation.content.model'], $app['users.recommendation.popularusers.model'],
                $app['users.recommendation.popularcontent.model'], $app['users.shares.manager']
            );
        };

        $app['threads.service'] = function ($app) {
            return new ThreadService($app['users.threads.manager'], $app['users.filterusers.manager'], $app['users.filtercontent.manager'], $app['users.threadCached.manager'], $app['users.threadData.manager']);
        };

        $app['userAggregator.service'] = function ($app) {
            return new UserAggregator(
                $app['users.manager'], $app['users.ghostuser.manager'], $app['users.socialprofile.manager'],
                $app['api_consumer.resource_owner_factory'], $app['socialNetwork.service'], $app['users.lookup.model'], $app['amqpManager.service']
            );
        };

        $app['userStats.service'] = function ($app) {
            return new UserStatsService($app['users.stats.manager'], $app['users.groups.model'], $app['users.relations.model'], $app['users.content.model'], $app['users.content.compare.model'], $app['users.shares.manager']);
        };

        $app['validator.service'] = function ($app) {
            return new Validator($app['neo4j.graph_manager'], $app['fields']);
        };

        $app['validator.factory'] = function ($app) {
            return new ValidatorFactory($app['neo4j.graph_manager'], $app['metadata.service'], $app['validator.config']);
        };

        $app['translator'] = $app->extend(
            'translator',
            function (Translator $translator) {

                $translator->addLoader('yaml', new YamlFileLoader());
                $translator->addResource('yaml', __DIR__ . '/../locales/en.yml', 'en');
                $translator->addResource('yaml', __DIR__ . '/../locales/es.yml', 'es');

                return $translator;
            }
        );

        $app['tokenGenerator.service'] = function () {
            return new TokenGenerator();
        };

        $app['amqpManager.service'] = function ($app) {
            return new AMQPManager($app['amqp']);
        };

        $app['notificationManager.service'] = function ($app) {
            return new NotificationManager($app['neo4j.graph_manager']);
        };

        $app['enqueueLinks.service'] = function ($app) {
            return new EnqueueLinksService($app['links.model'], $app['amqpManager.service']);
        };

        $app['consistency.service'] = function ($app) {
            return new ConsistencyCheckerService($app['neo4j.graph_manager'], $app['dispatcher'], $app['consistency']);
        };

        $app['dispatcher.service'] = function ($app) {
            return new EventDispatcher($app['dispatcher']);
        };

        $app['question.service'] = function ($app) {
            return new QuestionService($app['questionnaire.questions.model'], $app['questionnaire.admin.questions.model'], $app['questionnaire.questions.category.manager'], $app['questionnaire.questions.next.selector'], $app['users.questionCorrelation.manager']);
        };

        $app['metadata.service'] = function ($app) {
            return new MetadataService($app['users.metadataManager.factory'], $app['users.groups.model'], $app['users.profileOption.manager'], $app['metadata.utilities']);
        };

        $app['group.service'] = function ($app) {
            return new GroupService($app['users.groups.model'], $app['users.filterusers.manager'], $app['validator.factory']);
        };

        $app['migrateLinks.service'] = function ($app) {
            return new MigrateLinksService($app['neo4j.graph_manager']);
        };

        $app['instant.connection.service'] = function ($app) {
            return new InstantConnection($app['instant.client']);
        };
    }
}
