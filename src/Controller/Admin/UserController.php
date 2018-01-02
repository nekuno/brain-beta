<?php

namespace Controller\Admin;

use Silex\Application;

class UserController
{
    public function jwtAction(Application $app, $id)
    {
        $authService = $app['auth.service'];
        $jwt = $authService->getToken($id);

        return $app->json(array('jwt' => $jwt));
    }
}