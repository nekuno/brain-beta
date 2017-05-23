<?php

namespace Controller\Admin;

use Service\DeviceService;
use Silex\Application;

/**
 * This controller is for testing proposes
 */
class DevelopersController
{

    public function pushNotificationAction(Application $app, $id)
    {
        /* @var $deviceService DeviceService */
        $deviceService = $app['device.service'];
        $result = $deviceService->pushMessage('Push notification Test', 'Push notification testing worked!', $id);

        return $app->json($result);
    }
}