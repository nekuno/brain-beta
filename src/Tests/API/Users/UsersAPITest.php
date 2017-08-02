<?php

namespace Tests\API\Users;

use Tests\API\APITest;

abstract class UsersAPITest extends APITest
{
    protected function getUserAvailable($username)
    {
        return $this->getResponseByRoute('/users/available/' . $username);
    }

    protected function validateUserA($userData)
    {
        return $this->getResponseByRoute('/users/validate', 'POST', $userData);
    }

    protected function editOwnUser($userData, $loggedInUser = 1)
    {
        return $this->getResponseByRoute('/users', 'PUT', $userData, $loggedInUser);
    }

    protected function getOwnUser($loggedInUser = 1)
    {
        return $this->getResponseByRoute('/users', 'GET', array(), $loggedInUser);
    }

    protected function getOwnUserStatus($loggedInUser = 1)
    {
        return $this->getResponseByRoute('/data/status', 'GET', array(), $loggedInUser);
    }

    protected function getOtherUser($slug, $loggedInUser = 1)
    {
        return $this->getResponseByRoute('/users/' . $slug, 'GET', array(), $loggedInUser);
    }
}