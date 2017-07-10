<?php

namespace Controller\User;


use ApiConsumer\Factory\ResourceOwnerFactory;
use ApiConsumer\ResourceOwner\FacebookResourceOwner;
use ApiConsumer\ResourceOwner\TwitterResourceOwner;
use Event\AccountConnectEvent;
use Manager\UserManager;
use Model\User;
use Model\User\GhostUser\GhostUserManager;
use Model\User\SocialNetwork\SocialProfile;
use Model\User\SocialNetwork\SocialProfileManager;
use Model\User\Token\Token;
use Model\User\Token\TokensModel;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class TokensController
{
    public function postAction(Request $request, Application $app, User $user, $resourceOwner)
    {
        /* @var $model TokensModel */
        $model = $app['users.tokens.model'];

        $token = $model->create($user->getId(), $resourceOwner, $request->request->all());

        return $app->json($token, 201);
    }

    public function putAction(Request $request, Application $app, User $user, $resourceOwner)
    {
        /* @var $model TokensModel */
        $model = $app['users.tokens.model'];

        $token = $model->update($user->getId(), $resourceOwner, $request->request->all());

        return $app->json($token);
    }
}