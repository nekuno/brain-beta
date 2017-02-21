<?php

namespace ApiConsumer\LinkProcessor\Processor\YoutubeProcessor;

use ApiConsumer\LinkProcessor\PreprocessedLink;
use Model\User\Token\Token;

class YoutubeChannelProcessor extends AbstractYoutubeProcessor
{
    function getItemIdFromParser($url)
    {
        return $this->parser->getChannelId($url);
    }

    function addTags(PreprocessedLink $preprocessedLink, array $item)
    {
        $link = $preprocessedLink->getFirstLink();

        if (isset($item['brandingSettings']['channel']['keywords'])) {
            $tags = $item['brandingSettings']['channel']['keywords'];
            preg_match_all('/".*?"|\w+/', $tags, $results);
            if ($results) {
                foreach ($results[0] as $tagName) {
                    $link->addTag(array(
                        'name' => $tagName,
                    ));
                }
            }
        }
    }

    protected function requestSpecificItem($id, Token $token = null)
    {
        return $this->resourceOwner->requestChannel($id, $token);
    }
}