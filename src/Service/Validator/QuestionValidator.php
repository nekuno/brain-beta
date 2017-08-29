<?php

namespace Service\Validator;

class QuestionValidator extends Validator
{
    public function validateOnCreate($data)
    {
        $metadata = $this->metadata;
        $choices = $this->getChoices();
        $this->validateMetadata($data, $metadata, $choices);

        $this->validateUserInData($data, true);
    }

    public function validateOnUpdate($data)
    {
        $metadata = $this->metadata;
        $choices = $this->getChoices();
        $this->validateMetadata($data, $metadata, $choices);

        $this->validateUserInData($data, false);
    }

    protected function getChoices()
    {
        return array(
            'locale' => array('en', 'es'),
        );
    }
}