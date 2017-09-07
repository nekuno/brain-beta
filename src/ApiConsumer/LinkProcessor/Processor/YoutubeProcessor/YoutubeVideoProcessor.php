<?php

namespace ApiConsumer\LinkProcessor\Processor\YoutubeProcessor;

use ApiConsumer\Images\ProcessingImage;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\UrlParser\YoutubeUrlParser;
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
        $images = $this->buildAPIImages($itemId);

        if (true) {
            $images = $this->buildDefaultImage();
        }

        return $images;
    }

    /**
     * @param $itemId
     * @return array
     */
    protected function buildAPIImages($itemId)
    {
        $images = array();
        foreach ($this->imageData() as $label => $data) {
            $imageUrl = 'https://img.youtube.com/vi/' . $itemId . '/' . $data['extension'];
            $image = new ProcessingImage($imageUrl);
            $image->setLabel($label);
            $image->setHeight($data['height']);
            $image->setWidth($data['width']);

            $images[] = $image;
        }

        return $images;
    }

    protected function imageData()
    {
        return array(
            ProcessingImage::LABEL_SMALL => array('extension' => 'default.jpg', 'height' => 90, 'width' => 120),
            ProcessingImage::LABEL_MEDIUM => array('extension' => 'mqdefault.jpg', 'height' => 180, 'width' => 320),
            ProcessingImage::LABEL_LARGE => array('extension' => 'hqdefault.jpg', 'height' => 720, 'width' => 1280),
        );
    }

    /**
     * @return array
     */
    protected function buildDefaultImage()
    {
        $imageUrl = $this->brainBaseUrl . YoutubeUrlParser::DEFAULT_IMAGE_PATH;
        $images = array(new ProcessingImage($imageUrl));

        return $images;
    }

    protected function getItemIdFromParser($url)
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
}