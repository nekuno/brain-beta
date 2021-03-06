<?php

namespace Controller\User;

use Model\Exception\ErrorList;
use Model\Exception\ValidationException;
use Model\Content\ContentPaginatedManager;
use Model\Rate\RateManager;
use Model\Content\ContentReportManager;
use Model\User\UserManager;
use Model\User\User;
use Service\AuthService;
use Service\RecommendatorService;
use Service\UserStatsService;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class UserController
{

    /**
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     */
    public function getAction(Application $app, User $user)
    {
        /* @var $model UserManager */
        $model = $app['users.manager'];
        $userArray = $model->getById($user->getId())->jsonSerialize();

        return $app->json($userArray);
    }

    /**
     * @param Application $app
     * @param string $slug
     * @return JsonResponse
     */
    public function getOtherAction(Application $app, $slug)
    {
        /* @var $model UserManager */
        $model = $app['users.manager'];
        $userArray = $model->getBySlug($slug)->jsonSerialize();
        $userArray = $model->deleteOtherUserFields($userArray);

        if (empty($userArray)) {
            return $app->json([], 404);
        }

        return $app->json($userArray);
    }

    /**
     * @param Application $app
     * @param string $slug
     * @return JsonResponse
     */
    public function getPublicAction(Application $app, $slug)
    {
        /* @var $model UserManager */
        $model = $app['users.manager'];
        $userArray = $model->getPublicBySlug($slug)->jsonSerialize();
        $userArray = $model->deleteOtherUserFields($userArray);

        if (empty($userArray)) {
            return $app->json([], 404);
        }

        return $app->json($userArray);
    }

    public function autologinAction(Application $app, Request $request, User $user)
    {
        $profile = $app['users.profile.model']->getById($user->getId());

        $locale = $request->query->get('locale');
        $questionFilters = array('id' => $user->getId(), 'locale' => $locale);
        $questionsTotal = $app['users.questions.model']->countTotal($questionFilters);

        $returnData = array('user' => $user, 'profile' => $profile, 'questionsTotal' => $questionsTotal);

        return $app->json($returnData);
    }


    /**
     * @param Application $app
     * @param string $username
     * @throws NotFoundHttpException
     * @return JsonResponse
     */
    public function availableAction(Application $app, $username)
    {
        /* @var $user User */
        $user = $app['user'];
        if ($user && mb_strtolower($username) === $user->getUsernameCanonical()) {
            return $app->json();
        }
        /* @var $model UserManager */
        $model = $app['users.manager'];
        $model->validateUsername(0, $username);

        return $app->json();
    }

    public function setEnableAction(Request $request, Application $app, User $user)
    {
        $enabled = $request->request->get('enabled');
        /* @var $model UserManager */
        $model = $app['users.manager'];
        try{
            $model->setEnabled($user->getId(), $enabled);
        } catch (NotFoundHttpException $e)
        {
            return $app->json($e->getMessage(), 404);
        }

        if (!$enabled) {

            /** @var \Model\Device\DeviceManager $deviceModel */
            $deviceModel = $app['users.device.model'];
            $allDevices = $deviceModel->getAll($user->getId());
            foreach ($allDevices as $device) {
                $deviceModel->delete($device->toArray());
            }
        }

        return $app->json();
    }

    /**
     * @param Application $app
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function registerAction(Application $app, Request $request)
    {
        try {
            $data = $request->request->all();
            if (!isset($data['user']) || !isset($data['profile']) || !isset($data['token']) || !isset($data['oauth']) || !isset($data['trackingData'])) {
                $this->throwRegistrationException('Bad format');
            }
            $user = $app['register.service']->register($data['user'], $data['profile'], $data['token'], $data['oauth'], $data['trackingData']);
        } catch (\Exception $e) {
            $errorMessage = $this->exceptionMessagesToString($e);
            $message = \Swift_Message::newInstance()
                ->setSubject('Nekuno registration error')
                ->setFrom('enredos@nekuno.com', 'Nekuno')
                ->setTo($app['support_emails'])
                ->setContentType('text/html')
                ->setBody($app['twig']->render('email-notifications/registration-error-notification.html.twig', array(
                    'e' => $e,
                    'errorMessage' => $errorMessage,
                    'data' => json_encode($request->request->all()),
                )));

            $app['mailer']->send($message);

            $exceptionMessage = $app['env'] === 'dev' ? $errorMessage . ' ' . $e->getFile() . ' ' . $e->getLine() : "Error registering user";
            $this->throwRegistrationException($exceptionMessage);
        }

        return $app->json($user, 201);
    }

    /**
     * @param $message
     * @throws ValidationException
     */
    protected function throwRegistrationException($message)
    {
        $errorList = new ErrorList();
        $errorList->addError('registration', $message);
        throw new ValidationException($errorList);
    }

    /**
     * @param Application $app
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function putAction(Application $app, Request $request, User $user)
    {
        $data = $request->request->all();
        $data['userId'] = $user->getId();
        /* @var $model UserManager */
        $model = $app['users.manager'];
        $user = $model->update($data);

        /* @var $authService AuthService */
        $authService = $app['auth.service'];
        $jwt = $authService->getToken($data['userId']);

        return $app->json(
            array(
                'user' => $user,
                'jwt' => $jwt,
            ),
            200
        );
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function getMatchingAction(Request $request, Application $app, User $user)
    {
        $otherUserId = $request->get('userId');

        if (null === $otherUserId) {
            return $app->json(array(), 400);
        }

        try {
            /* @var $model \Model\Matching\MatchingManager */
            $model = $app['users.matching.model'];
            $matching = $model->getMatchingBetweenTwoUsersBasedOnAnswers($user->getId(), $otherUserId);
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($matching, !empty($matching) ? 201 : 200);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function getSimilarityAction(Request $request, Application $app, User $user)
    {
        $otherUserId = $request->get('userId');

        if (null === $otherUserId) {
            return $app->json(array(), 400);
        }

        try {
            /* @var $model \Model\Similarity\SimilarityManager */
            $model = $app['users.similarity.model'];
            $similarity = $model->getCurrentSimilarity($user->getId(), $otherUserId);
            $result = array('similarity' => $similarity->getSimilarity());

        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($result, !empty($result) ? 201 : 200);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function getUserContentAction(Request $request, Application $app, User $user)
    {
        $commonWithId = $request->get('commonWithId', null);
        $tag = $request->get('tag', array());
        $type = $request->get('type', array());

        /* @var $paginator \Paginator\Paginator */
        $paginator = $app['paginator'];

        $filters = array('id' => $user->getId());

        if ($commonWithId) {
            $filters['commonWithId'] = (int)$commonWithId;
        }

        foreach ($tag as $singleTag) {
            if (!empty($singleTag)) {
                $filters['tag'][] = urldecode($singleTag);
            }
        }

        foreach ($type as $singleType) {
            if (!empty($singleType)) {
                $filters['type'][] = urldecode($singleType);
            }
        }

        /* @var $model ContentPaginatedManager */
        $model = $app['users.content.model'];

        try {
            $result = $paginator->paginate($filters, $model, $request);
            $result['totals'] = $model->countAll($user->getId());
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($result, !empty($result) ? 201 : 200);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function getUserContentCompareAction(Request $request, Application $app, User $user)
    {
        $otherUserId = $request->get('userId');
        $tag = $request->get('tag', array());
        $type = $request->get('type', array());
        $showOnlyCommon = $request->get('showOnlyCommon', 0);

        if (null === $otherUserId) {
            return $app->json(array(), 400);
        }

        /* @var $paginator \Paginator\Paginator */
        $paginator = $app['paginator'];

        $filters = array('id' => $otherUserId, 'id2' => $user->getId(), 'showOnlyCommon' => (int)$showOnlyCommon);

        foreach ($tag as $singleTag) {
            if (!empty($singleTag)) {
                $filters['tag'][] = urldecode($singleTag);
            }
        }

        foreach ($type as $singleType) {
            if (!empty($singleType)) {
                $filters['type'][] = urldecode($singleType);
            }
        }

        /* @var $model \Model\Content\ContentComparePaginatedManager */
        $model = $app['users.content.compare.model'];

        try {
            $result = $paginator->paginate($filters, $model, $request);
            $result['totals'] = $model->countAll($otherUserId, $user->getId(), $showOnlyCommon);
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($result, !empty($result) ? 201 : 200);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function getUserContentTagsAction(Request $request, Application $app, User $user)
    {
        $search = $request->get('search', '');
        $limit = $request->get('limit', 0);

        if ($search) {
            $search = urldecode($search);
        }

        /* @var $model \Model\Content\ContentTagManager */
        $model = $app['users.content.tag.model'];

        try {
            $result = $model->getContentTags($user->getId(), $search, $limit);
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($result, !empty($result) ? 201 : 200);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function rateContentAction(Request $request, Application $app, User $user)
    {
        $rate = $request->request->get('rate');
        $data = $request->request->all();
        if (isset($data['linkId']) && !isset($data['id'])) {
            $data['id'] = $data['linkId'];
        }

        if (null == $data['linkId'] || null == $rate) {
            return $app->json(array('text' => 'Link Not Found', 'id' => $user->getId(), 'linkId' => $data['linkId']), 400);
        }

        $originContext = isset($data['originContext']) ? $data['originContext'] : null;
        $originName = isset($data['originName']) ? $data['originName'] : null;
        try {
            /* @var RateManager $model */
            $model = $app['users.rate.model'];
            $result = $model->userRateLink($user->getId(), $data['id'], 'nekuno', null, $rate, true, $originContext, $originName);
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($result, !empty($result) ? 201 : 200);
    }

    public function reportContentAction(Request $request, Application $app, User $user)
    {
        $reason = $request->request->get('reason');
        $reasonText = $request->request->get('reasonText');
        $contentId = $request->request->get('contentId');

        try {
            /* @var ContentReportManager $model */
            $model = $app['users.content.report.model'];
            $result = $model->report($user->getId(), $contentId, $reason, $reasonText);
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($result, 201);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     * @throws \Exception
     */
    public function getUserRecommendationAction(Request $request, Application $app, User $user)
    {
        /** @var RecommendatorService $recommendator */
        $recommendator = $app['recommendator.service'];

        try {
            $result = $recommendator->getUserRecommendationFromRequest($request, $user->getId());
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($result);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function getAffinityAction(Request $request, Application $app, User $user)
    {
        $linkId = $request->get('linkId');

        if (null === $linkId) {
            return $app->json(array(), 400);
        }

        try {
            /* @var $model \Model\Affinity\AffinityManager */
            $model = $app['users.affinity.model'];
            $affinity = $model->getAffinity($user->getId(), $linkId);
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($affinity, !empty($affinity) ? 201 : 200);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     * @throws \Exception
     */
    public function getContentRecommendationAction(Request $request, Application $app, User $user)
    {

        /* @var $recommendator RecommendatorService */
        $recommendator = $app['recommendator.service'];

        try {
            $result = $recommendator->getContentRecommendationFromRequest($request, $user->getId());
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($result, !empty($result) ? 201 : 200);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function getContentRecommendationTagsAction(Request $request, Application $app, User $user)
    {
        $search = $request->get('search', '');
        $limit = $request->get('limit', 0);

        if ($search) {
            $search = urldecode($search);
        }

        /* @var $model \Model\Recommendation\ContentRecommendationTagModel */
        $model = $app['users.recommendation.content.tag.model'];

        try {
            $result = $model->getRecommendedTags($user->getId(), $search, $limit);
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($result, !empty($result) ? 201 : 200);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function getContentAllTagsAction(Request $request, Application $app)
    {
        $search = $request->get('search', '');
        $limit = $request->get('limit', 0);

        if ($search) {
            $search = urldecode($search);
        }

        /* @var $model \Model\Recommendation\ContentRecommendationTagModel */
        $model = $app['users.recommendation.content.tag.model'];

        try {
            $result = $model->getAllTags($search, $limit);
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($result, !empty($result) ? 201 : 200);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     */
    public function getAllFiltersAction(Request $request, Application $app, User $user)
    {
        $locale = $request->query->get('locale');
        $filters = array();

        $metadataService = $app['metadata.service'];
        $filters['userFilters'] = $metadataService->getUserFilterMetadata($locale, $user->getId());

        $filters['contentFilters'] = $metadataService->getContentFilterMetadata($locale);

        return $app->json($filters, 200);
    }

    /**
     * @param Application $app
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function statusAction(Application $app, User $user)
    {
        /* @var $model UserManager */
        $model = $app['users.manager'];

        $status = $model->getStatus($user->getId());

        return $app->json(array('status' => $status));
    }

    /**
     * @param Application $app
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function statsAction(Application $app, User $user)
    {
        /** @var UserStatsService $statsService */
        $statsService = $app['userStats.service'];
        $stats = $statsService->getStats($user->getId());

        return $app->json($stats->toArray());
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function statsCompareAction(Request $request, Application $app, User $user)
    {
        $otherUserId = $request->get('userId');

        /** @var UserStatsService $statsService */
        $statsService = $app['userStats.service'];
        $stats = $statsService->getComparedStats($user->getId(), $otherUserId);

        return $app->json($stats->toArray());
    }

    private function exceptionMessagesToString(\Exception $e)
    {
        $errorMessage = $e->getMessage();
        if ($e instanceof ValidationException) {
            foreach ($e->getErrors() as $errors) {
                if (is_array($errors)) {
                    $errorMessage .= "\n" . implode("\n", $errors);
                } elseif (is_string($errors)) {
                    $errorMessage .= "\n" . $errors . "\n";
                }
            }
        }

        return $errorMessage;
    }
}
