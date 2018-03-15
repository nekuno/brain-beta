<?php

namespace Service;

use Model\Affinity\AffinityManager;
use Model\Link\LinkManager;
use Model\Popularity\PopularityManager;

class LinkService
{
    protected $linkModel;
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
        $this->linkModel = $linkModel;
        $this->popularityManager = $popularityManager;
        $this->affinityManager = $affinityManager;
    }

    public function deleteNotLiked(array $links)
    {
        $notLiked = array_filter($links, function($link){
            return $link['likes'] == 0;
        });

        foreach ($notLiked as $link)
        {
            $url = $link['url'];
            $this->popularityManager->deleteOneByUrl($url);
            $this->linkModel->removeLink($url);
        }
    }

    /**
     * @param string $userId
     * @return array of links
     * @throws \Exception on failure
     */
    public function findAffineLinks($userId)
    {
        return $this->affinityManager->getAffineLinks($userId);
    }

}