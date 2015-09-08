<?php

namespace ApiConsumer\LinkProcessor\Processor;

use GuzzleHttp\Exception\RequestException;
use Http\OAuth\ResourceOwner\FacebookResourceOwner;

class FacebookProcessor implements ProcessorInterface
{
    const FACEBOOK_VIDEO = 'video';
    const FACEBOOK_OTHER = 'other';
    protected $FACEBOOK_VIDEO_TYPES = array('video_inline', 'video_autoplay');

    /**
     * @var $resourceOwner FacebookResourceOwner
     */
    protected $resourceOwner;

    /**
     * @var $scrapperProcessor ScraperProcessor
     */
    protected $scraperProcessor;

    /**
     * @param FacebookResourceOwner $facebookResourceOwner
     * @param ScraperProcessor $scraperProcessor
     */
    public function __construct(FacebookResourceOwner $facebookResourceOwner, ScraperProcessor $scraperProcessor)
    {
        $this->resourceOwner = $facebookResourceOwner;
        $this->scraperProcessor = $scraperProcessor;
    }

    /**
     * @param array $link
     * @return array
     */
    public function process(array $link)
    {
        $type = $this->getAttachmentType($link);
        switch ($type) {
            case $this::FACEBOOK_VIDEO:
                $link = $this->processVideo($link);
                break;
            case $this::FACEBOOK_OTHER:
                $link = $this->scraperProcessor->process($link);
                break;
            default:
                return false;
                break;
        }

        return $link;
    }

    protected function processVideo($link)
    {
        $id = $this->getVideoIdFromURL($link['url']);

        $link['title'] = null;
        $link['additionalLabels'] = array('Video');
        $link['additionalFields'] = array(
            'embed_type' => 'facebook',
            'embed_id' => $id);

        $link = $this->scraperProcessor->process($link);

        try {
            $url = (string)$id;
            $query = array(
                'fields' => 'description,picture'
            );

            $token = $link['resourceOwnerToken'] ?: array();

            $response = $this->resourceOwner->authorizedHTTPRequest($url, $query, $token);
            $link['description'] = $response['description'] ?: null;
            $link['thumbnail'] = $response['picture'] ?: null;

        } catch (RequestException $e) {
        }

        return $link;
    }

    private function getAttachmentType($link)
    {
        if (empty($link['types'])
        ) {
            return null;
        }
        //TODO: Check if there can be more than one attachment in one post
        if (in_array($link['types'][0], $this->FACEBOOK_VIDEO_TYPES)) {
            return $this::FACEBOOK_VIDEO;
        }

        return $this::FACEBOOK_OTHER;

    }

    private function getVideoIdFromURL($url)
    {
        $prefix = 'videos/';
        $startPos = strpos($url, $prefix);
        if ($startPos === false) {
            return null;
        }
        return substr($url, $startPos + strlen($prefix));

    }

}