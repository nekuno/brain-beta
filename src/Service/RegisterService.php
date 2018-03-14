<?php

namespace Service;

use Event\UserRegisteredEvent;
use Model\User\UserManager;
use Model\User\ProfileManager;
use Model\User\InvitationManager;
use Model\User\Group\GroupManager;
use Model\User\GhostUser\GhostUserManager;
use Symfony\Component\EventDispatcher\EventDispatcher as Dispatcher;
use Model\User\Token\TokensManager;

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
     * @var TokensManager
     */
    protected $tm;

    /**
     * @var ProfileManager
     */
    protected $pm;

    /**
     * @var InvitationManager
     */
    protected $im;

    /**
     * @var GroupService
     */
    protected $groupService;

    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    public function __construct(UserManager $um, GhostUserManager $gum, TokensManager $tm, ProfileManager $pm, InvitationManager $im, GroupService $groupService, Dispatcher $dispatcher)
    {
        $this->um = $um;
        $this->gum = $gum;
        $this->tm = $tm;
        $this->pm = $pm;
        $this->im = $im;
        $this->groupService = $groupService;
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
        if (isset($invitation['invitation']['orientationRequired']) && $invitation['invitation']['orientationRequired']) {
            $profileData['orientationRequired'] = true;
            $profile = $this->pm->update($user->getId(), $profileData);
        }
        if (isset($invitation['invitation']['group'])) {
            $this->groupService->addUser($invitation['invitation']['group']->getId(), $user->getId());
        }
        $this->dispatcher->dispatch(\AppEvents::USER_REGISTERED, new UserRegisteredEvent($user, $profile, $invitation, $token, $trackingData));

        return $user->jsonSerialize();
    }

}