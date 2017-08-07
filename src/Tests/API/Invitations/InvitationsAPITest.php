<?php

namespace Tests\API\Profile;

use Tests\API\APITest;

abstract class InvitationsAPITest extends APITest
{
    protected function getInvitations($loggedInUserId = 1)
    {
        return $this->getResponseByRoute('/invitations', 'GET', array(), $loggedInUserId);
    }

    protected function createInvitation($data, $loggedInUserId = 1)
    {
        return $this->getResponseByRoute('/invitations' , 'POST', $data, $loggedInUserId);
    }
}