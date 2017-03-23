<?php

namespace Event;

use Symfony\Component\EventDispatcher\Event;

class UserLikedEvent extends Event
{
    private $userFromId;
    private $userToId;

    public function __construct($userFromId, $userToId)
    {
        $this->userFromId = $userFromId;
        $this->userToId = $userToId;
    }

    public function getUserFromId()
    {
        return $this->userFromId;
    }

    public function getUserToId()
    {
        return $this->userToId;
    }
} 