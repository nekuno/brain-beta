<?php

namespace Tests\API\Profile;

use Tests\API\APITest;

abstract class ProfileAPITest extends APITest
{
    protected function getOwnProfile($loggedInUserId = 1)
    {
        return $this->getResponseByRoute('/profile', 'GET', array(), $loggedInUserId);
    }

    protected function getOtherProfile($userId, $loggedInUserId = 1)
    {
        return $this->getResponseByRoute('/profile/' . $userId, 'GET', array(), $loggedInUserId);
    }

    protected function validateProfile($userData, $loggedInUserId = 1)
    {
        return $this->getResponseByRoute('/profile/validate', 'POST', $userData, $loggedInUserId);
    }

    protected function editProfile($userData, $loggedInUserId = 1)
    {
        return $this->getResponseByRoute('/profile', 'PUT', $userData, $loggedInUserId);
    }

    protected function getProfileMetadata($loggedInUserId = 1)
    {
        return $this->getResponseByRoute('/profile/metadata', 'GET', array(), $loggedInUserId);
    }

    protected function getCategories()
    {
        return $this->getResponseByRoute('/profile/categories', 'GET');
    }

    protected function getProfileFilters($loggedInUserId = 1)
    {
        return $this->getResponseByRoute('/profile/filters', 'GET', array(), $loggedInUserId);
    }

    protected function getProfileTags($type, $loggedInUserId = 1)
    {
        return $this->getResponseByRoute('/profile/tags/' . $type, 'GET', array(), $loggedInUserId);
    }

    protected function assertHasLocaleLabel($field, $message = '')
    {
        $this->assertArrayHasKey('label', $field, 'Has not locale label on ' . $message);
        $this->isType('string')->evaluate($field['label'], 'Label is not string on ' . $message);
    }
}