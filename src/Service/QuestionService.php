<?php

namespace Service;

use Model\User\Question\QuestionModel;

class QuestionService
{
    /**
     * @var QuestionModel
     */
    protected $questionModel;

    /**
     * @param QuestionModel $questionModel
     */
    public function __construct(QuestionModel $questionModel)
    {
        $this->questionModel = $questionModel;
    }

    public function getQuestions($locale, $skip = null, $limit = null)
    {
        return $this->questionModel->getAll($locale, $skip, $limit);
    }

    public function createQuestion(array $data)
    {
        return $this->questionModel->create($data);
    }

    public function updateQuestion(array $data)
    {
        return $this->questionModel->update($data);
    }

    public function deleteQuestion($questionId)
    {
        if (null === $questionId){
            return false;
        }

        $data = array('questionId' => $questionId);
        return $this->questionModel->delete($data);
    }
}