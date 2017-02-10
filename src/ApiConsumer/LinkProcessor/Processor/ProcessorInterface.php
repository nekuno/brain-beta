<?php

namespace ApiConsumer\LinkProcessor\Processor;

use ApiConsumer\Exception\CannotProcessException;
use ApiConsumer\Exception\UrlChangedException;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\SynonymousParameters;
use ApiConsumer\ResourceOwner\AbstractResourceOwnerTrait;
use Symfony\Component\DomCrawler\Crawler;

interface ProcessorInterface
{
    /** @return AbstractResourceOwnerTrait */
    public function getResourceOwner();

    /**
     * @param PreprocessedLink $preprocessedLink
     * @return array|Crawler
     * @throws CannotProcessException|UrlChangedException
     */
    public function getResponse(PreprocessedLink $preprocessedLink);

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data);

    public function addTags(PreprocessedLink $preprocessedLink, array $data);

    public function getSynonymousParameters(PreprocessedLink $preprocessedLink, array $data);

    /**
     * @param PreprocessedLink $preprocessedLink
     * @param array $data
     * @return array
     */
    public function getImages(PreprocessedLink $preprocessedLink, array $data);

} 