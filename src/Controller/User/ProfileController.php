<?php

namespace Controller\User;

use Model\Metadata\ProfileFilterMetadataManager;
use Model\User\ProfileModel;
use Model\User\ProfileTagModel;
use Model\User;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ProfileController
 * @package Controller
 */
class ProfileController
{
    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     */
    public function getAction(Request $request, Application $app, User $user)
    {
        $locale = $request->query->get('locale');
        /* @var $model ProfileModel */
        $model = $app['users.profile.model'];

        $profile = $model->getById($user->getId(), $locale);

        return $app->json($profile);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function getOtherAction(Request $request, Application $app)
    {
        $locale = $request->query->get('locale');
        $id = $request->get('id');
        /* @var $model ProfileModel */
        $model = $app['users.profile.model'];

        $profile = $model->getById($id, $locale);

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
        $locale = $request->query->get('locale');

        /* @var $model ProfileFilterMetadataManager */
        $model = $app['users.profileFilter.model'];
        $metadata = $model->getProfileFilterMetadata($locale);

        return $app->json($metadata);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function getCategoriesAction(Request $request, Application $app)
    {
        $locale = $request->query->get('locale');

        /* @var $model ProfileFilterMetadataManager */
        $model = $app['users.profileFilter.model'];
        $categories = $model->getProfileCategories($locale);

        return $app->json($categories);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function getFiltersAction(Request $request, Application $app)
    {
        $locale = $request->query->get('locale');

        /* @var $model ProfileFilterMetadataManager */
        $model = $app['users.profileFilter.model'];
        $filters = $model->getFilters($locale);

        return $app->json($filters);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function validateAction(Request $request, Application $app)
    {
        /* @var $model ProfileModel */
        $model = $app['users.profile.model'];

        $model->validate($request->request->all());

        return $app->json();
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
        $limit = $request->get('limit', 0);

        if (null === $type) {
            throw new NotFoundHttpException('type needed');
        }

        if ($search) {
            $search = urldecode($search);
        }

        /* @var $model ProfileTagModel */
        $model = $app['users.profile.tag.model'];

        $result = $model->getProfileTags($type, $search, $limit);

        return $app->json($result);
    }
}
