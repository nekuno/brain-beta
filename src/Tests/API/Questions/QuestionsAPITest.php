<?php

namespace Tests\API\Questions;

use Tests\API\APITest;

abstract class QuestionsAPITest extends APITest
{
    public function createQuestion($questionData, $loggedInUserId = 1)
    {
        return $this->getResponseByRoute('/questions', 'POST', $questionData, $loggedInUserId);
    }

    public function getOwnAnswers($loggedInUserId = 1)
    {
        return $this->getResponseByRoute('/answers', 'GET', array(), $loggedInUserId);
    }

    public function getNextOwnQuestion($loggedInUserId = 1)
    {
        return $this->getResponseByRoute('/questions/next', 'GET', array(), $loggedInUserId);
    }

    public function skipQuestion($questionId, $loggedInUserId = 1)
    {
        $url = '/questions/' . $questionId . '/skip';

        return $this->getResponseByRoute($url, 'POST', array(), $loggedInUserId);
    }

    public function reportQuestion($questionId, $loggedInUserId = 1)
    {
        $url = '/questions/' . $questionId . '/report';

        return $this->getResponseByRoute($url, 'POST', array(), $loggedInUserId);
    }

    public function answerQuestion($data, $loggedInUserId = 1)
    {
        return $this->getResponseByRoute('/answers', 'POST', $data, $loggedInUserId);
    }

    protected function assertQuestionFormat($data)
    {

    }
}