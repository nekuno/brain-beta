<?php

namespace Controller\Admin;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class GroupController
 * @package Controller
 */
class GroupController
{
    public function getAllAction(Application $app)
    {
        $groups = $app['users.groups.model']->getAll();

        return $app->json($groups);
    }

    public function getAction(Application $app, $id)
    {
        $group = $app['users.groups.model']->getById($id);

        return $app->json($group);
    }

    public function postAction(Request $request, Application $app)
    {
        $data = $request->request->all();

        $group = $app['users.groups.model']->create($data);

        return $app->json($group, 201);
    }

    public function putAction(Request $request, Application $app, $id)
    {
        $data = $request->request->all();

        $group = $app['users.groups.model']->update($id, $data);

        return $app->json($group);
    }

    public function deleteAction(Application $app, $id)
    {
        $group = $app['users.groups.model']->remove($id);

        return $app->json($group);
    }

    public function validateAction(Request $request, Application $app)
    {
        $data = $request->request->all();

        $model = $app['users.groups.model'];
        if (isset($data['id'])) {
            $groupId = $data['id'];
            unlink($data['id']);
            $model->validateOnUpdate($data, $groupId);
        } else {
            $model->validateOnCreate($data);
        }
        return $app->json();
    }
}