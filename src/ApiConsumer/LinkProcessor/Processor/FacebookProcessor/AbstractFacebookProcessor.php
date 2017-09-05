<?php

namespace ApiConsumer\LinkProcessor\Processor\FacebookProcessor;

use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\Processor\AbstractAPIProcessor;
use ApiConsumer\LinkProcessor\UrlParser\FacebookUrlParser;
use ApiConsumer\ResourceOwner\FacebookResourceOwner;

abstract class AbstractFacebookProcessor extends AbstractAPIProcessor
{
    const DEFAULT_IMAGE_PATH = 'default_images/facebook.png';

    /**
     * @var FacebookResourceOwner
     */
    protected $resourceOwner;

    /**
     * @var FacebookUrlParser
     */
    protected $parser;

    public function getImages(PreprocessedLink $preprocessedLink, array $data)
    {
        return isset($data['picture']) && isset($data['picture']['data']['url']) ? array($data['picture']['data']['url'])
            : array($this->brainBaseUrl . self::DEFAULT_IMAGE_PATH);
    }

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        $link = $preprocessedLink->getFirstLink();
        $link->setDescription(isset($data['description']) ? $data['description'] : $this->buildDescriptionFromTitle($data));
        $link->setTitle(isset($data['name']) ? $data['name'] : $this->buildTitleFromDescription($data));
    }

    //TODO: Move to Link? Can be done without dependency?
    protected function buildTitleFromDescription(array $response)
    {
        if (!isset($response['description'])){
            return null;
        }
        $description = $response['description'];

        return strlen($description) >= 25 ? mb_substr($description, 0, 22) . '...' : $description;
    }

    protected function buildDescriptionFromTitle(array $response)
    {
        return isset($response['name'])? $response['name'] : null;
    }

    protected function isValidResponse(array $response)
    {
        $isError = isset($response['error']);

        return !$isError && parent::isValidResponse($response);
    }
}