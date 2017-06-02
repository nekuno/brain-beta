<?php

namespace Controller\User;

use Model\User;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class DeviceController
{

    public function subscribeAction(Application $app, Request $request, User $user)
    {
        $manager = $app['users.device.model'];
        $data = array(
            'userId' => $user->getId(),
            'registrationId' => $request->get('registrationId'),
            'token' => $request->get('token'),
            'platform' => $request->get('platform'),
        );

        if ($manager->exists($data['registrationId'])) {
            $device = $manager->update($data);
        } else {
            $device = $manager->create($data);
        }

        return $app->json($device->toArray());
    }

    public function unSubscribeAction(Application $app, Request $request, User $user)
    {
        $manager = $app['users.device.model'];
        $data = array(
            'userId' => $user->getId(),
            'registrationId' => $request->get('registrationId'),
            'token' => $request->get('token'),
            'platform' => $request->get('platform'),
        );

        $device = $manager->delete($data);

        return $app->json($device->toArray());
    }
}
