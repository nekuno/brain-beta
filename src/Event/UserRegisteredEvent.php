<?php

namespace Event;

use Model\User;

class UserRegisteredEvent extends UserEvent
{

    protected $data = array();

    public function __construct(User $user, $data = array())
    {
        parent::__construct($user);
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function getData()
    {

        return $this->data;
    }
}
