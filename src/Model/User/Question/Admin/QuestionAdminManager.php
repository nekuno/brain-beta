<?php

namespace Model\User\Question\Admin;

use Everyman\Neo4j\Query\Row;
use Model\Neo4j\GraphManager;
use Service\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QuestionAdminManager
{
    protected $graphManager;

    protected $questionAdminBuilder;

    protected $validator;

    /**
     * QuestionAdminManager constructor.
     * @param GraphManager $graphManager
     * @param QuestionAdminBuilder $questionAdminBuilder
     * @param ValidatorInterface $validator
     */
    public function __construct(GraphManager $graphManager, QuestionAdminBuilder $questionAdminBuilder, ValidatorInterface $validator)
    {
        $this->graphManager = $graphManager;
        $this->questionAdminBuilder = $questionAdminBuilder;
        $this->validator = $validator;
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
            throw new NotFoundHttpException('Question for admin not found');
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
        $this->validateOnCreate($data);

        $answersData = $data['answerTexts'];
        $questionTexts = $data['questionTexts'];
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

    protected function validateOnCreate(array $data)
    {
        $this->validator->validateOnCreate($data);
    }
}