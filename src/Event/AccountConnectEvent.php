<?php

namespace Event;

use Symfony\Component\EventDispatcher\Event;

class AccountConnectEvent extends Event
{

    protected $userId;
    protected $resourceOwner;
    protected $token;

    public function __construct($userId, $resourceOwner, $token)
    {
        $this->userId = $userId;
        $this->resourceOwner = $resourceOwner;
        $this->token = $token;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getResourceOwner()
    {
        return $this->resourceOwner;
    }

    public function getToken()
    {
        return $this->token;
    }

}
