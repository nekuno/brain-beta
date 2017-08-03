<?php

namespace Tests\API\Threads;

use Tests\API\APITest;

abstract class ThreadsAPITest extends APITest
{
    protected function getThreads($loggedInUser = 1)
    {
        return $this->getResponseByRoute('/threads', 'GET', array(), $loggedInUser);
    }

    protected function getRecommendations($threadId, $loggedInUser = 1)
    {
        return $this->getResponseByRoute('/threads/' . $threadId . '/recommendations', 'GET', array(), $loggedInUser);
    }

    protected function editThread($threadId, $loggedInUser = 1)
    {
        return $this->getResponseByRoute('/threads/' . $threadId, 'PUT', array(), $loggedInUser);
    }

}