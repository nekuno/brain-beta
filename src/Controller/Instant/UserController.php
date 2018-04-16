<?php

namespace Controller\Instant;

use Model\User\UserManager;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class UserController
 * @package Controller
 */
class UserController
{
    /**
     * @param Application $app
     * @param int $id
     * @return JsonResponse
     */
    public function getAction(Application $app, $id)
    {
        /* @var $model UserManager */
        $userManager = $app['users.manager'];
        $userArray = $userManager->getById($id)->jsonSerialize();
        $userArray = $userManager->deleteOtherUserFields($userArray);

        if (empty($userArray)) {
            return $app->json([], 404);
        }

        return $app->json($userArray);
    }

}
