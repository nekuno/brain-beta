<?php

namespace ApiConsumer\Fetcher;

use ApiConsumer\LinkProcessor\PreprocessedLink;

interface FetcherInterface
{

    /**
     * Fetch links using user authorization
     *
     * @param $token
     * @return PreprocessedLink[]
     */
    public function fetchLinksFromUserFeed($token);

    /**
     * Fetch links using client authorization
     * @param string $username
     * @return PreprocessedLink[]
     */
    public function fetchAsClient($username);
}