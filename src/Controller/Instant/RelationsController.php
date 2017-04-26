<?php

namespace Controller\Instant;

use Model\User\RelationsModel;
use Model\User;
use Silex\Application;

class RelationsController
{
    public function contactFromAction(Application $app, $id)
    {
        /* @var $model RelationsModel */
        $model = $app['users.relations.model'];

        $users = $model->contactFrom($id);

        return $app->json($users);
    }

    public function contactToAction(Application $app, $id)
    {
        /* @var $model RelationsModel */
        $model = $app['users.relations.model'];

        $users = $model->contactTo($id);

        return $app->json($users);
    }

    public function contactAction(Application $app, $from, $to)
    {
        /* @var $model RelationsModel */
        $model = $app['users.relations.model'];

        $contact = $model->canContact($from, $to);

        return $app->json(array(), $contact ? 200 : 404);
    }
}
