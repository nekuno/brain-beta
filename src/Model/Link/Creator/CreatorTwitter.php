<?php

namespace Model\Link\Creator;

class CreatorTwitter extends Creator
{
    public function toArray()
    {
        $array = parent::toArray();
        $array['additionalLabels'][] = 'CreatorTwitter';
        return $array;
    }
}