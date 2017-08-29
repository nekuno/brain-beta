<?php

namespace Tests\API\Threads;

use Tests\API\APITest;

abstract class ThreadsAPITest extends APITest
{
    protected function getThreads($loggedInUser =  self::OWN_USER_ID)
    {
        return $this->getResponseByRouteWithCredentials('/threads', 'GET', array(), $loggedInUser);
    }

    protected function getRecommendations($threadId, $loggedInUser =  self::OWN_USER_ID)
    {
        return $this->getResponseByRouteWithCredentials('/threads/' . $threadId . '/recommendations', 'GET', array(), $loggedInUser);
    }

    protected function editThread($threadId, $loggedInUser =  self::OWN_USER_ID)
    {
        return $this->getResponseByRouteWithCredentials('/threads/' . $threadId, 'PUT', array(), $loggedInUser);
    }

}