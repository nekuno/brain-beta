<?php

namespace ApiConsumer\LinkProcessor\Processor\TwitterProcessor;

use ApiConsumer\Exception\CannotProcessException;
use ApiConsumer\Exception\UrlChangedException;
use ApiConsumer\Images\ProcessingImage;
use ApiConsumer\LinkProcessor\LinkAnalyzer;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser;

class TwitterTweetProcessor extends AbstractTwitterProcessor
{
    public function requestItem(PreprocessedLink $preprocessedLink)
    {
        $statusId = $this->getItemId($preprocessedLink->getUrl());

        $apiResponse = $this->resourceOwner->requestStatus($statusId);

        $link = $this->extractLinkFromResponse($apiResponse);

        if (isset($link['url'])){
            try {
                $url = LinkAnalyzer::cleanUrl($link['url']);
            } catch (\Exception $e) {}

            if (isset($url) && $url != $preprocessedLink->getUrl()){
                throw new UrlChangedException($preprocessedLink->getUrl(), $link['url']);
            } else {
                return array();
            }
        } else {
            throw new CannotProcessException($preprocessedLink->getUrl(), 'We do not want tweets without url content');
        }
    }

    private function extractLinkFromResponse($apiResponse)
    {
        //if tweet quotes another
        if (isset($apiResponse['quoted_status_id'])) {
            //if tweet is main, API returns quoted_status
            if (isset($apiResponse['quoted_status'])) {

                return $this->extractLinkFromResponse($apiResponse['quoted_status']);

            } else if (isset($apiResponse['is_quote_status']) && $apiResponse['is_quote_status'] == true) {
                return array('id' => $apiResponse['quoted_status_id']);
            } else {
                //should not be able to enter here
            }
        }

        //if tweet includes url or media in text
        if (isset($apiResponse['entities'])) {
            $entities = $apiResponse['entities'];

            $media = $this->getEntityUrl($entities, 'media');
            if ($media) {
                return $media;
            }

            $url = $this->getEntityUrl($entities, 'urls');
            if ($url) {
                return $url;
            }
        }
        //we do not want tweets with no content
        return false;
    }

    private function getEntityUrl($entities, $name)
    {
        if (isset($entities[$name]) && !empty($entities[$name])) {
            $urlObject = $entities[$name][0]; //TODO: Foreach
            return array('url' => $urlObject['expanded_url']);
        }

        return false;
    }

    public function getImages(PreprocessedLink $preprocessedLink, array $data)
    {
        $profileAvatar = null;
        if (isset($data['user'])){
            $default = $this->brainBaseUrl . TwitterUrlParser::DEFAULT_IMAGE_PATH;
            $profileAvatar = isset($data['user']['profile_image_url_https']) ? $data['user']['profile_image_url_https'] : $this->parser->getOriginalProfileUrl($data['user'], $default);
        }

        return array(new ProcessingImage($profileAvatar));
    }

    protected function getItemIdFromParser($url)
    {
        return $this->parser->getStatusId($url);
    }

}