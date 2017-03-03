<?php

namespace ApiConsumer\LinkProcessor\Processor\YoutubeProcessor;

use ApiConsumer\LinkProcessor\PreprocessedLink;
use Model\User\Token\Token;
use Model\Link\Video;

class YoutubeVideoProcessor extends AbstractYoutubeProcessor
{
    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        parent::hydrateLink($preprocessedLink, $data);

        $link = $preprocessedLink->getFirstLink();
        $itemId = $preprocessedLink->getResourceItemId();

        $link = Video::buildFromLink($link);
        $link->setEmbedId($itemId);
        $link->setEmbedType('youtube');

        $preprocessedLink->setFirstLink($link);
    }

    public function getImages(PreprocessedLink $preprocessedLink, array $data)
    {
        $itemId = $preprocessedLink->getResourceItemId();

        $imageUrls = array();
        foreach ($this->imageResolutions() as $resolution) {
            $imageUrls[] = 'https://img.youtube.com/vi/' . $itemId . '/' . $resolution;
        }

        if (empty($imageUrls)) {
            $imageUrls = array($this->brainBaseUrl . self::DEFAULT_IMAGE_PATH);
        }

        return $imageUrls;
    }

    public function getItemIdFromParser($url)
    {
        return $this->parser->getVideoId($url);
    }

    public function addTags(PreprocessedLink $preprocessedLink, array $item)
    {
        $link = $preprocessedLink->getFirstLink();

        if (isset($item['topicDetails']['topicIds'])) {
            foreach ($item['topicDetails']['topicIds'] as $tagName) {
                $link->addTag(
                    array(
                        'name' => $tagName,
                        'additionalLabels' => array('Freebase'),
                    )
                );
            }
        }
    }

    protected function requestSpecificItem($id, Token $token = null)
    {
        return $this->resourceOwner->requestVideo($id, $token);
    }

    private function imageResolutions()
    {
        return array('default.jpg', 'mqdefault.jpg', 'hqdefault.jpg', 'sddefault.jpg', 'maxresdefault.jpg');
    }
}