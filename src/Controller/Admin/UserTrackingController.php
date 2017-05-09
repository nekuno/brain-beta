<?php

namespace Controller\Admin;

use Model\User\RelationsModel;
use Model\User\RelationsPaginatedModel;
use Model\User\UserTrackingModel;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class UserTrackingController
{
    public function getAllAction(Application $app)
    {
        /* @var $model UserTrackingModel */
        $model = $app['users.tracking.model'];
        $result = $model->getAll();

        return $app->json($result);
    }

    public function getAction(Application $app, $id)
    {
        /* @var $model UserTrackingModel */
        $model = $app['users.tracking.model'];

        $result = $model->get($id);

        return $app->json($result);
    }

    public function getCsvAction(Application $app)
    {
        $array = $app['users.tracking.model']->getUsersDataForCsv();
        $this->downloadSendHeaders("data_export_" . date("Y-m-d") . ".csv");
        return $this->array2csv($array);
    }

    public function getReportedAction(Request $request, Application $app)
    {
        $from = $request->get('from', null);
        $to = $request->get('to', null);
        $filters = array(
            'relation' => RelationsModel::REPORTS,
            'from' => $from,
            'to' => $to
        );

        /* @var $paginator \Paginator\Paginator */
        $paginator = $app['paginator'];
        /* @var $model RelationsPaginatedModel */
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

    private function array2csv(array &$array)
    {
        if (count($array) == 0) {
            return null;
        }
        ob_start();
        $df = fopen("php://output", 'w');
        fputcsv($df, array_keys(reset($array)));
        foreach ($array as $row) {
            fputcsv($df, $row);
        }
        fclose($df);
        return ob_get_clean();
    }

    private function downloadSendHeaders($filename) {
        // disable caching
        $now = gmdate("D, d M Y H:i:s");
        header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
        header("Last-Modified: {$now} GMT");

        // force download
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");

        // disposition / encoding on response body
        header("Content-Disposition: attachment;filename={$filename}");
        header("Content-Transfer-Encoding: binary");
    }
}