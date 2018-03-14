<?php

namespace Controller\User;

use Doctrine\ORM\EntityManager;
use Model\Entity\DataStatus;
use Model\Token\TokensManager;
use Model\User\User;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class DataController
{
    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function getStatusAction(Request $request, Application $app, User $user)
    {
        $resourceOwner = $request->query->get('resourceOwner');
        $userId = $user->getId();

        /** @var User\Token\TokenStatus\TokenStatusManager $tokenStatusManager */
        $tokenStatusManager = $app['users.tokenStatus.manager'];
        $statuses = $resourceOwner ? array($tokenStatusManager->getOne($userId, $resourceOwner)) : $tokenStatusManager->getAll($userId);

        if (empty($statuses)) {
            return $app->json(null, 404);
        }

        $responseData = array();
        foreach ($statuses as $tokenStatus) {
            $resource = $tokenStatus->getResourceOwner();

            $responseData[$resource] = array(
                'fetched' => $tokenStatus->getFetched(),
                'processed' => $tokenStatus->getProcessed(),
            );
        }

        return $app->json($responseData, 200);
    }

}
