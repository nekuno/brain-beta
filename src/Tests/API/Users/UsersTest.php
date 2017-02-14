<?php

namespace Tests\API\Users;

class UsersTest extends UsersAPITest
{
    public function testUsers()
    {
        $this->assertGetUserWithoutCredentialsResponse();
        $this->assertGetUnusedUsernameAvailableResponse();
        $this->assertCreateUsersResponse();
        $this->assertGetExistingUsernameAvailableResponse();
        $this->assertLoginUserResponse();
        $this->assertGetOwnUserResponse();
        $this->assertGetOtherUserResponse();
        $this->assertEditOwnUserResponse();
        $this->assertValidationErrorsResponse();
    }

    protected function assertGetUserWithoutCredentialsResponse()
    {
        $response = $this->getOtherUser('janedoe');
        $this->assertStatusCode($response, 401, "Get User without credentials");
    }

    protected function assertGetUnusedUsernameAvailableResponse()
    {
        $response = $this->getUserAvailable('JohnDoe');
        $this->assertStatusCode($response, 200, "Bad response on get unused available username JohnDoe");
    }

    protected function assertCreateUsersResponse()
    {
        $userData = $this->getUserARegisterFixtures();
        $response = $this->createUser($userData);
        $formattedResponse = $this->assertJsonResponse($response, 201, "Create UserA");
        $this->assertUserAFormat($formattedResponse, "Bad User response on create user A");

        $userData = $this->getUserBRegisterFixtures();
        $response = $this->createUser($userData);
        $formattedResponse = $this->assertJsonResponse($response, 201, "Create UserB");
        $this->assertUserBFormat($formattedResponse, "Bad User response on create user B");
    }

    protected function assertGetExistingUsernameAvailableResponse()
    {
        $response = $this->getUserAvailable('JohnDoe');
        $this->assertStatusCode($response, 422, "Bad response on get existing available username JohnDoe");
    }

    protected function assertLoginUserResponse()
    {
        $userData = $this->getUserAFixtures();
        $response = $this->loginUser($userData);
        $this->assertStatusCode($response, 200, "Login UserA");
    }

    protected function assertGetOwnUserResponse()
    {
        $response = $this->getOwnUser();
        $formattedResponse = $this->assertJsonResponse($response, 200, "Get own user");
        $this->assertUserAFormat($formattedResponse, "Bad own user response");
    }

    protected function assertGetOtherUserResponse()
    {
        $response = $this->getOtherUser('janedoe');
        $formattedResponse = $this->assertJsonResponse($response, 200, "Get User B");
        $this->assertUserBFormat($formattedResponse, "Bad user B response");
    }

    protected function assertEditOwnUserResponse()
    {
        $userData = $this->getEditedUserAFixtures();
        $response = $this->editOwnUser($userData);
        $formattedResponse = $this->assertJsonResponse($response, 200, "Edit UserA");
        $this->assertEditedUserAFormat($formattedResponse, "Bad User response on edit user A");

        $userData = $this->getUserAEditionFixtures();
        $response = $this->editOwnUser($userData);
        $formattedResponse = $this->assertJsonResponse($response, 200, "Edit UserA");
        $this->assertEditedOriginalUserAFormat($formattedResponse, "Bad User response on edit user A");
    }

    protected function assertValidationErrorsResponse()
    {
        $userData = $this->getUserWithStatusFixtures();
        $response = $this->createUser($userData);
        $formattedResponse = $this->assertJsonResponse($response, 422, "Edit user with status error");
        $this->assertUserValidationErrorFormat($formattedResponse);

        $userData = $this->getUserWithSaltFixtures();
        $response = $this->createUser($userData);
        $formattedResponse = $this->assertJsonResponse($response, 422, "Edit user with salt error");
        $this->assertUserValidationErrorFormat($formattedResponse);

        $userData = $this->getUserWithNumericUsername();
        $response = $this->createUser($userData);
        $formattedResponse = $this->assertJsonResponse($response, 422, "Edit user with username error");
        $this->assertUserValidationErrorFormat($formattedResponse);
    }

    protected function assertUserAFormat($user)
    {
        $this->assertArrayHasKey('qnoow_id', $user, "User has not qnoow_id key");
        $this->assertArrayHasKey('username', $user, "User has not username key");
        $this->assertArrayHasKey('email', $user, "User has not email key");
        $this->assertEquals(1, $user['qnoow_id'], "qnoow_id is not 1");
        $this->assertEquals('JohnDoe', $user['username'], "username is not JohnDoe");
        $this->assertEquals('nekuno-johndoe@gmail.com', $user['email'], "email is not nekuno-johndoe@gmail.com");
    }

    protected function assertUserBFormat($user)
    {
        $this->assertArrayHasKey('qnoow_id', $user, "User has not qnoow_id key");
        $this->assertArrayHasKey('username', $user, "User has not username key");
        $this->assertEquals(2, $user['qnoow_id'], "qnoow_id is not 2");
        $this->assertEquals('JaneDoe', $user['username'], "username is not JaneDoe");
    }

    protected function assertEditedUserAFormat($response)
    {
        $this->assertArrayHasKey('user', $response, "User response has not user key");
        $this->assertArrayHasKey('jwt', $response, "User response has not jwt key");
        $user = $response['user'];
        $this->assertArrayHasKey('qnoow_id', $user, "User has not qnoow_id key");
        $this->assertArrayHasKey('username', $user, "User has not username key");
        $this->assertArrayHasKey('email', $user, "User has not email key");
        $this->assertEquals(1, $user['qnoow_id'], "qnoow_id is not 1");
        $this->assertEquals('JohnDoe', $user['username'], "username is not JohnDoe");
        $this->assertEquals('nekuno-johndoe_updated@gmail.com', $user['email'], "email is not nekuno-johndoe_updated@gmail.com");
    }

    protected function assertEditedOriginalUserAFormat($response)
    {
        $this->assertArrayHasKey('user', $response, "User response has not user key");
        $this->assertArrayHasKey('jwt', $response, "User response has not jwt key");
        $user = $response['user'];
        $this->assertArrayHasKey('qnoow_id', $user, "User has not qnoow_id key");
        $this->assertArrayHasKey('username', $user, "User has not username key");
        $this->assertArrayHasKey('email', $user, "User has not email key");
        $this->assertEquals(1, $user['qnoow_id'], "qnoow_id is not 1");
        $this->assertEquals('JohnDoe', $user['username'], "username is not JohnDoe");
        $this->assertEquals('nekuno-johndoe@gmail.com', $user['email'], "email is not nekuno-johndoe@gmail.com");
    }

    protected function assertUserValidationErrorFormat($exception)
    {
        $this->assertValidationErrorFormat($exception);
        $this->assertArrayHasKey('registration', $exception['validationErrors'], "User validation error does not have invalid key \"registration\"'");
        $this->assertEquals('Error registering user', $exception['validationErrors']['registration'], "registration key is not \"Error registering user\"");
    }

    private function getEditedUserAFixtures()
    {
        return array(
            'username' => 'JohnDoe',
            'email' => 'nekuno-johndoe_updated@gmail.com',
        );
    }

    private function getUserWithStatusFixtures()
    {
        return array(
            'user' => array(
                'username' => 'JohnDoe',
                'email' => 'nekuno-johndoe_updated@gmail.com',
                'status' => 'complete'
            ),
            'profile' => array(),
            'token' => 'join',
            'oauth' => array(
                'resourceOwner' => 'facebook',
                'oauthToken' => '12345',
                'resourceId' => '12345',
                'expireTime' => strtotime("+1 week"),
                'refreshToken' => '123456'
            )
        );
    }

    private function getUserWithSaltFixtures()
    {
        return array(
            'user' => array(
                'username' => 'JohnDoe',
                'email' => 'nekuno-johndoe_updated@gmail.com',
                'salt' => 'foo'
            ),
            'profile' => array(),
            'token' => 'join',
            'oauth' => array(
                'resourceOwner' => 'facebook',
                'oauthToken' => '12345',
                'resourceId' => '12345',
                'expireTime' => strtotime("+1 week"),
                'refreshToken' => '123456'
            )
        );
    }

    private function getUserWithNumericUsername()
    {
        return array(
            'user' => array(
                'username' => 1,
                'email' => 'nekuno-johndoe_updated@gmail.com',
            ),
            'profile' => array(),
            'token' => 'join',
            'oauth' => array(
                'resourceOwner' => 'facebook',
                'oauthToken' => '12345',
                'resourceId' => '12345',
                'expireTime' => strtotime("+1 week"),
                'refreshToken' => '123456'
            )
        );
    }
}