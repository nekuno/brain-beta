<?php

namespace Controller\User;

use Model\User\ProfileModel;
use Model\User\ProfileTagModel;
use Model\User;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProfileController
{
    /**
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     */
    public function getAction(Application $app, User $user)
    {
        /* @var $model ProfileModel */
        $model = $app['users.profile.model'];

        $profile = $model->getById($user->getId());

        return $app->json($profile);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function getOtherAction(Request $request, Application $app)
    {
        $id = $request->get('userId');
        /* @var $model ProfileModel */
        $model = $app['users.profile.model'];

        $profile = $model->getById($id);

        return $app->json($profile);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     */
    public function putAction(Request $request, Application $app, User $user)
    {
        /* @var $model ProfileModel */
        $model = $app['users.profile.model'];

        $profile = $model->update($user->getId(), $request->request->all());

        return $app->json($profile);
    }

    /**
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     */
    public function deleteAction(Application $app, User $user)
    {
        /* @var $model ProfileModel */
        $model = $app['users.profile.model'];

        $profile = $model->getById($user->getId());
        $model->remove($user->getId());

        return $app->json($profile);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function getMetadataAction(Request $request, Application $app)
    {
        $locale = $request->query->get('locale', 'en');

        $metadataService = $app['metadata.service'];
        $metadata = $metadataService->getProfileMetadataWithChoices($locale);

        return $app->json($metadata);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function getCategoriesAction(Request $request, Application $app)
    {
        $locale = $request->query->get('locale', 'en');

        $metadataService = $app['metadata.service'];
        $categories = $metadataService->getCategoriesMetadata($locale);

        return $app->json($categories);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function getFiltersAction(Request $request, Application $app)
    {
        $locale = $request->query->get('locale', 'en');

        $metadataService = $app['metadata.service'];
        $filters = $metadataService->getUserFilterMetadata($locale);

        return $app->json($filters);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws NotFoundHttpException
     */
    public function getProfileTagsAction(Request $request, Application $app)
    {
        $type = $request->get('type');
        $search = $request->get('search', '');
        $limit = $request->get('limit', 3);

        if (null === $type) {
            throw new NotFoundHttpException('type needed');
        }

        if ($search) {
            $search = urldecode($search);
        }

        /* @var $model ProfileTagModel */
        $model = $app['users.profile.tag.model'];

        $result = $model->getProfileTagsSuggestion($type, $search, $limit);

        return $app->json($result);
    }
}
