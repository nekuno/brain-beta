<?php

namespace Service;

use Model\Metadata\MetadataManager;
use Model\User\Question\Admin\QuestionAdminDataFormatter;
use Model\User\Question\Admin\QuestionAdminManager;
use Model\User\Question\QuestionModel;

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

    protected $questionAdminDataFormatter;

    /**
     * @param QuestionModel $questionModel
     * @param QuestionAdminManager $questionAdminManager
     */
    public function __construct(QuestionModel $questionModel, QuestionAdminManager $questionAdminManager)
    {
        $this->questionModel = $questionModel;
        $this->questionAdminManager = $questionAdminManager;
        $this->questionAdminDataFormatter = new QuestionAdminDataFormatter();
    }

    public function createQuestion(array $data)
    {
        $data = $this->questionAdminDataFormatter->getCreateData($data);
        $created = $this->questionAdminManager->create($data);
        $questionId = $created->getQuestionId();

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
}