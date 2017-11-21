<?php

namespace ApiConsumer\LinkProcessor\Processor\TumblrProcessor;

use ApiConsumer\Images\ProcessingImage;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use Model\Link\Link;

class TumblrLinkProcessor extends TumblrPostProcessor
{

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        parent::hydrateLink($preprocessedLink, $data);
        $link = $preprocessedLink->getFirstLink();
        $newLink = Link::buildFromArray($link->toArray());
        $newLink->setTitle($data['title']);
        $newLink->setDescription($data['description'] ?: $data['excerpt']);

        $preprocessedLink->setFirstLink($newLink);
    }

    public function getImages(PreprocessedLink $preprocessedLink, array $data)
    {
        $images = array();
        if (isset($data['photos'])) {
            foreach ($data['photos'] as $photo) {
                $originalPhoto = isset($photo['original_size']) ? $photo['original_size'] : null;
                if (isset($originalPhoto['url'])) {
                    $images[] = $this->buildSquareImage($originalPhoto['url'], ProcessingImage::LABEL_LARGE, $originalPhoto['width']);
                }
                $smallerPhotos = isset($photo['alt_sizes']) ? $photo['alt_sizes'] : array();
                foreach ($smallerPhotos as $smallerPhoto) {
                    if (isset($smallerPhoto['url'])) {
                        $sizeLabel = $smallerPhoto['width'] >= 128 ? ProcessingImage::LABEL_MEDIUM : ProcessingImage::LABEL_SMALL;
                        $images[] = $this->buildSquareImage($smallerPhoto['url'], $sizeLabel, $smallerPhoto['width']);
                        if (count($images) >= 3) {
                            break;
                        }
                    }
                }

            }

            if (count($images) > 0) {
                return $images;
            }
        }

        return parent::getImages($preprocessedLink, $data);
    }

}