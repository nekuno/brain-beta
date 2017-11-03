<?php

namespace Model\User\Question\Admin;

use Everyman\Neo4j\Node;
use Everyman\Neo4j\Query\Row;

class QuestionAdminBuilder
{
    public function build(Row $row)
    {
        $questionNode = $row->offsetGet('question');
        $question = $this->buildQuestion($questionNode);

        $this->addAnswers($row, $question);
        $this->addStats($row, $question);

        return $question;
    }

    protected function buildQuestion(Node $questionNode)
    {
        $question = new QuestionAdmin();
        $question->setQuestionId($questionNode->getId());
        $question->setQuestionText('es', $questionNode->getProperty('text_es'));
        $question->setQuestionText('en', $questionNode->getProperty('text_en'));

        return $question;
    }

    /**
     * @param Row $row
     * @param $question
     */
    protected function addAnswers(Row $row, QuestionAdmin $question)
    {
        if (!$row->offsetExists('answers')) {
            return;
        }

        $answers = $this->buildAnswers($row);
        $question->setAnswers($answers);
    }

    /**
     * @param Row $row
     * @return array
     */
    protected function buildAnswers(Row $row)
    {
        $answerNodes = $row->offsetGet('answers');
        $answers = array();
        foreach ($answerNodes as $answerNode) {
            $answers[] = $this->buildAnswer($answerNode);
        }

        return $answers;
    }

    protected function buildAnswer(Node $answerNode)
    {
        $answer = new AnswerAdmin();
        $answer->setAnswerId($answerNode->getId());
        $answer->setText('es', $answerNode->getProperty('text_es'));
        $answer->setText('en', $answerNode->getProperty('text_en'));

        return $answer;
    }

    protected function addStats(Row $row, QuestionAdmin $question)
    {
        $countAnswered = $row->offsetExists('answered') ? $row->offsetGet('answered') : 0;
        $question->setAnswered($countAnswered);

        $countSkipped = $row->offsetExists('skipped') ? $row->offsetGet('skipped') : 0;
        $question->setSkipped($countSkipped);
    }
}