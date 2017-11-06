<?php

namespace Service;

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

    /**
     * @param QuestionModel $questionModel
     * @param QuestionAdminManager $questionAdminManager
     */
    public function __construct(QuestionModel $questionModel, QuestionAdminManager $questionAdminManager)
    {
        $this->questionModel = $questionModel;
        $this->questionAdminManager = $questionAdminManager;
    }

    public function createQuestion(array $data)
    {

        $created = $this->questionAdminManager->create($data);
        $questionId = $created->getQuestionId();

        return $this->getOneMultilanguage($questionId);
    }

    //TODO: Write custom update query in QuestionAdminManager
    public function updateMultilanguage(array $data)
    {
        $dataSets = $this->multilingualToLocales($data);

        foreach ($dataSets as $dataSet)
        {
            $this->questionModel->update($dataSet);
        }

        return $this->getOneMultilanguage($data['questionId']);
    }

    protected function multilingualToLocales(array $data)
    {
        $localesData = array();
        $localesAvailable = array('es', 'en');

        $questionId = isset($data['questionId']) ? $data['questionId'] : null;
        foreach ($localesAvailable as $locale) {

            $result = array('locale' => $locale);
            if ($questionId) {
                $result['questionId'] = $questionId;
            }

            $answers = array();
            for ($i = 1; $i <= 6; $i++) {
                $answerTextLabel = 'answer' . $i . ucfirst($locale);
                $answerIdLabel = 'answer' . $i . 'Id';
                $answers[] = array('answerId' => $data[$answerIdLabel], 'text' => $data[$answerTextLabel]);
            }
            $result['answers'] = $answers;

            $questionTextLabel = 'text' . ucfirst($locale);
            $result['text'] = $data[$questionTextLabel];

            $localesData[] = $result;
        }

        return $localesData;
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