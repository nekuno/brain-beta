<?php

namespace Controller\User;

use Model\User\Thread\Thread;
use Model\User\Thread\ThreadPaginatedModel;
use Model\User;
use Paginator\Paginator;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class ThreadController
{
    /**
     * Parameters accepted when ContentThread:
     * -offset, limit and foreign
     * Parameters accepted when UsersThread:
     * -order
     *
     * @param Application $app
     * @param Request $request
     * @param string $threadId threadId
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function getRecommendationAction(Application $app, Request $request, $threadId)
    {
        $thread = $app['threads.service']->getByThreadId($threadId);

        $result = $this->getRecommendations($app, $thread, $request);
        if (!is_array($result)) {
            return $app->json($result, 500);
        }

        return $app->json($result);
    }

    /**
     * Get threads from a given user
     * @param Application $app
     * @param Request $request
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getByUserAction(Application $app, Request $request, User $user)
    {
        $filters = array(
            'userId' => $user->getId()
        );

        /** @var Paginator $paginator */
        $paginator = $app['paginator'];

        /** @var ThreadPaginatedModel $model */
        $model = $app['users.threads.paginated.model'];

        $result = $paginator->paginate($filters, $model, $request);

        foreach ($result['items'] as $key=>$threadId){
            $thread = $app['threads.service']->getByThreadId($threadId);
            $result['items'][$key] = $thread;
        }

        return $app->json($result);
    }

    /**
     * Create new thread for a given user
     * @param Application $app
     * @param Request $request
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function postAction(Application $app, Request $request, User $user)
    {
        $thread = $app['threads.service']->createThread($user->getId(), $request->request->all());

        return $app->json($thread, 201);
    }

    /**
     * @param Application $app
     * @param Request $request
     * @param User $user
     * @param integer $threadId
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function putAction(Application $app, Request $request, User $user, $threadId)
    {
        $thread = $app['threads.service']->updateThread($threadId, $user->getId(), $request->request->all());

        return $app->json($thread, 201);
    }

    /**
     * @param Application $app
     * @param integer $threadId
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function deleteAction(Application $app, $threadId)
    {
        try {
            $relationships = $app['threads.service']->deleteById($threadId);
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json($e->getMessage(), 500);
        }

        return $app->json($relationships);
    }

    /**
     * @param $app
     * @param $thread
     * @param Request $request
     * @return array|string string if got an exception in production environment
     * @throws \Exception
     */
    protected function getRecommendations(Application $app, Thread $thread, Request $request)
    {

        $recommendator = $app['recommendator.service'];
        try {
            $result = $recommendator->getRecommendationFromThreadAndRequest($thread, $request);
//            $this->cacheRecommendations($app, $thread, $request, $result);
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            } else {
                return $e->getMessage();
            }
        }

        return $result;
    }

    protected function cacheRecommendations(Application $app, Thread $thread, Request $request, array $result)
    {
        $isFirstPage = !$request->get('offset');
        if ($isFirstPage) {
            $firstResults = array_slice($result['items'], 0, 20);
            $app['threads.service']->cacheResults($thread, $firstResults, $result['pagination']['total']);
        }
    }
}