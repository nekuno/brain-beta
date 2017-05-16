<?php

namespace Controller\Admin;

use Model\User\UserTrackingModel;
use Silex\Application;

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