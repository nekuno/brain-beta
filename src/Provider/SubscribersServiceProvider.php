<?php

namespace Provider;

use ApiConsumer\EventListener\ChannelSubscriber;
use ApiConsumer\EventListener\OAuthTokenSubscriber;
use EventListener\AccountConnectSubscriber;
use EventListener\ConsistencySubscriber;
use EventListener\ExceptionLoggerSubscriber;
use EventListener\InvitationSubscriber;
use EventListener\LookUpSocialNetworkSubscriber;
use EventListener\PrivacySubscriber;
use EventListener\SimilarityMatchingSubscriber;
use EventListener\UserAnswerSubscriber;
use EventListener\UserDataStatusSubscriber;
use EventListener\UserRelationsSubscriber;
use EventListener\UserSubscriber;
use EventListener\UserTrackingSubscriber;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class SubscribersServiceProvider implements ServiceProviderInterface
{

    /**
     * { @inheritdoc }
     */
    public function register(Application $app)
    {

    }

    /**
     * { @inheritdoc }
     */
    public function boot(Application $app)
    {

        /* @var $dispatcher EventDispatcher */
        $dispatcher = $app['dispatcher'];

        $dispatcher->addSubscriber(new OAuthTokenSubscriber($app['users.tokens.model'], $app['mailer'], $app['monolog'], $app['amqp']));
        $dispatcher->addSubscriber(new AccountConnectSubscriber($app['amqpManager.service'], $app['users.manager'], $app['users.ghostuser.manager'], $app['users.socialprofile.manager'], $app['api_consumer.resource_owner_factory'], $app['users.tokens.model']));
        $dispatcher->addSubscriber(new UserTrackingSubscriber($app['users.tracking.model']));
        $dispatcher->addSubscriber(new UserSubscriber($app['users.threads.manager'], $app['chatMessageNotifications.service']));
        $dispatcher->addSubscriber(new ChannelSubscriber($app['userAggregator.service']));
        $dispatcher->addSubscriber(new UserDataStatusSubscriber($app['users.tokenStatus.manager'], $app['amqpManager.service']));
        $dispatcher->addSubscriber(new UserAnswerSubscriber($app['amqpManager.service']));
        $dispatcher->addSubscriber(new InvitationSubscriber($app['neo4j.graph_manager'], $app['users.invitations.model']));
        $dispatcher->addSubscriber(new LookUpSocialNetworkSubscriber($app['neo4j.graph_manager'], $app['amqpManager.service']));
        $dispatcher->addSubscriber(new SimilarityMatchingSubscriber($app['emailNotification.service'], $app['users.manager'], $app['users.profile.model'], $app['users.groups.model'], $app['translator'], $app['notificationManager.service'], $app['social_host']));
        $dispatcher->addSubscriber(new UserRelationsSubscriber($app['guzzle.client'], $app['users.manager'], $app['instant.host']));
        $dispatcher->addSubscriber(new PrivacySubscriber($app['translator'], $app['users.groups.model'], $app['users.manager'], $app['users.profile.model'], $app['users.invitations.model'], $app['social_host']));
        $dispatcher->addSubscriber(new ExceptionLoggerSubscriber($app['monolog']));
        $dispatcher->addSubscriber(new ConsistencySubscriber($app['consistency.service'], $app['popularity.manager']));
    }

}
