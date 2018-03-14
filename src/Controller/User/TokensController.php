<?php

namespace Controller\User;


use ApiConsumer\Factory\ResourceOwnerFactory;
use ApiConsumer\ResourceOwner\FacebookResourceOwner;
use ApiConsumer\ResourceOwner\TwitterResourceOwner;
use Event\AccountConnectEvent;
use Model\User\UserManager;
use Model\User\User;
use Model\User\GhostUser\GhostUserManager;
use Model\User\SocialNetwork\SocialProfile;
use Model\User\SocialNetwork\SocialProfileManager;
use Model\User\Token\Token;
use Model\User\Token\TokensManager;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class TokensController
{
    public function postAction(Request $request, Application $app, User $user, $resourceOwner)
    {
        /* @var $model TokensManager */
        $model = $app['users.tokens.model'];

        $token = $model->create($user->getId(), $resourceOwner, $request->request->all());

        return $app->json($token, 201);
    }

    public function putAction(Request $request, Application $app, User $user, $resourceOwner)
    {
        /* @var $model TokensManager */
        $model = $app['users.tokens.model'];

        $token = $model->update($user->getId(), $resourceOwner, $request->request->all());

        return $app->json($token);
    }
}