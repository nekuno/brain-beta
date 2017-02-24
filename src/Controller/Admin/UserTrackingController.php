<?php

namespace Controller\Admin;

use Model\User\UserTrackingModel;
use Silex\Application;

class UserTrackingController
{
    public function getAllAction(Application $app)
    {
        /* @var $model UserTrackingModel */
        $model = $app['users.tracking.model'];
        $result = $model->getAll();

        return $app->json($result);
    }

    public function getAction(Application $app, $id)
    {
        /* @var $model UserTrackingModel */
        $model = $app['users.tracking.model'];

        $result = $model->get($id);

        return $app->json($result);
    }
}