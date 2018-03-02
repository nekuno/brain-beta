<?php

namespace Service;

use Model\Link\LinkModel;
use Model\Popularity\PopularityManager;

class LinkService
{
    protected $linkModel;
    protected $popularityManager;

    /**
     * LinkService constructor.
     * @param $linkModel
     * @param $popularityManager
     */
    public function __construct(LinkModel $linkModel, PopularityManager $popularityManager)
    {
        $this->linkModel = $linkModel;
        $this->popularityManager = $popularityManager;
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

}