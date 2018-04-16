<?php

namespace Controller\Admin;

use Model\Content\ContentReportManager;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class ContentController
{
    public function getReportedAction(Request $request, Application $app)
    {
        $id = $request->get('id', null);
        $type = $request->get('type', array());
        $disabled = $request->get('disabled', null);
        $order = $request->get('order', null);
        $orderDir = $request->get('orderDir', null);
        $filters = array(
            'id' => $id,
            'disabled' => $disabled,
            'order' => $order,
            'orderDir' => $orderDir,
        );
        foreach ($type as $singleType) {
            if (!empty($singleType)) {
                $filters['type'][] = urldecode($singleType);
            }
        }
        /* @var $paginator \Paginator\Paginator */
        $paginator = $app['paginator'];
        /* @var $model ContentReportManager */
        $model = $app['users.content.report.model'];
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

    public function getReportedByIdAction(Application $app, $id)
    {
        /* @var $model ContentReportManager */
        $model = $app['users.content.report.model'];

        $result = $model->getById($id);

        return $app->json($result);
    }

    public function disableAction(Application $app, $id)
    {
        /* @var $model ContentReportManager */
        $model = $app['users.content.report.model'];

        $result = $model->disable($id);

        return $app->json($result);
    }

    public function enableAction(Application $app, $id)
    {
        /* @var $model ContentReportManager */
        $model = $app['users.content.report.model'];

        $result = $model->enable($id);

        return $app->json($result);
    }
}