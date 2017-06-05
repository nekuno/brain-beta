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
        $result = $deviceService->pushMessage(array(
            'title' => 'Testing',
            'body' => 'This is a testing push notification',
            'image' => '/img/no-img/big.jpg',
            'on_click_path' => '/social-networks',
        ), $id);

        return $app->json($result);
    }
}