<?php

namespace Model\Link\Creator;

class CreatorInstagram extends Creator
{
    public function toArray()
    {
        $array = parent::toArray();
        $array['additionalLabels'][] = 'CreatorInstagram';
        return $array;
    }
}