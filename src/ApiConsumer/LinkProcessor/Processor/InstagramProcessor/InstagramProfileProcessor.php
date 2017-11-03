<?php

namespace ApiConsumer\LinkProcessor\Processor\InstagramProcessor;

use ApiConsumer\Images\ProcessingImage;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\UrlParser\InstagramUrlParser;
use Model\Link\Creator;

class InstagramProfileProcessor extends AbstractInstagramProcessor
{

    protected function requestItem(PreprocessedLink $preprocessedLink)
    {
        $token = $preprocessedLink->getToken();

        return $this->resourceOwner->requestProfile($preprocessedLink->getResourceItemId(), $token);
    }

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        parent::hydrateLink($preprocessedLink, $data);
        $link = $preprocessedLink->getFirstLink();
        $data = $data['data'];
        $creator = Creator::buildFromArray($link->toArray());
        $creator->setDescription(isset($data['bio']) ? $data['bio'] : isset($data['full_name']) ? $data['full_name'] : $data['username']);
        $creator->setTitle(isset($data['full_name']) ? $data['full_name'] : $data['username']);
        $preprocessedLink->setFirstLink($creator);
    }

    public function getImages(PreprocessedLink $preprocessedLink, array $data)
    {
        if (!isset($data['data']['profile_picture'])) {
            return array($this->brainBaseUrl . InstagramUrlParser::DEFAULT_IMAGE_PATH);
        }

        $image = new ProcessingImage($data['data']['profile_picture']);

        return array($image);
    }
}