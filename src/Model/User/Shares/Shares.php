<?php

namespace Model\User\Shares;

class Shares implements \JsonSerializable
{
    protected $id;

    protected $topLinks = array();

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getTopLinks()
    {
        return $this->topLinks;
    }

    /**
     * @param mixed $topLinks
     */
    public function setTopLinks($topLinks)
    {
        $this->topLinks = $topLinks;
    }

    public function addTopLink($topLink)
    {
        $this->topLinks[] = $topLink;
    }

    public function toArray()
    {
        return array(
            'topLinks' => $this->topLinks,
        );
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }
}