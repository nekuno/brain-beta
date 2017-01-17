<?php

namespace ApiConsumer\Fetcher;

use ApiConsumer\ResourceOwner\AbstractResourceOwnerTrait;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwnerInterface;

abstract class AbstractFetcher implements FetcherInterface
{
    /**
     * @var string
     */
    protected $url = '';

    /**
     * @var array
     */
    protected $rawFeed = array();

    /**
     * @var ResourceOwnerInterface|AbstractResourceOwnerTrait
     */
    protected $resourceOwner;

    /**
     * @var array
     */
    protected $token;

    /**
     * @param ResourceOwnerInterface $resourceOwner
     */
    public function __construct(ResourceOwnerInterface $resourceOwner)
    {
        $this->resourceOwner = $resourceOwner;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Get query
     *
     * @return array
     */
    protected function getQuery()
    {
        return array();
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * {@inheritDoc}
     */
    abstract public function fetchLinksFromUserFeed($token);
}
