<?php

namespace ApiConsumer\Fetcher;

use ApiConsumer\LinkProcessor\PreprocessedLink;

interface FetcherInterface
{

    /**
     * Fetch links using user authorization
     *
     * @param $user
     * @param boolean $public
     * @return PreprocessedLink[]
     */
    public function fetchLinksFromUserFeed($user, $public);

    /**
     * Fetch links using client authorization
     * @param string $username
     * @return PreprocessedLink[]
     */
    public function fetchAsClient($username);
}