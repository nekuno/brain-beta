<?php

namespace Service;

use Event\UserRegisteredEvent;
use Manager\UserManager;
use Model\User\ProfileModel;
use Model\User\InvitationModel;
use Model\User\Group\GroupModel;
use Model\User\GhostUser\GhostUserManager;
use Symfony\Component\EventDispatcher\EventDispatcher as Dispatcher;
use Model\User\Token\TokensModel;

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
     * @var InvitationModel
     */
    protected $im;

    /**
     * @var GroupModel
     */
    protected $gm;

    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    public function __construct(UserManager $um, GhostUserManager $gum, TokensModel $tm, ProfileModel $pm, InvitationModel $im, GroupModel $gm, Dispatcher $dispatcher)
    {
        $this->um = $um;
        $this->gum = $gum;
        $this->tm = $tm;
        $this->pm = $pm;
        $this->im = $im;
        $this->gm = $gm;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param $userData
     * @param $profileData
     * @param $invitationToken
     * @param $oauth
     * @param $trackingData
     * @return string
     */
    public function register($userData, $profileData, $invitationToken, $oauth, $trackingData)
    {
        $this->im->validateTokenAvailable($invitationToken);
        $this->um->validate($userData);
        $this->pm->validateOnCreate($profileData);
        $this->tm->validateOnCreate($oauth);

        $user = $this->um->create($userData);
        if (isset($userData['enabled']) && $userData['enabled'] === false) {
            $this->gum->saveAsGhost($user->getId());
        }

        $token = $this->tm->create($user->getId(), $oauth['resourceOwner'], $oauth);
        $profile = $this->pm->create($user->getId(), $profileData);
        $invitation = $this->im->consume($invitationToken, $user->getId());
        if (isset($invitation['invitation']['group'])) {
            $this->gm->addUser($invitation['invitation']['group']->getId(), $user->getId());
        }
        $this->dispatcher->dispatch(\AppEvents::USER_REGISTERED, new UserRegisteredEvent($user, $profile, $invitation, $token, $trackingData));

        return $user->jsonSerialize();
    }

}