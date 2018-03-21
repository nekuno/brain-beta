<?php

namespace Service;

use Model\Affinity\AffinityManager;
use Model\Link\Link;
use Model\Link\LinkManager;
use Model\Popularity\PopularityManager;

class LinkService
{
    protected $linkManager;
    protected $popularityManager;
    protected $affinityManager;

    /**
     * LinkService constructor.
     * @param LinkManager $linkModel
     * @param PopularityManager $popularityManager
     * @param AffinityManager $affinityManager
     */
    public function __construct(LinkManager $linkModel, PopularityManager $popularityManager, AffinityManager $affinityManager)
    {
        $this->linkManager = $linkModel;
        $this->popularityManager = $popularityManager;
        $this->affinityManager = $affinityManager;
    }

    public function deleteNotLiked(array $linkUrls)
    {
        $notLiked = array_filter($linkUrls, function($linkUrl){
            return $linkUrl['likes'] == 0;
        });

        foreach ($notLiked as $linkUrl)
        {
            $url = $linkUrl['url'];
            $this->popularityManager->deleteOneByUrl($url);
            $this->linkManager->removeLink($url);
        }
    }

    /**
     * @param string $userId
     * @return Link[]
     * @throws \Exception on failure
     */
    public function findAffineLinks($userId)
    {
        $linkNodes = $this->affinityManager->getAffineLinks($userId);

        $links = array();
        foreach ($linkNodes as $node)
        {
            $linkArray = $this->linkManager->buildLink($node);
            $link = $this->linkManager->buildLinkObject($linkArray);

            $links[] = $link;
        }

        return $links;
    }

}