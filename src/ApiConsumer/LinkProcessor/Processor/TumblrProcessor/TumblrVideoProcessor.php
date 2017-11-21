<?php

namespace ApiConsumer\LinkProcessor\Processor\TumblrProcessor;

use ApiConsumer\LinkProcessor\PreprocessedLink;
use Model\Link\Link;
use Model\Link\Video;

class TumblrVideoProcessor extends TumblrPostProcessor
{

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        parent::hydrateLink($preprocessedLink, $data);
        $link = $preprocessedLink->getFirstLink();
        $video = $this->completeLink($link, $data);

        $preprocessedLink->setFirstLink($video);
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

        $newLink = Video::buildFromArray($link->toArray());
        if ($caption) {
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
        }

        $newLink->setEmbedType('tumblr');
        $newLink->setEmbedId($data['player'][0]['embed_code']);

        return $newLink;
    }
}