<?php

namespace Controller\User;

use Event\AnswerEvent;
use Model\Metadata\MetadataManager;
use Model\User\Question\QuestionManager;
use Model\User\Question\UserAnswerPaginatedManager;
use Model\User\User;
use Silex\Application;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

class AnswerController
{
    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     * @throws \Exception
     */
    public function indexAction(Request $request, Application $app, User $user)
    {
        $locale = $this->getLocale($request, $app['locale.options']['default']);

        /* @var $paginator \Paginator\Paginator */
        $paginator = $app['paginator'];

        $filters = array('id' => $user->getId(), 'locale' => $locale);
        /* @var $model UserAnswerPaginatedManager */
        $model = $app['users.questions.model'];

        $result = $paginator->paginate($filters, $model, $request);

        foreach ($result['items'] as &$questionData)
        {
            $question = $questionData['question'];
            $questionData['question'] = $this->setIsRegisterQuestion($question, $user, $app);
        }

        return $app->json($result);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     * @throws \Exception
     */
    public function getAnswerAction(Request $request, Application $app, User $user)
    {
        $questionId = (integer)$request->attributes->get('questionId');
        $locale = $this->getLocale($request, $app['locale.options']['default']);

        $result = $app['users.answers.model']->getUserAnswer($user->getId(), $questionId, $locale);

        return $app->json($result);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     * @throws \Exception
     */
    public function answerAction(Request $request, Application $app, User $user)
    {
        $data = $request->request->all();
        $data['userId'] = $user->getId();
        $data['locale'] = $this->getLocale($request, $app['locale.options']['default']);

        $userAnswer = $app['users.answers.model']->answer($data);

        // TODO: Refactor this to listener
        /* @var $questionModel QuestionManager */
        $questionModel = $app['questionnaire.questions.model'];
        $questionModel->setOrUpdateRankingForQuestion($data['questionId']);

        return $app->json($userAnswer, 201);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     * @throws \Exception
     */
    public function updateAction(Request $request, Application $app, User $user)
    {
        $data = $request->request->all();
        $data['userId'] = $user->getId();
        $data['locale'] = $this->getLocale($request, $app['locale.options']['default']);

        $userAnswer = $app['users.answers.model']->update($data);

        // TODO: Refactor this to listener
        /* @var $questionModel QuestionManager */
        $questionModel = $app['questionnaire.questions.model'];
        $questionModel->setOrUpdateRankingForQuestion($data['questionId']);

        return $app->json($userAnswer, 201);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     * @throws \Exception
     */
    public function explainAction(Request $request, Application $app, User $user)
    {
        $data = $request->request->all();
        $data['userId'] = $user->getId();
        $data['locale'] = $this->getLocale($request, $app['locale.options']['default']);

        $userAnswer = $app['users.answers.model']->explain($data);

        return $app->json($userAnswer);
    }

    /**
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     * @throws \Exception
     */
    public function countAction(Application $app, User $user)
    {
        $userAnswerResult = $app['users.answers.model']->getNumberOfUserAnswers($user->getId());

        $data = array(
            'userId' => $user->getId(),
        );

        foreach ($userAnswerResult as $row) {
            $data['nOfAnswers'] = $row['nOfAnswers'];
        }

        if (empty($data)) {
            return $app->json('The user has not answered to any question', 404);
        }

        return $app->json($data);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     * @throws \Exception
     */
    public function deleteAnswerAction(Request $request, Application $app, User $user)
    {
        $questionId = (integer)$request->attributes->get('questionId');
        $locale = $this->getLocale($request, $app['locale.options']['default']);

        try {
            $userAnswer = $app['users.answers.model']->getUserAnswer($user->getId(), $questionId, $locale);
            $answer = $userAnswer['userAnswer'];
        } catch (NotFoundHttpException $e) {
            return $app->json($e->getMessage(), 404);
        }

        $deletion = $app['users.answers.model']->deleteUserAnswer($user->getId(), $answer);

        if (!$deletion) {
            return $app->json('Answer not deleted', 500);
        }

        /* @var $dispatcher EventDispatcher */
        $dispatcher = $app['dispatcher'];
        $dispatcher->dispatch(\AppEvents::ANSWER_ADDED, new AnswerEvent($user->getId(),$questionId));

        /* @var $questionModel QuestionManager */
        $questionModel = $app['questionnaire.questions.model'];

        try {
            $questionModel->skip($answer['questionId'], $user->getId());
        } catch (\Exception $e) {
            return $app->json($e->getMessage(), 405);
        }

        return $app->json($answer, 200);
    }

    /**
     * @param Request $request
     * @param Application $app
     * @param User $user
     * @return JsonResponse
     * @throws \Exception
     */
    public function getOldUserAnswersCompareAction(Request $request, Application $app, User $user)
    {
        $otherUserId = $request->attributes->get('id');
        $locale = $request->query->get('locale');
        $showOnlyCommon = $request->query->get('showOnlyCommon', 0);

        if (null === $otherUserId || null === $user->getId()) {
            return $app->json(array(), 400);
        }

        /* @var $paginator \Paginator\Paginator */
        $paginator = $app['paginator'];

        $filters = array('id' => $otherUserId, 'id2' => $user->getId(), 'locale' => $locale, 'showOnlyCommon' => $showOnlyCommon);

        /* @var $model \Model\User\Question\OldQuestionComparePaginatedManager */
        $model = $app['old.users.questions.compare.model'];

        try {
            $result = $paginator->paginate($filters, $model, $request);
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
     * @throws \Exception
     */
    public function getUserAnswersCompareAction(Request $request, Application $app, User $user)
    {
        $otherUserId = $request->attributes->get('userId');
        $locale = $request->query->get('locale');
        $showOnlyCommon = $request->query->get('showOnlyCommon', 0);

        if (null === $otherUserId || null === $user->getId()) {
            return $app->json(array(), 400);
        }

        /* @var $paginator \Paginator\Paginator */
        $paginator = $app['paginator'];

        $filters = array('id' => $otherUserId, 'id2' => $user->getId(), 'locale' => $locale, 'showOnlyCommon' => $showOnlyCommon);

        /* @var $model \Model\User\Question\QuestionComparePaginatedManager */
        $model = $app['users.questions.compare.model'];

        try {
            $result = $paginator->paginate($filters, $model, $request);

            foreach ($result['items'] as &$questionData)
            {
                if (empty($question)){
                    continue;
                }
                $question = $questionData['question'];
                $questionData['question'] = $this->setIsRegisterQuestion($question, $user, $app);
            }
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($result, !empty($result) ? 201 : 200);
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

    protected function setIsRegisterQuestion($question, User $user, Application $app)
    {
        $registerModes = isset($question['registerModes']) ? $question['registerModes'] : array();

        if (empty($registerModes)) {
            $question['isRegisterQuestion'] = false;
            unset($question['registerModes']);
            return $question;
        }

        $userId = $user->getId();

        $questionCorrelationManager = $app['users.questionCorrelation.manager'];
        $mode = $questionCorrelationManager->getMode($userId);

        unset($question['registerModes']);
        $question['isRegisterQuestion'] = in_array($mode, $registerModes);

        return $question;
    }
}
