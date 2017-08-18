<?php

namespace Service\Validator;

use Model\User\Question\Answer;

class AnswerValidator extends Validator
{
    public function validateOnCreate($data)
    {
        $this->validate($data);
    }

    public function validateOnUpdate($data)
    {
        $this->validateExpired($data);
        $this->validate($data);
    }

    protected function validate($data)
    {
        $this->validateUserInData($data);

        foreach ($data['acceptedAnswers'] as $acceptedAnswer) {
            if (!is_int($acceptedAnswer)) {
                $this->throwException(array('acceptedAnswers' => 'acceptedAnswers items must be integers'));
            }
            $this->validateAnswerId($data['questionId'], $acceptedAnswer);
        }

        $metadata = $this->metadata;
        return $this->validateMetadata($data, $metadata);
    }

    public function validateExpired($data)
    {
        /** @var Answer $answer */
        $answer = $data['userAnswer'];
        if (!$answer->isEditable()) {
            $this->throwException(array('answer' => sprintf('This answer cannot be edited now. Please wait %s seconds', $answer->getEditableIn())));
        }
    }

    protected function validateAnswerId($questionId, $answerId, $desired = true)
    {
        $errors = array('answerId' => $this->existenceValidator->validateAnswerId($questionId, $answerId, $desired));

        $this->throwException($errors);
    }
}