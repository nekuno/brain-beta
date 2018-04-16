<?php

namespace Controller\User;

use Model\User\UserManager;
use Model\Relations\RelationsManager;
use Model\User\User;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

class RelationsController
{
    /**
     * @param Application $app
     * @param User $user
     * @param string $relation
     * @return JsonResponse
     */
    public function indexAction(Application $app, User $user, $relation)
    {
        /* @var $model RelationsManager */
        $model = $app['users.relations.model'];

        $result = $model->getAll($relation, $user->getId());

        return $app->json($result);
    }

    /**
     * @param Application $app
     * @param User $user
     * @param string $slugTo
     * @param string $relation
     * @return JsonResponse
     */
    public function getAction(Application $app, User $user, $slugTo, $relation = null)
    {
        /* @var $model UserManager */
        $userManager = $app['users.manager'];
        $to = $userManager->getBySlug($slugTo)->getId();
        /* @var $model RelationsManager */
        $model = $app['users.relations.model'];

        if (!is_null($relation)) {
            $result = $model->get($user->getId(), $to, $relation);
        } else {
            $result = array();
            foreach (RelationsManager::getRelations() as $relation) {
                try {
                    $model->get($user->getId(), $to, $relation);
                    $result[$relation] = true;
                } catch (NotFoundHttpException $e) {
                    $result[$relation] = false;
                }
            }
        }

        return $app->json($result);
    }

    /**
     * @param Application $app
     * @param User $user
     * @param string $slugFrom
     * @param string $relation
     * @return JsonResponse
     */
    public function getOtherAction(Application $app, User $user, $slugFrom, $relation = null)
    {
        /* @var $model UserManager */
        $userManager = $app['users.manager'];
        $from = $userManager->getBySlug($slugFrom)->getId();
        /* @var $model RelationsManager */
        $model = $app['users.relations.model'];

        if (!is_null($relation)) {
            $result = $model->get($from, $user->getId(), $relation);
        } else {
            $result = array();
            foreach (RelationsManager::getRelations() as $relation) {
                try {
                    $model->get($from, $user->getId(), $relation);
                    $result[$relation] = true;
                } catch (NotFoundHttpException $e) {
                    $result[$relation] = false;
                }
            }
        }

        return $app->json($result);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @param string $slugTo
     * @param string $relation
     * @return JsonResponse
     */
    public function postAction(Request $request, Application $app, User $user, $slugTo, $relation)
    {
        /* @var $model UserManager */
        $userManager = $app['users.manager'];
        $to = $userManager->getBySlug($slugTo)->getId();

        $data = $request->request->all();

        /* @var $model RelationsManager */
        $model = $app['users.relations.model'];

        $result = $model->create($user->getId(), $to, $relation, $data);

        return $app->json($result);
    }

    /**
     * @param Application $app
     * @param User $user
     * @param string $slugTo
     * @param string $relation
     * @return JsonResponse
     */
    public function deleteAction(Application $app, User $user, $slugTo, $relation)
    {
        /* @var $model UserManager */
        $userManager = $app['users.manager'];
        $to = $userManager->getBySlug($slugTo)->getId();

        /* @var $model RelationsManager */
        $model = $app['users.relations.model'];

        $result = $model->remove($user->getId(), $to, $relation);

        return $app->json($result);
    }
}
