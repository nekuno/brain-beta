<?php

namespace EventListener;

use ApiConsumer\Factory\ResourceOwnerFactory;
use Event\AccountConnectEvent;
use Manager\UserManager;
use Model\User\GhostUser\GhostUserManager;
use Model\User\SocialNetwork\SocialProfileManager;
use Model\User\Token\TokensModel;
use Service\AMQPManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Model\User\SocialNetwork\SocialProfile;
use ApiConsumer\ResourceOwner\FacebookResourceOwner;
use ApiConsumer\ResourceOwner\TwitterResourceOwner;

class AccountConnectSubscriber implements EventSubscriberInterface
{
    /**
     * @var AMQPManager
     */
    protected $amqpManager;

    /**
     * @var UserManager
     */
    protected $um;

    /**
     * @var GhostUserManager
     */
    protected $gum;

    /**
     * @var SocialProfileManager
     */
    protected $spm;

    /**
     * @var ResourceOwnerFactory
     */
    protected $resourceOwnerFactory;

    public function __construct(AMQPManager $amqpManager, UserManager $um, GhostUserManager $gum, SocialProfileManager $spm, ResourceOwnerFactory $resourceOwnerFactory)
    {
        $this->amqpManager = $amqpManager;
        $this->um = $um;
        $this->gum = $gum;
        $this->spm = $spm;
        $this->resourceOwnerFactory = $resourceOwnerFactory;
    }

    public static function getSubscribedEvents()
    {
        return array(
            \AppEvents::ACCOUNT_CONNECTED => array('onAccountConnected'),
        );
    }

    public function onAccountConnected(AccountConnectEvent $event)
    {
        $userId = $event->getUserId();
        $resourceOwner = $event->getResourceOwner();
        $token = $event->getToken();

        $message = array(
            'userId' => $userId,
            'resourceOwner' => $resourceOwner,
        );

        $this->amqpManager->enqueueMessage($message, 'brain.fetching.links');

        if ($resourceOwner === TokensModel::FACEBOOK) {
            /* @var $facebookResourceOwner FacebookResourceOwner */
            $facebookResourceOwner = $this->resourceOwnerFactory->build(TokensModel::FACEBOOK);
            $token = $facebookResourceOwner->extend($token);
            if (array_key_exists('refreshToken', $token) && is_null($token['refreshToken'])) {
                $token = $facebookResourceOwner->forceRefreshAccessToken($token);
            }
        }

        if ($resourceOwner == TokensModel::TWITTER) {
            /** @var TwitterResourceOwner $resourceOwnerObject */
            $resourceOwnerObject = $this->resourceOwnerFactory->build($resourceOwner);
            $profileUrl = $resourceOwnerObject->getProfileUrl($token);
            if ($profileUrl) {
                $profile = new SocialProfile($userId, $profileUrl, $resourceOwner);

                if ($ghostUser = $this->gum->getBySocialProfile($profile)) {
                    $this->um->fuseUsers($userId, $ghostUser->getId());
                    $this->gum->saveAsUser($userId);
                } else {
                    $this->spm->addSocialProfile($profile);
                }
            }
        }
    }
}