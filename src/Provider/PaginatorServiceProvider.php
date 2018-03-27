<?php

namespace Provider;

use Paginator\ContentPaginator;
use Paginator\Paginator;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class PaginatorServiceProvider implements ServiceProviderInterface
{

    /**
     * { @inheritdoc }
     */
    public function register(Container $app)
    {
        $app['paginator'] = function ($app) {
            $paginator = new Paginator();

            return $paginator;
        };

        $app['paginator.content'] = function ($app) {
            $paginator = new ContentPaginator();

            return $paginator;
        };
    }

}
