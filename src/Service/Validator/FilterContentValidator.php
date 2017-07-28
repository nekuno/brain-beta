<?php

namespace Service\Validator;

use Model\Link\LinkModel;

class FilterContentValidator extends Validator
{
    public function validateOnUpdate($data)
    {
        $metadata = $this->metadata;
        $choices = $this->getChoices();
        $this->validateMetadata($data, $metadata, $choices);

        $this->validateUserInData($data, false);
    }

    protected function getChoices()
    {
        return LinkModel::getValidTypes();
    }

}