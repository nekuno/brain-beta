<?php

namespace Controller\Admin;

use Model\User\RelationsManager;
use Model\User\RelationsPaginatedManager;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class UserReportController
{
    public function getReportedAction(Request $request, Application $app)
    {
        $from = $request->get('from', null);
        $to = $request->get('to', null);
        $filters = array(
            'relation' => RelationsManager::REPORTS,
            'from' => $from,
            'to' => $to
        );

        /* @var $paginator \Paginator\Paginator */
        $paginator = $app['paginator'];
        /* @var $model RelationsPaginatedManager */
        $model = $app['users.relations.paginated.model'];

        try {
            $result = $paginator->paginate($filters, $model, $request);
            $result['totals'] = $model->countTotal($filters);
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($result, !empty($result) ? 201 : 200);
    }

    public function getDisabledAction(Request $request, Application $app)
    {
        $filters = array();

        /* @var $paginator \Paginator\Paginator */
        $paginator = $app['paginator'];
        /* @var $model RelationsPaginatedManager */
        $model = $app['users.disabled.paginated.model'];

        try {
            $result = $paginator->paginate($filters, $model, $request);
            $result['totals'] = $model->countTotal($filters);
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($result, !empty($result) ? 201 : 200);
    }

    public function getDisabledByIdAction($id, Application $app)
    {
        $model = $app['users.disabled.paginated.model'];
        $result = $model->getById($id);

        return $app->json($result);
    }

    public function enableAction(Application $app, $id)
    {
        return $this->setEnabled($app, $id, true);
    }

    public function disableAction(Application $app, $id)
    {
        return $this->setEnabled($app, $id, false);
    }

    protected function setEnabled(Application $app, $id, $enabled)
    {
        $userManager = $app['users.manager'];

        $enabledSet = $userManager->setEnabled($id, $enabled, true);
        $canReenableSet = $userManager->setCanReenable($id, $enabled);

        return $app->json($enabledSet && $canReenableSet);
    }
}