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
     * @param $token
     * @param $oauth
     * @param $trackingData
     * @return string
     */
    public function register($userData, $profileData, $token, $oauth, $trackingData)
    {
        $this->im->validateToken($token);
        $this->um->validate($userData);
        $this->pm->validate($profileData);
        $this->tm->validate($oauth);

        $user = $this->um->create($userData);
        if (isset($userData['enabled']) && $userData['enabled'] === false) {
            $this->gum->saveAsGhost($user->getId());
        }

        $this->tm->create($user->getId(), $oauth['resourceOwner'], $oauth);
        $this->pm->create($user->getId(), $profileData);
        $invitation = $this->im->consume($token, $user->getId());
        if (isset($invitation['invitation']['group']['id'])) {
            $this->gm->addUser($invitation['invitation']['group']['id'], $user->getId());
        }
        $this->dispatcher->dispatch(\AppEvents::USER_REGISTERED, new UserRegisteredEvent($user, $trackingData));

        return $user->jsonSerialize();
    }

}