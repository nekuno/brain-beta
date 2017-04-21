<?php
/**
 * @author Manolo Salsas <manolez@gmail.com>
 */
namespace Controller\User;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

/**
 * Class LookUpController
 * @package Controller
 */
class LookUpController
{
    /**
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function getAction(Application $app, Request $request)
    {
        $userData = array();
        $userData['email'] = $request->query->get('email');
        $userData['twitterUsername'] = $request->query->get('twitterUsername');
        $userData['facebookUsername'] = $request->query->get('facebookUsername');
        $userData['gender'] = $request->query->get('gender');
        $userData['location'] = $request->query->get('location');

        $lookUpData = $app['users.lookup.model']->completeUserData($userData);

        return $app->json($lookUpData);
    }

    /**
     * @param Application $app
     * @param integer $userId
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */

    public function setAction(Application $app, Request $request, $userId)
    {
        $userData = array();
        $userData['email'] = $request->request->get('email');
        $userData['twitterUsername'] = $request->request->get('twitterUsername');
        $userData['facebookUsername'] = $request->request->get('facebookUsername');
        $userData['gender'] = $request->request->get('gender');
        $userData['location'] = $request->request->get('location');

        $lookUpData = $app['users.lookup.model']->set($userId, $userData);

        return $app->json($lookUpData);
    }

    public function setFromWebHookAction(Request $request, Application $app)
    {
        /* @var $logger LoggerInterface */
        $logger = $app['monolog'];
        $logger->info(sprintf('Web hook called with content: %s', $request->getContent()));

        $app['users.lookup.model']->setFromWebHook($request);

        return true;
    }
}