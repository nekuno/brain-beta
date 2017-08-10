<?php

namespace Tests\API\Questions;

class QuestionsTest extends QuestionsAPITest
{
    protected $nextQuestionId;

    public function testQuestions()
    {
        $this->createAndLoginUserA();
        $this->assertQuestionCreation();
        $this->assertNextQuestion();
        $this->assertSkipQuestion();
        $this->assertReportQuestions();
    }

    public function assertQuestionCreation()
    {
        $questionData = $this->getCreateQuestionDataA();
        $response = $this->createQuestion($questionData, 999);
        $this->assertStatusCode($response, 401, 'Question creation from non-existent user');

        $response = $this->createQuestion($questionData, 1);
        $this->assertStatusCode($response, 201, 'Correct question creation');
    }

    //TODO: Get own answers
//    public function assertGetQuestions()
//    {
//        $response = $this->getOwnQuestions();
//        $questionsData = $this->assertJsonResponse($response, 200, 'Getting own questions');
//        $this->a
//
//    }

    public function assertNextQuestion()
    {
        $response = $this->getNextOwnQuestion();
        $questionData = $this->assertJsonResponse($response, 200, 'Getting own next question');
        $this->assertQuestionFormat($questionData);
        $this->nextQuestionId = $questionData['questionId'];
    }

    public function assertSkipQuestion()
    {
        $response = $this->skipQuestion($this->nextQuestionId);
        $this->assertStatusCode($response, 201, 'Skipping question response');

        $response = $this->getNextOwnQuestion();
        $this->assertStatusCode($response, 404, 'No next question after skipped');
    }

    public function assertReportQuestions()
    {
        $response = $this->reportQuestion($this->nextQuestionId);
        $this->assertStatusCode($response, 201, 'Correctly reported question');
        //TODO: Get own answers up and then check it does not appear anymore
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
            'answers' => $answers
        );
    }

}