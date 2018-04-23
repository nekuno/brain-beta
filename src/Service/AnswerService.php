<?php

namespace Service;

use Model\Question\AnswerManager;
use Model\Question\QuestionManager;

class AnswerService
{
    protected $answerManager;

    protected $questionManager;

    /**
     * AnswerService constructor.
     * @param AnswerManager $answerManager
     * @param QuestionManager $questionManager
     */
    public function __construct(AnswerManager $answerManager, QuestionManager $questionManager)
    {
        $this->answerManager = $answerManager;
    }

    public function getUserAnswer($userId, $questionId, $locale)
    {
        $row = $this->answerManager->getUserAnswer($userId, $questionId, $locale);
        $answer = $this->answerManager->build($row, $locale);

        return $answer;
    }

}