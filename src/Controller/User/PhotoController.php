<?php

namespace Controller\User;

use Event\UserEvent;
use Model\Exception\ErrorList;
use Model\Exception\ValidationException;
use Model\Photo\PhotoManager;
use Model\User\User;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PhotoController
{
    /**
     * @var PhotoManager
     */
    protected $manager;

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
        $this->manager = $app['users.photo.manager'];

        $file = $this->getPostFile($request);
        $photo = $this->manager->create($user, $file);

        return $app->json($photo, 201);
    }

    protected function getPostFile(Request $request)
    {
        if ($request->request->has('base64')) {
            $file = base64_decode($request->request->get('base64'));
            if (!$file) {
                $this->manager->throwPhotoException('Invalid "base64" provided');
            }

            return $file;
        }

        if ($request->request->has('url')) {
            $url = $request->request->get('url');
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                $this->manager->throwPhotoException('Invalid "url" provided');
            }

            $file = @file_get_contents($url);
            if (!$file) {
                $this->manager->throwPhotoException('Unable to get photo from "url"');
            }

            return $file;
        }

        $this->manager->throwPhotoException('Invalid photo provided, param "base64" or "url" must be provided');
        return null;
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
