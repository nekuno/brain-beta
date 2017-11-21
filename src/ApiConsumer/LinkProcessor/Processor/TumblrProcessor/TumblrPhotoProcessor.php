<?php

namespace ApiConsumer\LinkProcessor\Processor\TumblrProcessor;

use ApiConsumer\Images\ProcessingImage;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use Model\Link\Image;
use Model\Link\Link;

class TumblrPhotoProcessor extends TumblrPostProcessor
{

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        parent::hydrateLink($preprocessedLink, $data);
        $link = $preprocessedLink->getFirstLink();
        $newLink = $this->completeLink($link, $data);

        $preprocessedLink->setFirstLink($newLink);
    }

    public function getImages(PreprocessedLink $preprocessedLink, array $data)
    {
        $images = array();
        if (isset($data['photos'])) {
            foreach ($data['photos'] as $photo) {
                $thumbnails = isset($photo['alt_sizes']) ? $photo['alt_sizes'] : array();
                foreach ($thumbnails as $thumbnail) {
                    if (isset($thumbnail['url'])) {
                        $sizeLabel = $thumbnail['width'] >= 512 ? ProcessingImage::LABEL_LARGE :
                            $thumbnail['width'] >= 128 ? ProcessingImage::LABEL_MEDIUM : ProcessingImage::LABEL_SMALL;
                        $images[] = $this->buildSquareImage($thumbnail['url'], $sizeLabel, $thumbnail['width']);
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

    private function completeLink(Link $link, $data)
    {
        if (isset($data['summary']) && $data['summary']) {
            $caption = $data['summary'];
        } elseif (isset($data['caption']) && $data['caption']) {
            $caption = strip_tags($data['caption']);
        } else {
            $caption = null;
        }

        if ($caption) {
            $newLink = Link::buildFromArray($link->toArray());
            if ($newLinePos = strpos($caption, "\n")) {
                $title = substr($caption, 0, $newLinePos);
                $description = strlen($caption) > $newLinePos + 1 ? substr($caption, $newLinePos + 1) : null;
            } elseif ($dotPos = strpos($caption, '.')) {
                $title = substr($caption, 0, $dotPos);
                $description = strlen($caption) > $dotPos + 1 ? substr($caption, $dotPos + 1) : null;
            } else {
                $title = $caption;
                $description = null;
            }
            $newLink->setTitle($title);
            $newLink->setDescription($description);
        } else {
            $newLink = Image::buildFromArray($link->toArray());
        }

        return $newLink;
    }
}