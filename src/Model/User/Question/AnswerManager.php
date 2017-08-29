<?php

namespace Model\User\Question;

use Event\AnswerEvent;
use Everyman\Neo4j\Node;
use Everyman\Neo4j\Query\Row;
use Everyman\Neo4j\Relationship;
use Model\Exception\ValidationException;
use Model\Neo4j\GraphManager;
use Manager\UserManager;
use Service\Validator\AnswerValidator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AnswerManager
{
    /**
     * @var GraphManager
     */
    protected $gm;

    /**
     * @var QuestionModel
     */
    protected $qm;

    /**
     * @var UserManager
     */
    protected $um;

    /**
     * @var EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @var AnswerValidator
     */
    protected $validator;

    public function __construct(GraphManager $gm, QuestionModel $qm, UserManager $um, AnswerValidator $validator, EventDispatcher $eventDispatcher)
    {
        $this->gm = $gm;
        $this->qm = $qm;
        $this->um = $um;
        $this->validator = $validator;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function countTotal(array $filters)
    {
        $count = 0;

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)')
            ->where('u.qnoow_id = { userId }')
            ->setParameter('userId', (integer)$filters['id'])
            ->match('(u)-[r:RATES]->(q:Question)')
            ->returns('COUNT(DISTINCT r) AS total');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if ($result->count() > 0) {
            $row = $result->current();
            /* @var $row Row */
            $count = $row->offsetGet('total');
        }

        return $count;
    }

    public function answer(array $data)
    {
        if ($this->isQuestionAnswered($data['userId'], $data['questionId'])) {
            return $this->update($data);
        } else {
            return $this->create($data);
        }
    }

    public function create(array $data)
    {
        $this->validateOnCreate($data);

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(user:User)', '(question:Question)', '(answer:Answer)')
            ->where('user.qnoow_id = { userId } AND id(question) = { questionId } AND id(answer) = { answerId }')
            ->createUnique('(user)-[a:ANSWERS]->(answer)', '(user)-[r:RATES]->(question)')
            ->set('r.rating = { rating }', 'a.private = { isPrivate }', 'a.answeredAt = timestamp()', 'a.explanation = { explanation }')
            ->with('user', 'question', 'answer')
            ->match('(pa:Answer)-[:IS_ANSWER_OF]->(question)')
            ->where('id(pa) IN { acceptedAnswers }')
            ->createUnique('(user)-[:ACCEPTS]->(pa)')
            ->with('user', 'question', 'answer')
            ->optionalMatch('(user)-[skips:SKIPS]->(question)')
            ->delete('skips')
            ->returns('answer')
            ->setParameters($data);

        $query = $qb->getQuery();

        $query->getResultSet();

        $this->handleAnswerAddedEvent($data);

        return $this->getUserAnswer($data['userId'], $data['questionId'], $data['locale']);

    }

    public function update(array $data)
    {
        $this->validateOnUpdate($data);

        $data['userId'] = intval($data['userId']);
        $data['questionId'] = intval($data['questionId']);
        $data['answerId'] = intval($data['answerId']);

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)-[r1:ANSWERS]->(:Answer)-[:IS_ANSWER_OF]->(q:Question)')
            ->where('u.qnoow_id = { userId } AND id(q) = { questionId }')
            ->with('u', 'q', 'r1')
            ->match('(u)-[r2:ACCEPTS]->(:Answer)-[:IS_ANSWER_OF]->(q)')
            ->with('u', 'q', 'r1', 'r2')
            ->match('(u)-[r3:RATES]->(q)')
            ->delete('r1', 'r2', 'r3')
            ->with('u', 'q')
            ->match('(a:Answer)')
            ->where('id(a) = { answerId }')
            ->createUnique('(u)-[r4:ANSWERS]->(a)', '(u)-[r5:RATES]->(q)')
            ->set('r5.rating = { rating }', 'r4.private = { isPrivate }', 'r4.answeredAt = timestamp()', 'r4.explanation = { explanation }')
            ->with('u', 'q', 'a')
            ->optionalMatch('(answers:Answer)-[:IS_ANSWER_OF]->(q)')
            ->where('id(answers) IN { acceptedAnswers }')
            ->createUnique('(u)-[:ACCEPTS]->(answers)')
            ->returns('a AS answer')
            ->setParameters($data);

        $query = $qb->getQuery();

        $query->getResultSet();

        $this->handleAnswerAddedEvent($data);

        return $this->getUserAnswer($data['userId'], $data['questionId'], $data['locale']);
    }

    public function explain(array $data)
    {
        $data['userId'] = (integer)$data['userId'];
        $data['questionId'] = (integer)$data['questionId'];

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(user:User)-[r:ANSWERS]->(answer:Answer)-[:IS_ANSWER_OF]->(question:Question)')
            ->where('user.qnoow_id = { userId } AND id(question) = { questionId }')
            ->set('r.explanation = { explanation }')
            ->returns('answer')
            ->setParameters($data);

        $query = $qb->getQuery();

        $query->getResultSet();

        return $this->getUserAnswer($data['userId'], $data['questionId'], $data['locale']);

    }

    /**
     * @param $userId
     * @return \Everyman\Neo4j\Query\ResultSet
     */
    public function getNumberOfUserAnswers($userId)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(a:Answer)<-[ua:ANSWERS]-(u:User)')
            ->where('u.qnoow_id = { userId }')
            ->setParameter('userId', (integer)$userId)
            ->returns('count(ua) AS nOfAnswers');

        $query = $qb->getQuery();

        return $query->getResultSet();
    }

    public function getUserAnswer($userId, $questionId, $locale)
    {
        $this->validator->validateUserId($userId);
        $question = $this->qm->getById($questionId, $locale);

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(q:Question)', '(u:User)')
            ->where('u.qnoow_id = { userId }', 'id(q) = { questionId }', "EXISTS(q.text_$locale)")
            ->setParameter('userId', $userId)
            ->setParameter('questionId', $question['questionId'])
            ->with('u', 'q')
            ->match('(u)-[ua:ANSWERS]->(a:Answer)-[:IS_ANSWER_OF]->(q)')
            ->match('(u)-[r:RATES]->(q)')
            ->with('u', 'a', 'q', 'ua', 'r')
            ->match('(answers:Answer)-[:IS_ANSWER_OF]->(q)')
            ->match('(u)-[:ACCEPTS]->(acceptedAnswers:Answer)-[:IS_ANSWER_OF]->(q)')
            ->with('u', 'a', 'ua', 'acceptedAnswers', 'q', 'r', 'answers')
            ->orderBy('ID(answers)', 'ID(acceptedAnswers)')
            ->with('u AS user', 'a AS answer', 'ua AS userAnswer', 'COLLECT(DISTINCT acceptedAnswers) AS acceptedAnswers', 'q AS question', 'r AS rates', 'COLLECT(DISTINCT answers) AS answers')
            ->returns('user', 'answer', 'userAnswer', 'acceptedAnswers', 'question', 'rates', 'answers')
            ->limit(1);

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException(sprintf('There is not answer for user "%s" to question "%s"', $userId, $question['questionId']));
        }

        /* @var $row Row */
        $row = $result->current();

        return $this->build($row, $locale);
    }

    /**
     * @param $userId
     * @param array $answer
     * @return int
     * @throws \Exception
     * @throws \Model\Neo4j\Neo4jException
     */
    public function deleteUserAnswer($userId, array $answer)
    {
        $qb = $this->gm->createQueryBuilder();

        $qb->setParameters(
            array(
                'userId' => $userId,
                'answerId' => $answer['answerId'],
            )
        );

        $qb->match('(a:Answer)', '(u:User)')
            ->where('(id(a) = {answerId})', '(u.qnoow_id = {userId})')
            ->with('a', 'u')
            ->match('(a)-[:IS_ANSWER_OF]->(q:Question)<-[:IS_ANSWER_OF]-(answers:Answer)')
            ->match('(u)-[ua:ANSWERS]->(a)')
            ->optionalMatch('(u)-[rates:RATES]->(q)', '(u)-[accepts:ACCEPTS]->(answers)')
            ->delete('ua', 'rates', 'accepts')
            ->returns('count(ua) as deleted');
        $query = $qb->getQuery();
        $result = $query->getResultSet();

        /* @var $row Row */
        $row = $result->current();

        return ($row->offsetGet('deleted'));

    }

    /**
     * @param array $data
     * @throws ValidationException
     */
    public function validateOnCreate(array $data)
    {
        $this->validator->validateOnCreate($data);
    }

    public function validateOnUpdate(array $data)
    {
        $data = $this->addUserAnswer($data);
        $this->validator->validateOnUpdate($data);
    }

    protected function addUserAnswer(array $data)
    {
        $answerResult = $this->getUserAnswer($data['userId'], $data['questionId'], $data['locale']);
        /** @var Answer $answer */
        $answer = $answerResult['userAnswer'];
        $this->updateEditable($answer);

        $data['userAnswer'] = $answer;

        return $data;
    }

    public function build(Row $row, $locale)
    {
        return array(
            'userAnswer' => $this->buildUserAnswer($row),
            'question' => $this->qm->build($row, $locale),
        );
    }

    protected function buildUserAnswer(Row $row)
    {
        $this->checkMandatoryKeys($row);

        list($question, $answerNode, $userAnswer, $rates) = $this->getRowData($row);

        $acceptedAnswers = $this->buildAcceptedAnswers($row);

        $answer = new Answer();
        $answer->setQuestionId($question->getId());
        $answer->setAnswerId($answerNode->getId());
        $answer->setAcceptedAnswers($acceptedAnswers);
        $answer->setRating($rates->getProperty('rating'));
        $answer->setExplanation($userAnswer->getProperty('explanation'));
        $answer->setPrivate($userAnswer->getProperty('private'));
        $answer->setAnsweredAt($userAnswer->getProperty('answeredAt'));
        $this->updateEditable($answer);

        return $answer;
    }

    protected function updateEditable(Answer $answer)
    {
        $answeredAt = floor($answer->getAnsweredAt() / 1000);
        $now = time();
        $oneDay = 24 * 3600;

        $untilNextEdit = ($answeredAt + $oneDay) - $now;
        $answer->setEditableIn($untilNextEdit);
        $answer->setEditable($untilNextEdit < 0);
    }

    /**
     * @param $userId
     * @param $questionId
     * @return bool
     * @throws \Exception
     */
    protected function isQuestionAnswered($userId, $questionId)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(q:Question)<-[:IS_ANSWER_OF]-(a:Answer)<-[ua:ANSWERS]->(u:User)')
            ->where('id(q) = { questionId }', 'u.qnoow_id = { userId }')
            ->setParameter('questionId', (integer)$questionId)
            ->setParameter('userId', (integer)$userId)
            ->returns('a AS answer');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        return $result->count() > 0;
    }

    /**
     * @param $questionId
     * @return bool
     * @throws \Exception
     */
    protected function existsQuestion($questionId)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(q:Question)')
            ->where('id(q) = { questionId }')
            ->setParameter('questionId', (integer)$questionId)
            ->returns('q AS question');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        return $result->count() > 0;
    }

    protected function handleAnswerAddedEvent(array $data)
    {
        $event = new AnswerEvent($data['userId'], $data['questionId']);
        $this->eventDispatcher->dispatch(\AppEvents::ANSWER_ADDED, $event);
    }

    /**
     * @param Row $row
     */
    protected function checkMandatoryKeys(Row $row)
    {
        $keys = array('question', 'answer', 'userAnswer', 'rates', 'acceptedAnswers');
        foreach ($keys as $key) {
            if (!$row->offsetExists($key)) {
                throw new \RuntimeException(sprintf('"%s" key needed in row', $key));
            }
        }
    }

    /**
     * @param Row $row
     * @return array
     */
    protected function buildAcceptedAnswers(Row $row)
    {
        $acceptedAnswers = array();
        foreach ($row->offsetGet('acceptedAnswers') as $acceptedAnswer) {
            /* @var $acceptedAnswer Node */
            $acceptedAnswers[] = $acceptedAnswer->getId();
        }

        return $acceptedAnswers;
    }

    /**
     * @param Row $row
     * @return array
     */
    protected function getRowData(Row $row)
    {
        /* @var $question Node */
        $question = $row->offsetGet('question');
        /* @var $answerNode Node */
        $answerNode = $row->offsetGet('answer');
        /* @var $userAnswer Node */
        $userAnswer = $row->offsetGet('userAnswer');
        /* @var $rates Relationship */
        $rates = $row->offsetGet('rates');

        return array($question, $answerNode, $userAnswer, $rates);
    }
}