<?php

namespace ApiConsumer\LinkProcessor\Processor\InstagramProcessor;

use ApiConsumer\Images\ProcessingImage;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\Processor\ScraperProcessor\ScraperProcessor;
use ApiConsumer\LinkProcessor\UrlParser\InstagramUrlParser;
use Model\Link\Creator;

class InstagramProfileProcessor extends InstagramProcessor
{
    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        parent::hydrateLink($preprocessedLink, $data);
        $link = $preprocessedLink->getFirstLink();
        $creator = Creator::buildFromArray($link->toArray());
        $preprocessedLink->setFirstLink($creator);
    }
}