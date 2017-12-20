<?php

namespace Model\User\Question\Admin;

use Everyman\Neo4j\Query\Row;
use Model\Neo4j\GraphManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QuestionAdminManager
{
    protected $graphManager;

    protected $questionAdminBuilder;

    /**
     * QuestionAdminManager constructor.
     * @param GraphManager $graphManager
     * @param QuestionAdminBuilder $questionAdminBuilder
     */
    public function __construct(GraphManager $graphManager, QuestionAdminBuilder $questionAdminBuilder)
    {
        $this->graphManager = $graphManager;
        $this->questionAdminBuilder = $questionAdminBuilder;
    }

    public function getById($id)
    {
        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(q:Question)<-[:IS_ANSWER_OF]-(a:Answer)')
            ->where('id(q) = { id }')
            ->setParameter('id', (integer)$id)
            ->with('q', 'a')
            ->orderBy('id(a)')
            ->with('q as question, COLLECT(a) AS answers')
            ->optionalMatch('(question)-[r:RATES]-(:User)')
            ->with('question', 'answers', 'count(r) AS answered')
            ->optionalMatch('(question)-[s:SKIPS]-(:User)')
            ->returns('question', 'answers', 'answered', 'count(s) AS skipped')
            ->limit(1);

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException(sprintf('Question %d for admin not found', $id));
        }

        /* @var $row Row */
        $row = $result->current();

        return $this->questionAdminBuilder->build($row);
    }

    /**
     * @param array $data
     * @return QuestionAdmin
     */
    public function create(array $data)
    {
//        $this->validateOnCreate($data);

        $answersData = $this->getAnswersTexts($data);
        $questionTexts = $this->getQuestionTexts($data);
        $qb = $this->graphManager->createQueryBuilder();

        $qb->create('(q:Question)');
        foreach ($questionTexts as $locale => $text) {
            $qb->set("q.text_$locale = {question$locale}")
                ->setParameter("question$locale", $text);
        }
        $qb->set('q.timestamp = timestamp()', 'q.ranking = 0');

        foreach ($answersData as $answerIndex => $answerData) {
            $qb->create("(a$answerIndex:Answer)-[:IS_ANSWER_OF]->(q)");
            foreach ($answerData as $locale => $text) {
                $qb->set("a$answerIndex.text_$locale = {text$answerIndex$locale}")
                    ->setParameter("text$answerIndex$locale", $text);
            }
        };
        $qb->returns('q AS question');

        $query = $qb->getQuery();

        $result = $query->getResultSet();
        /* @var $row Row */
        $row = $result->current();

        return $this->questionAdminBuilder->build($row);
    }

    protected function getAnswersTexts(array $data)
    {
        $answers = array();
        foreach ($data as $key => $value) {
            $hasAnswerText = strpos($key, 'answer') !== false;
            $isNotId = strpos($key, 'Id') === false;
            if ($hasAnswerText && $isNotId && !empty($value)) {
                $id = $this->extractAnswerId($key);
                $locale = $this->extractLocale($key);
                $answers[$id][$locale] = $value;
            }
        }

        return $answers;
    }

    //To change with more locales
    protected function extractLocale($text)
    {
        if (strpos($text, 'Es') !== false) {
            return 'es';
        }

        return 'en';
    }

    protected function extractAnswerId($text)
    {
        $prefixSize = strlen('answer');
        $number = substr($text, $prefixSize, 1);

        return (integer)$number;
    }

    protected function getQuestionTexts(array $data)
    {
        $texts = array();
        foreach ($data as $key => $value) {
            $isQuestionText = strpos($key, 'text') === 0;
            if ($isQuestionText) {
                $locale = $this->extractLocale($key);
                $texts[$locale] = $value;
            }
        }

        return $texts;
    }
}