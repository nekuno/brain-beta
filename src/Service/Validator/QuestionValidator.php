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

    public function validateOnDelete($data)
    {
        $errors = array();
        if (!isset($data['questionId'])) {
            $errors['questionId'] = 'Question Id is not set when deleting question';
        }

        $this->throwException($errors);
    }

    protected function getChoices()
    {
        return array(
            'locale' => array('en', 'es'),
        );
    }
}