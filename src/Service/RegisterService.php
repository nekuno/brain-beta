<?php

namespace Service;

use ApiConsumer\ResourceOwner\TwitterResourceOwner;
use Manager\UserManager;
use Model\User\ProfileModel;
use Model\User\SocialNetwork\SocialProfile;
use Model\User\SocialNetwork\SocialProfileManager;
use Model\User\InvitationModel;
use Model\User\Group\GroupModel;
use Model\User\GhostUser\GhostUserManager;
use Model\User\Thread\ThreadManager;
use Model\User;
use Model\User\Token\TokensModel;
use ApiConsumer\Factory\ResourceOwnerFactory;
use ApiConsumer\ResourceOwner\FacebookResourceOwner;


class RegisterService
{

    /**
     * @var UserManager
     */
    protected $um;

    /**
     * @var GhostUserManager
     */
    protected $gum;

    /**
     * @var TokensModel
     */
    protected $tm;

    /**
     * @var ProfileModel
     */
    protected $pm;

    /**
     * @var SocialProfileManager
     */
    protected $spm;

    /**
     * @var InvitationModel
     */
    protected $im;

    /**
     * @var GroupModel
     */
    protected $gm;

    /**
     * @var ThreadManager
     */
    protected $threadManager;

    /**
     * @var ResourceOwnerFactory
     */
    protected $resourceOwnerFactory;

    public function __construct(UserManager $um, GhostUserManager $gum, TokensModel $tm, ProfileModel $pm, SocialProfileManager $spm, InvitationModel $im, GroupModel $gm, ThreadManager $threadManager, ResourceOwnerFactory $resourceOwnerFactory)
    {
        $this->um = $um;
        $this->gum = $gum;
        $this->tm = $tm;
        $this->pm = $pm;
        $this->spm = $spm;
        $this->im = $im;
        $this->gm = $gm;
        $this->threadManager = $threadManager;
        $this->resourceOwnerFactory = $resourceOwnerFactory;
    }

    /**
     * @param $userData
     * @param $profileData
     * @param $token
     * @return string
     */
    public function register($userData, $profileData, $token)
    {
        if (isset($userData['oauth'])) {
            $oauthData = $userData['oauth'];
            unset($userData['oauth']);
        }
        $this->im->validateToken($token);
        $this->um->validate($userData);
        $this->pm->validate($profileData);

        $user = $this->um->create($userData);
        if (isset($userData['enabled']) && $userData['enabled'] === false) {
            $this->gum->saveAsGhost($user->getId());
        }
        if (isset($oauthData)) {
            $this->setOAuthData($user, $oauthData);
        }
        $this->pm->create($user->getId(), $profileData);
        $invitation = $this->im->consume($token, $user->getId());
        if (isset($invitation['invitation']['group']['id'])) {
            $this->gm->addUser($invitation['invitation']['group']['id'], $user->getId());
        }
        $threads = $this->threadManager->getDefaultThreads($user, User\Thread\ThreadManager::SCENARIO_DEFAULT_LITE);
        try {
            $createdThreads = $this->threadManager->createBatchForUser($user->getId(), $threads);
        } catch (\Exception $e) {
            sleep(5);
            $createdThreads = $this->threadManager->createBatchForUser($user->getId(), $threads);
        }

        if (count($createdThreads) < count ($threads) ) {
            sleep(5);
            $this->threadManager->createBatchForUser($user->getId(), $threads);
        }

        return $user->jsonSerialize();
    }

    private function setOAuthData(User $user, $oauthData)
    {
        $resourceOwner = $oauthData['resourceOwner'];
        $token = $this->tm->create($user->getId(), $resourceOwner, $oauthData);
        if ($resourceOwner === TokensModel::FACEBOOK) {
            /* @var $facebookResourceOwner FacebookResourceOwner */
            $facebookResourceOwner = $this->resourceOwnerFactory->build(TokensModel::FACEBOOK);
            $token = $facebookResourceOwner->extend($token);
            if (array_key_exists('refreshToken', $token) && is_null($token['refreshToken'])) {
                $token = $facebookResourceOwner->forceRefreshAccessToken($token);
            }
        }
        // TODO: This will not be executed since we only use Facebook for registration
        if ($resourceOwner == TokensModel::TWITTER) {
            /** @var TwitterResourceOwner $resourceOwnerObject */
            $resourceOwnerObject = $this->resourceOwnerFactory->build($resourceOwner);
            $profileUrl = $resourceOwnerObject->getProfileUrl($token);
            if ($profileUrl) {
                $profile = new SocialProfile($user->getId(), $profileUrl, $resourceOwner);

                if ($ghostUser = $this->gum->getBySocialProfile($profile)) {
                    $this->um->fuseUsers($user->getId(), $ghostUser->getId());
                    $this->gum->saveAsUser($user->getId());
                } else {
                    $this->spm->addSocialProfile($profile);
                }
            }
        }

        return $token;
    }
}