<?php

namespace Controller\Admin;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class UserController
{
    public function jwtAction(Application $app, $id)
    {
        $authService = $app['auth.service'];
        $jwt = $authService->getToken($id);

        return $app->json(array('jwt' => $jwt));
    }

    public function getUsersAction(Application $app, Request $request)
    {
        $order = $request->get('order', null);
        $orderDir = $request->get('orderDir', null);
        $filters = array(
            'order' => $order,
            'orderDir' => $orderDir,
        );

        $paginator = $app['paginator'];
        $model = $app['users.paginated.model'];

        $result = $paginator->paginate($filters, $model, $request);

        return $app->json($result);
    }
}