<?php

namespace ApiConsumer\Fetcher;

use ApiConsumer\LinkProcessor\PreprocessedLink;
use Model\Creator;

class TwitterFollowingFetcher extends AbstractTwitterFetcher
{
    protected $url = 'friends/ids.json';

    protected $paginationField = 'cursor';

    protected $pageLength = 5000;

    protected function getQuery($paginationId = null)
    {
        return array_merge(
            parent::getQuery($paginationId),
            array(
                'count' => $this->pageLength,
            )
        );
    }

    protected function getItemsFromResponse($response)
    {
        return isset($response['ids']) ? $response['ids'] : array();

    }

    protected function getPaginationIdFromResponse($response)
    {
        $paginationId = isset($response['next_cursor']) ? $response['next_cursor'] : null;
        if ($paginationId == 0) {
            return null;
        }

        return $paginationId;
    }

    /**
     * @inheritdoc
     */
    protected function parseLinks(array $rawFeed)
    {
        $lookups = $this->resourceOwner->lookupUsersBy('user_id', $rawFeed, $this->token);

        $links = array();
        foreach ($lookups as $lookupResponse){
            $links = array_merge($links, $this->resourceOwner->buildProfilesFromLookup($lookupResponse));
        }

        $preprocessedLinks = array();
        if ($links == false || empty($links)) {
            foreach ($rawFeed as $id) {
                $link = array(
                    'url' => 'https://twitter.com/intent/user?user_id=' . $id,
                    'title' => null,
                    'description' => null,
                    'timestamp' => 1000 * time(),
                );
                $preprocessedLink = new PreprocessedLink($link['url']);
                $preprocessedLink->setFirstLink(Creator::buildFromArray($link));
                $preprocessedLink->setSource($this->resourceOwner->getName());
                $preprocessedLink->setResourceItemId($id);
                $preprocessedLinks[] = $preprocessedLink;
            }
        } else {
            foreach ($links as &$link) {
//                $screenName = $link['screen_name'];
//                $link = $this->resourceOwner->buildProfileFromLookup($link);
//                $link['processed'] = 1;
//                $this->resourceOwner->dispatchChannel(
//                    array(
//                        'url' => $link['url'],
//                        'username' => $screenName,
//                    )
//                );
                $preprocessedLink = new PreprocessedLink($link['url']);
                $preprocessedLink->setFirstLink(Creator::buildFromArray($link));
                $preprocessedLinks[] = $preprocessedLink;
            }
        }

        return $preprocessedLinks;
    }

}