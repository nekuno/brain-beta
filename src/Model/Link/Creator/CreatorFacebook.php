<?php

namespace Model\Link\Creator;

class CreatorFacebook extends Creator
{
    public function toArray()
    {
        $array = parent::toArray();
        $array['additionalLabels'][] = 'CreatorFacebook';
        return $array;
    }
}