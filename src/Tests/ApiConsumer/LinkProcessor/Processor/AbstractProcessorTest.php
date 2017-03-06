<?php

namespace Tests\ApiConsumer\LinkProcessor\Processor;

abstract class AbstractProcessorTest extends \PHPUnit_Framework_TestCase
{
    protected $brainBaseUrl;

    public function createApplication()
    {
        $app = require __DIR__ . '/../../../../app.php';
        $app['debug'] = true;
        unset($app['exception_handler']);
        $app['session.test'] = true;
        $this->brainBaseUrl = $app['brain_base_url'];

        return $app;
    }
}
