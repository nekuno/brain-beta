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
        $filters = array('locale' => $locale);

        $paginator = $app['paginator'];
        $model = $app['users.questions.paginated.model'];

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
//        $data = $request->query->all();
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

        $updated = $questionService->updateMultilanguage($data);

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