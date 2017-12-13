<?php

namespace Controller\Admin;

use Service\QuestionService;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class QuestionController
{
    public function getQuestionsAction(Request $request, Application $app)
    {
        $locale = $request->get('locale', 'es');
        $order = $request->get('order', null);
        $orderDir = $request->get('orderDir', null);
        $filters = array(
            'locale' => $locale,
            'order' => $order,
            'orderDir' => $orderDir,
        );

        $paginator = $app['paginator'];
        $model = $app['admin.questions.paginated.model'];

        $result = $paginator->paginate($filters, $model, $request);

        return $app->json($result);
    }

    public function getQuestionAction(Request $request, Application $app, $questionId)
    {
        /** @var QuestionService $questionService */
        $questionService = $app['question.service'];
        $question = $questionService->getOneMultilanguage($questionId);

        return $app->json($question);
    }

    public function createQuestionAction(Request $request, Application $app)
    {
        $data = $request->request->all();

        /** @var QuestionService $questionService */
        $questionService = $app['question.service'];

        $created = $questionService->createQuestion($data);

        return $app->json($created, 201);
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