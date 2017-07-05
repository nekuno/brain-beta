<?php

namespace Controller\User;

use Event\UserEvent;
use Model\Exception\ValidationException;
use Model\User;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PhotoController
{

    public function getAllAction(Application $app, User $user)
    {

        $manager = $app['users.photo.manager'];

        $photos = $manager->getAll($user->getId());

        return $app->json($photos);
    }

    public function getAction(Application $app, $userId)
    {
        $manager = $app['users.photo.manager'];

        $photos = $manager->getAll($userId);

        return $app->json($photos);
    }

    public function postAction(Application $app, Request $request, User $user)
    {
        $manager = $app['users.photo.manager'];

        if ($request->request->has('base64')) {
            if (!$file = base64_decode($request->request->get('base64'))) {
                throw new ValidationException(array('photo' => array('Invalid "base64" provided')));
            }
        } elseif ($request->request->has('url')) {
            $url = $request->request->get('url');
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new ValidationException(array('photo' => array('Invalid "url" provided')));
            }
            $file = @file_get_contents($url);
            if (!$file) {
                throw new ValidationException(array('photo' => array('Unable to get photo from "url"')));
            }
        } else {
            throw new ValidationException(array('photo' => array('Invalid photo provided, param "base64" or "url" must be provided')));

        }

        $photo = $manager->create($user, $file);

        return $app->json($photo, 201);
    }

    public function postProfileAction(Application $app, Request $request, User $user, $photoId)
    {
        $xPercent = $request->request->get('x', 0);
        $yPercent = $request->request->get('y', 0);
        $widthPercent = $request->request->get('width', 100);
        $heightPercent = $request->request->get('height', 100);

        $photoManager = $app['users.photo.manager'];
        $photo = $photoManager->getById($photoId);

        if ($photo->getUserId() !== $user->getId()) {
            throw new AccessDeniedHttpException('Photo not allowed');
        }

        $oldPhoto = $user->getPhoto();

        $photoManager->setAsProfilePhoto($photo, $user, $xPercent, $yPercent, $widthPercent, $heightPercent);
        $app['dispatcher']->dispatch(\AppEvents::USER_PHOTO_CHANGED, new UserEvent($user));

        $oldPhoto->delete();

        return $app->json($user);

    }

    public function deleteAction(Application $app, User $user, $photoId)
    {

        $manager = $app['users.photo.manager'];

        $photo = $manager->getById($photoId);

        if ($photo->getUserId() !== $user->getId()) {
            throw new AccessDeniedHttpException('Photo not allowed');
        }

        $manager->remove($photoId);

        return $app->json($photo);
    }

    public function validateAction(Request $request, Application $app)
    {

    }

}
