<?php

namespace Controller;

use Silex\Application;

/**
 * Class ClientController
 *
 * @package Controller
 */
class ClientController
{
    public function getBlogFeedAction(Application $app)
    {
        $client = $app['guzzle.client'];
        $blogFeed = $client->get('http://blog.nekuno.com/feed/');

        return $blogFeed->getBody();
    }
}
