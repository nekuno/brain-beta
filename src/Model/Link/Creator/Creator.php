<?php

namespace Model\Link\Creator;

use Model\Link\Link;

class Creator extends Link
{
    //TODO: Related User/GhostUser ?

    public function toArray()
    {
        $array = parent::toArray();
        $array['additionalLabels'] = array('Creator');
        return $array;
    }
}