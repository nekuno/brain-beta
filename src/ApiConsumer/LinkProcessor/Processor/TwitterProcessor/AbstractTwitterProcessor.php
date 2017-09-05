<?php

namespace ApiConsumer\LinkProcessor\Processor\TwitterProcessor;

use ApiConsumer\LinkProcessor\Processor\AbstractAPIProcessor;
use ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser;
use ApiConsumer\ResourceOwner\TwitterResourceOwner;

abstract class AbstractTwitterProcessor extends AbstractAPIProcessor
{
    const DEFAULT_IMAGE_PATH = 'default_images/twitter.png';

    /** @var  TwitterUrlParser */
    protected $parser;

    /** @var  TwitterResourceOwner */
    protected $resourceOwner;
}