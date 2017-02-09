<?php

namespace Controller\Social;

use ApiConsumer\ResourceOwner\FacebookResourceOwner;
use ApiConsumer\ResourceOwner\TwitterResourceOwner;
use Model\User\GhostUser\GhostUserManager;
use Model\User\SocialNetwork\SocialProfile;
use Model\User\SocialNetwork\SocialProfileManager;
use Model\User\Token\TokensModel;
use Manager\UserManager;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class TokensController
 * @package Controller
 */
class TokensController
{
    /**
     * @param integer $id
     * @param Application $app
     * @return JsonResponse
     */
    public function getAllAction(Application $app, $id)
    {
        /* @var $model TokensModel */
        $model = $app['users.tokens.model'];

        $tokens = $model->getAll($id);

        return $app->json($tokens);
    }

    /**
     * @param Application $app
     * @param integer $id
     * @param string $resourceOwner
     * @return JsonResponse
     */
    public function getAction(Application $app, $id, $resourceOwner)
    {
        /* @var $model TokensModel */
        $model = $app['users.tokens.model'];

        $token = $model->getById($id, $resourceOwner);

        return $app->json($token);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @@param integer $id
     * @param string $resourceOwner
     * @return JsonResponse
     */
    public function postAction(Request $request, Application $app, $id, $resourceOwner)
    {
        /* @var $model TokensModel */
        $model = $app['users.tokens.model'];

        $token = $model->create($id, $resourceOwner, $request->request->all());

        /* @var $resourceOwnerFactory \ApiConsumer\Factory\ResourceOwnerFactory */
        $resourceOwnerFactory = $app['api_consumer.resource_owner_factory'];

        if ($resourceOwner === TokensModel::FACEBOOK) {

            /* @var $facebookResourceOwner FacebookResourceOwner */
            $facebookResourceOwner = $resourceOwnerFactory->build(TokensModel::FACEBOOK);

            if ($request->query->has('extend')) {
                $token = $facebookResourceOwner->extend($token);
            }

            if ($token->getRefreshToken()) {
                $token = $facebookResourceOwner->forceRefreshAccessToken($token);
            }
        }

        if ($resourceOwner == TokensModel::TWITTER) {
            /** @var TwitterResourceOwner $twitterResourceOwner */
            $twitterResourceOwner = $resourceOwnerFactory->build($resourceOwner);
            $profileUrl = $twitterResourceOwner->getProfileUrl($token);
            if (!$profileUrl) {
                //TODO: Add information about this if it happens
                return $app->json($token, 201);
            }
            $profile = new SocialProfile($id, $profileUrl, $resourceOwner);

            /* @var $ghostUserManager GhostUserManager */
            $ghostUserManager = $app['users.ghostuser.manager'];
            if ($ghostUser = $ghostUserManager->getBySocialProfile($profile)) {
                /* @var $userManager UserManager */
                $userManager = $app['users.manager'];
                $userManager->fuseUsers($id, $ghostUser->getId());
                $ghostUserManager->saveAsUser($id);
            } else {
                /** @var $socialProfilesManager SocialProfileManager */
                $socialProfilesManager = $app['users.socialprofile.manager'];
                $socialProfilesManager->addSocialProfile($profile);
            }
        }

        return $app->json($token, 201);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param integer $id
     * @param string $resourceOwner
     * @return JsonResponse
     */
    public function putAction(Request $request, Application $app, $id, $resourceOwner)
    {
        /* @var $model TokensModel */
        $model = $app['users.tokens.model'];

        $token = $model->update($id, $resourceOwner, $request->request->all());

        return $app->json($token);
    }

    /**
     * @param Application $app
     * @param integer $id
     * @param string $resourceOwner
     * @return JsonResponse
     */
    public function deleteAction(Application $app, $id, $resourceOwner)
    {
        /* @var $model TokensModel */
        $model = $app['users.tokens.model'];

        $token = $model->remove($id, $resourceOwner);

        return $app->json($token);
    }
}
