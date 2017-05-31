<?php

namespace Controller\Instant;

use Service\DeviceService;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class PushNotificationsController
{
    public function notifyAction(Application $app, Request $request)
    {
        /* @var $deviceService DeviceService */
        $deviceService = $app['device.service'];
        $userId = $request->get('userId');
        $category = $request->get('category');
        $data = $request->get('data');

        $result = $deviceService->pushMessage($data, $userId, $category);

        return $app->json($result);
    }
}
