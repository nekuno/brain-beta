<?php

namespace Controller\Questionnaire;

use Model\Metadata\MetadataManager;
use Model\User\Question\QuestionModel;
use Model\User;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class QuestionController
{
    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
//    public function getQuestionsAction(Request $request, Application $app)
//    {
//        $locale = $this->getLocale($request, $app['locale.options']['default']);
//        $skip = $request->query->get('skip');
//        $limit = $request->query->get('limit', 10);
//        /* @var QuestionModel $model */
//        $model = $app['questionnaire.questions.model'];
//
//        $questions = $model->getAll($locale, $skip, $limit);
//
//        return $app->json($questions);
//    }

    /**
     * Returns an unanswered question for given user
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     */
    public function getNextQuestionAction(Request $request, Application $app, User $user)
    {
        $locale = $this->getLocale($request, $app['locale.options']['default']);
        /* @var QuestionModel $model */
        $model = $app['questionnaire.questions.model'];

        $question = $model->getNextByUser($user->getId(), $locale);

        return $app->json($question);
    }

    /**
     * Returns an unanswered question for given user
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     */
    public function getNextOtherQuestionAction(Request $request, Application $app, User $user)
    {
        $otherUserId = $request->get('userId');
        $locale = $this->getLocale($request, $app['locale.options']['default']);
        /* @var QuestionModel $model */
        $model = $app['questionnaire.questions.model'];

        $question = $model->getNextByOtherUser($user->getId(), $otherUserId, $locale);

        return $app->json($question);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     */
    public function getQuestionAction(Request $request, Application $app)
    {
        $id = $request->get('questionId');
        $locale = $this->getLocale($request, $app['locale.options']['default']);
        /* @var $model QuestionModel */
        $model = $app['questionnaire.questions.model'];

        $question = $model->getById($id, $locale);

        return $app->json($question);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     * @throws \Exception
     */
    public function postQuestionAction(Request $request, Application $app, User $user)
    {
        $data = $request->request->all();
        $data['userId'] = $user->getId();
        $data['locale'] = $this->getLocale($request, $app['locale.options']['default']);

        /* @var $model QuestionModel */
        $model = $app['questionnaire.questions.model'];

        $question = $model->create($data);

        return $app->json($question, 201);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     * @throws \Exception
     */
    public function skipAction(Request $request, Application $app, User $user)
    {
        $id = $request->attributes->get('questionId');
        $locale = $this->getLocale($request, $app['locale.options']['default']);
        /* @var QuestionModel $model */
        $model = $app['questionnaire.questions.model'];

        $question = $model->getById($id, $locale);

        $model->skip($id, $user->getId());

        return $app->json($question, 201);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     * @throws \Exception
     */
    public function reportAction(Request $request, Application $app, User $user)
    {
        $id = $request->attributes->get('questionId');
        $reason = $request->request->get('reason');

        $locale = $this->getLocale($request, $app['locale.options']['default']);
        /* @var QuestionModel $model */
        $model = $app['questionnaire.questions.model'];

        $question = $model->getById($id, $locale);

        $model->report($id, $user->getId(), $reason);

        return $app->json($question, 201);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @return JsonResponse
     * @throws \Exception
     */
    public function getDivisiveQuestionsAction(Request $request, Application $app)
    {
        $locale = $request->get('locale', $app['locale.options']['default']);
        /* @var QuestionModel $model */
        $model = $app['questionnaire.questions.model'];

        $questions = $model->getDivisiveQuestions($locale);

        return $app->json($questions);
    }

    protected function getLocale(Request $request, $defaultLocale)
    {
        $locale = $request->get('locale', $defaultLocale);
        $validLocales = MetadataManager::$validLocales;
        if (!in_array($locale, $validLocales)) {
            $locale = $defaultLocale;
        }

        return $locale;
    }
}