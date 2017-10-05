<?php

namespace Controller\Admin;

use Service\QuestionService;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class QuestionController
{
    public function getQuestionsAction(Request $request, Application $app)
    {
        $locale = $request->get('locale');
        $offset = $request->get('offset', 0);
        $limit = $request->get('limit', 20);

        /** @var QuestionService $questionService */
        $questionService = $app['question.service'];

        $questions = $questionService->getQuestions($locale, $offset, $limit);

        return $app->json($questions);
    }

    public function createQuestionAction(Request $request, Application $app)
    {
        $data = $request->request->all();

        /** @var QuestionService $questionService */
        $questionService = $app['question.service'];

        $created = $questionService->createQuestion($data);

        return $app->json($created);
    }

    public function updateQuestionAction(Request $request, Application $app, $questionId)
    {
        $data = $request->request->all();
        $data['questionId'] = $questionId;

        /** @var QuestionService $questionService */
        $questionService = $app['question.service'];

        $updated = $questionService->updateQuestion($data);

        return $app->json($updated);
    }

    public function deleteQuestionAction(Request $request, Application $app, $questionId)
    {
        /** @var QuestionService $questionService */
        $questionService = $app['question.service'];

        $deleted = $questionService->deleteQuestion($questionId);
        $code = $deleted ? 201 : 404;

        return $app->json($deleted, $code);
    }
}