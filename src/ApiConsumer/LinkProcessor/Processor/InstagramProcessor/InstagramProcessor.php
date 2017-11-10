<?php

namespace ApiConsumer\LinkProcessor\Processor\InstagramProcessor;

use ApiConsumer\Images\ProcessingImage;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\Processor\ScraperProcessor\ScraperProcessor;
use ApiConsumer\LinkProcessor\UrlParser\InstagramUrlParser;
use Model\Link\Image;

class InstagramProcessor extends ScraperProcessor
{
    const INSTAGRAM_LABEL = 'LinkInstagram';

    /**
     * @var InstagramUrlParser
     */
    protected $parser;

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        parent::hydrateLink($preprocessedLink, $data);
        $link = $preprocessedLink->getFirstLink();
        $link->addAdditionalLabels(self::INSTAGRAM_LABEL);
    }

    public function getImages(PreprocessedLink $preprocessedLink, array $data)
    {
        $images = parent::getImages($preprocessedLink, $data);
        if (empty($images)) {
            return array($this->brainBaseUrl . InstagramUrlParser::DEFAULT_IMAGE_PATH);
        }

        return $images;
    }
}