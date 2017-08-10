<?php

namespace Tests\API\Questions;

class AnswersTest extends QuestionsAPITest
{
    protected $createdQuestionId = 1;
    protected $createdAnswerId = 1;

    public function testAnswers()
    {
        $this->createAndLoginUserA();
        $this->createQuestionA();
        $this->assertAnswer();
        $this->assertGetOwnAnswers();
        $this->assertDeleteAnswer();
    }

    public function assertGetOwnAnswers()
    {
        $response = $this->getOwnAnswers();
        $answersData = $this->assertJsonResponse($response, 200, 'Getting own questions');
    }

    public function assertAnswer()
    {
        $answerData = $this->getAnswerData();
        $response = $this->answerQuestion($answerData);
        $this->assertJsonResponse($response, 201, 'Correctly answering a question');

        $response = $this->answerQuestion($answerData);
        var_dump($response->getContent());
        $this->assertStatusCode($response, 422, 'Cannot answer again in less than 24 hours');
    }

    public function assertDeleteAnswer()
    {
        //We currently (Aug 2017) skip question to do this. Couldn´t we just remove the answer without skipping? This makes it harder to answer again
    }

    protected function createQuestionA()
    {
        $data = $this->getCreateQuestionDataA();
        $question = $this->createQuestion($data);
        $questionData = $this->assertJsonResponse($question, 201);
        $this->createdQuestionId = $questionData['questionId'];
        $this->createdAnswerId = $questionData['answers'][0]['answerId'];
    }

    protected function getCreateQuestionDataA()
    {
        $answers = array(
            array('text' => 'Answer 1 to question A'),
            array('text' => 'Answer 2 to question A'),
            array('text' => 'Answer 3 to question A'),
        );

        return array(
            'locale' => 'en',
            'text' => 'English text question A',
            'answers' => $answers,
        );
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