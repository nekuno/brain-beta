<?php

namespace Service\Validator;

class QuestionAdminValidator extends Validator
{
    public function validateOnCreate($data)
    {
        $metadata = $this->metadata;
        $choices = $this->getChoices();
//        $this->validateAnswerTexts($data]);
//        $this->validateQuestionTexts($data['questionTexts']);

//        $this->validateMetadata($data, $metadata, $choices);
    }

//    public function validateOnUpdate($data)
//    {
//        $metadata = $this->metadata;
//        $choices = $this->getChoices();
//        $this->validateMetadata($data, $metadata, $choices);
//    }
//
//    public function validateOnDelete($data)
//    {
//        $errors = array();
//        if (!isset($data['questionId'])) {
//            $errors['questionId'] = 'Question Id is not set when deleting question';
//        }
//
//        $this->throwException($errors);
//    }

    protected function getChoices()
    {
        return array(
            'locale' => array('en', 'es'),
        );
    }

    protected function validateAnswerTexts(array $data)
    {
//        foreach ($data as $key => $value)
//        {
//            if ($nswerIda)
//        }
    }
}