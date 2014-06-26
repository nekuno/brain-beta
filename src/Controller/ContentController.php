<?php
/**
 * Created by PhpStorm.
 * User: adridev
 * Date: 6/17/14
 * Time: 6:39 PM
 */

namespace Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class ContentController
{

    public function addLink(Request $request, Application $app)
    {

        $data = $request->request->all();

        try {
            $model  = $app['content.model'];
            $result = $model->addLink($data);
        } catch (\Exception $e) {
            if ($app['env'] == 'dev') {
                throw $e;
            }

            return $app->json(array(), 500);
        }

        return $app->json($result, empty($result) ? 200 : 201);

    }

    public function fetchLinksAction(Request $request, Application $app)
    {

        $userId = $request->get('userId');
        $resource = $request->get('resource');
        $FQNClassName = '\\Social\\Consumer\\' . ucfirst($resource) . 'FeedConsumer';

        $consumer = new $FQNClassName($app);

        try {
            $result = $consumer->fetchLinks($userId);
        } catch(\Exception $e) {
            if($app['env'] == 'dev'){
                throw $e;
            }
            return $app->json(array(), 500);
        }

        return $app->json($result);

    }

}