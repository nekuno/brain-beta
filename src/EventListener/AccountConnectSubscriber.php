<?php

namespace EventListener;

use ApiConsumer\Event\OAuthTokenEvent;
use ApiConsumer\Factory\ResourceOwnerFactory;
use Event\AccountConnectEvent;
use Manager\UserManager;
use Model\User\GhostUser\GhostUserManager;
use Model\User\SocialNetwork\SocialProfileManager;
use Model\User\Token\Token;
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

    /**
     * @var TokensModel
     */
    protected $tokensModel;

    public function __construct(AMQPManager $amqpManager, UserManager $um, GhostUserManager $gum, SocialProfileManager $spm, ResourceOwnerFactory $resourceOwnerFactory, TokensModel $tokensModel)
    {
        $this->amqpManager = $amqpManager;
        $this->um = $um;
        $this->gum = $gum;
        $this->spm = $spm;
        $this->resourceOwnerFactory = $resourceOwnerFactory;
        $this->tokensModel = $tokensModel;
    }

    public static function getSubscribedEvents()
    {
        return array(
            \AppEvents::ACCOUNT_CONNECTED => array('onAccountConnected'),
            \AppEvents::TOKEN_PRE_SAVE => array('onTokenSave'),
        );
    }

    public function onAccountConnected(AccountConnectEvent $event)
    {
        $userId = $event->getUserId();
        $token = $event->getToken();
        $resourceOwner = $token->getResourceOwner();

        switch ($resourceOwner) {
            case TokensModel::TWITTER:
                $this->createTwitterSocialProfile($token, $userId);
                break;
            default:
                break;
        }

        $message = array(
            'userId' => $userId,
            'resourceOwner' => $resourceOwner,
        );

        $this->amqpManager->enqueueFetching($message);
    }

    public function onTokenSave(OAuthTokenEvent $event)
    {
        $token = $event->getToken();
        $resourceOwner = $token->getResourceOwner();

        switch ($resourceOwner) {
            case TokensModel::FACEBOOK:
                $this->extendFacebook($token);
                break;
            default:
                break;
        }
    }

    private function extendFacebook(Token $token)
    {
        /* @var $facebookResourceOwner FacebookResourceOwner */
        $facebookResourceOwner = $this->resourceOwnerFactory->build(TokensModel::FACEBOOK);
        $facebookResourceOwner->extend($token);
        if ($token->getRefreshToken()) {
            $facebookResourceOwner->forceRefreshAccessToken($token);
        }
    }

    private function createTwitterSocialProfile(Token $token, $userId)
    {
        $resourceOwner = TokensModel::TWITTER;
        /** @var TwitterResourceOwner $resourceOwnerObject */
        $resourceOwnerObject = $this->resourceOwnerFactory->build($resourceOwner);
        $profileUrl = $resourceOwnerObject->requestProfileUrl($token);
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