<?php

namespace ApiConsumer\LinkProcessor\Processor\InstagramProcessor;

use ApiConsumer\Images\ProcessingImage;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\Processor\AbstractAPIProcessor;
use ApiConsumer\LinkProcessor\UrlParser\InstagramUrlParser;
use ApiConsumer\ResourceOwner\InstagramResourceOwner;

abstract class AbstractInstagramProcessor extends AbstractAPIProcessor
{
    const INSTAGRAM_LABEL = 'LinkInstagram';
    /**
     * @var InstagramUrlParser
     */
    protected $parser;

    /**
     * @var InstagramResourceOwner
     */
    protected $resourceOwner;

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        $link = $preprocessedLink->getFirstLink();
        $link->addAdditionalLabels(self::INSTAGRAM_LABEL);
    }

    public function getImages(PreprocessedLink $preprocessedLink, array $data)
    {
        if (!isset($data['images']['standard_resolution']['url'])) {
            return array($this->brainBaseUrl . InstagramUrlParser::DEFAULT_IMAGE_PATH);
        }

        $imageData = $data['images']['standard_resolution'];
        $image = new ProcessingImage($imageData['url']);
        $image->setWidth($imageData['width']);
        $image->setHeight($imageData['height']);
        $images = array($image);

        return $images;
    }
}