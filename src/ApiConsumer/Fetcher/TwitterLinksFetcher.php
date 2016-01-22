<?php

namespace ApiConsumer\Fetcher;

class TwitterLinksFetcher extends AbstractTweetsFetcher
{
    protected $url = 'statuses/user_timeline.json';

    protected function getQuery()
    {
        return array(
            'count' => $this->pageLength,
            'trim_user' => 'true',
            'exclude_replies' => 'false',
            'contributor_details' => 'false',
            'include_rts' => 'true',
        );
    }
}