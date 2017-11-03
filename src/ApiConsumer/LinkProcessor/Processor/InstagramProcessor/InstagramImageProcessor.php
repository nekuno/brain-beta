<?php

namespace ApiConsumer\LinkProcessor\Processor\InstagramProcessor;

use ApiConsumer\Images\ProcessingImage;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\UrlParser\InstagramUrlParser;
use Model\Link\Image;

class InstagramImageProcessor extends AbstractInstagramProcessor
{

    protected function requestItem(PreprocessedLink $preprocessedLink)
    {
        $token = $preprocessedLink->getToken();

        return $this->resourceOwner->requestMedia($preprocessedLink->getResourceItemId(), $token);
    }

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        parent::hydrateLink($preprocessedLink, $data);
        $link = $preprocessedLink->getFirstLink();
        $image = Image::buildFromArray($link->toArray());
        $preprocessedLink->setFirstLink($image);
    }

    public function getImages(PreprocessedLink $preprocessedLink, array $data)
    {
        if (!isset($data['data']['images']['standard_resolution']['url'])) {
            return array($this->brainBaseUrl . InstagramUrlParser::DEFAULT_IMAGE_PATH);
        }

        $imageData = $data['data']['images']['standard_resolution'];
        $image = new ProcessingImage($imageData['url']);
        $image->setWidth($imageData['width']);
        $image->setHeight($imageData['height']);
        $images = array($image);

        return $images;
    }
}