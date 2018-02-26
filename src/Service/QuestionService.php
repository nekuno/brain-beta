<?php

namespace Service;

use Model\User\Question\Admin\QuestionAdminDataFormatter;
use Model\User\Question\Admin\QuestionAdminManager;
use Model\User\Question\QuestionCategory\QuestionCategoryManager;
use Model\User\Question\QuestionCorrelationManager;
use Model\User\Question\QuestionModel;
use Model\User\Question\QuestionNextSelector;

class QuestionService
{
    /**
     * @var QuestionModel
     */
    protected $questionModel;

    /**
     * @var QuestionAdminManager
     */
    protected $questionAdminManager;

    protected $questionCategoryManager;

    protected $questionAdminDataFormatter;
    
    protected $questionNextSelector;

    protected $questionCorrelationManager;

    /**
     * @param QuestionModel $questionModel
     * @param QuestionAdminManager $questionAdminManager
     * @param QuestionCategoryManager $questionCategoryManager
     * @param QuestionNextSelector $questionNextSelector
     */
    public function __construct(QuestionModel $questionModel, QuestionAdminManager $questionAdminManager, QuestionCategoryManager $questionCategoryManager, QuestionNextSelector $questionNextSelector, QuestionCorrelationManager $questionCorrelationManager)
    {
        $this->questionModel = $questionModel;
        $this->questionAdminManager = $questionAdminManager;
        $this->questionCategoryManager = $questionCategoryManager;
        $this->questionNextSelector = $questionNextSelector;
        $this->questionCorrelationManager = $questionCorrelationManager;
        $this->questionAdminDataFormatter = new QuestionAdminDataFormatter();
    }

    public function createQuestion(array $data)
    {
        $data = $this->questionAdminDataFormatter->getCreateData($data);
        $created = $this->questionAdminManager->create($data);
        $questionId = $created->getQuestionId();
        $this->questionCategoryManager->setQuestionCategories($questionId, $data);

        return $this->getOneMultilanguage($questionId);
    }

    public function updateQuestion(array $data)
    {
        $data = $this->questionAdminDataFormatter->getUpdateData($data);
        $created = $this->questionAdminManager->update($data);
        $questionId = $created->getQuestionId();

        return $this->getOneMultilanguage($questionId);
    }

    public function getById($questionId, $locale)
    {
        return $this->questionModel->getById($questionId, $locale);

    }

    public function getOneMultilanguage($questionId)
    {
        return $this->questionAdminManager->getById($questionId);
    }

    public function deleteQuestion($questionId)
    {
        if (null === $questionId) {
            return false;
        }

        $data = array('questionId' => $questionId);

        return $this->questionModel->delete($data);
    }
    
    public function getNextByUser($userId, $locale, $sortByRanking = true)
    {
        $row = $this->questionNextSelector->getNextByUser($userId, $locale, $sortByRanking);
        return $this->questionModel->build($row, $locale);
    }

    public function getNextByOtherUser($userId, $locale, $sortByRanking = true)
    {
        $row = $this->questionNextSelector->getNextByOtherUser($userId, $locale, $sortByRanking);
        return $this->questionModel->build($row, $locale);
    }

    public function getDivisiveQuestions($locale)
    {
        $result = $this->questionCorrelationManager->getDivisiveQuestions($locale);

        $questions = array();
        foreach ($result as $row)
        {
            $questions[] = $this->questionModel->build($row, $locale);
        }

        return $questions;
    }
}