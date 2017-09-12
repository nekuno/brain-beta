<?php

namespace ApiConsumer\LinkProcessor\Processor\TwitterProcessor;

use ApiConsumer\LinkProcessor\Processor\AbstractAPIProcessor;
use ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser;
use ApiConsumer\ResourceOwner\TwitterResourceOwner;

abstract class AbstractTwitterProcessor extends AbstractAPIProcessor
{
    /** @var  TwitterUrlParser */
    protected $parser;

    /** @var  TwitterResourceOwner */
    protected $resourceOwner;
}