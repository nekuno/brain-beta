<?php

namespace Event;

use Model\User;

class UserRegisteredEvent extends UserEvent
{
    protected $profile;
    protected $invitation;
    protected $token;
    protected $trackingData;

    public function __construct(User $user, array $profile, $invitation, User\Token\Token $token, $trackingData)
    {
        parent::__construct($user);
        $this->profile = $profile;
        $this->invitation = $invitation;
        $this->token = $token;
        $this->trackingData = $trackingData;
    }

    public function getProfile()
    {
        return $this->profile;
    }

    public function getInvitation()
    {
        return $this->invitation;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getTrackingData()
    {
        return $this->trackingData;
    }
}
