<?php

namespace Controller;

use Model\Link\LinkManager;
use Model\Rate\RateManager;
use Model\User\UserManager;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;


class FetchController
{

    /**
     * @param Request $request
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function addLinkAction(Request $request, Application $app)
    {

        $data = $request->request->all();

        try {
            /* @var $linkModel LinkManager */
            $linkModel = $app['links.model'];
            $link = $linkModel->addLink($data);

            if (empty($link)) {
                $link = $linkModel->findLinkByUrl($data['url']);
            }

            if (isset($data['userId'])) {
                /* @var $rateModel RateManager */
                $rateModel = $app['users.rate.model'];
                $rateModel->userRateLink($data['userId'], $link['id'], $data['resource'], $data['timestamp'], RateManager::LIKE);
            }

        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($link, empty($createdLink) ? 200 : 201);
    }

}
