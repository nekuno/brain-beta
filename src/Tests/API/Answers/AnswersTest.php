<?php

namespace Tests\API\Answers;

class AnswersTest extends AnswersAPITest
{
    protected $createdQuestionId = 1;
    protected $createdAnswerId = 1;

    public function testAnswers()
    {
        $this->loginOwnUser();
        $this->assertAnswer();
        $this->assertGetOwnAnswers();
    }

    public function assertGetOwnAnswers()
    {
        $response = $this->getOwnAnswers();
        $this->assertJsonResponse($response, 200, 'Getting own questions');
    }

    public function assertAnswer()
    {
        $answerData = $this->getAnswerData();
        $response = $this->answerQuestion($answerData);
        $this->assertJsonResponse($response, 201, 'Correctly answering a question');

        $response = $this->answerQuestion($answerData);
        $this->assertStatusCode($response, 422, 'Cannot answer again in less than 24 hours');
    }

    protected function getAnswerData()
    {
        return array(
            'questionId' => $this->createdQuestionId,
            'answerId' => $this->createdAnswerId,
            'acceptedAnswers' => array($this->createdAnswerId),
            'rating' => 2,
            'explanation' => '',
            'isPrivate' => false,
        );
    }

}