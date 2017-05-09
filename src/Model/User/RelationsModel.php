<?php

namespace Model\User;

use Doctrine\DBAL\Connection;
use Event\UserBothLikedEvent;
use Everyman\Neo4j\Node;
use Everyman\Neo4j\Query\ResultSet;
use Everyman\Neo4j\Query\Row;
use Everyman\Neo4j\Relationship;
use Model\Exception\ValidationException;
use Model\Neo4j\GraphManager;
use Manager\UserManager;
use Model\Neo4j\QueryBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RelationsModel
{
    const BLOCKS = 'BLOCKS';
    const FAVORITES = 'FAVORITES';
    const LIKES = 'LIKES';
    const DISLIKES = 'DISLIKES';
    const IGNORES = 'IGNORES';
    const REPORTS = 'REPORTS';

    /**
     * @var GraphManager
     */
    protected $gm;

    /**
     * @var Connection
     */
    protected $connectionBrain;

    /**
     * @var UserManager
     */
    protected $userManager;

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    public function __construct(GraphManager $gm, Connection $connectionBrain, UserManager $userManager, EventDispatcher $dispatcher)
    {

        $this->gm = $gm;
        $this->connectionBrain = $connectionBrain;
        $this->userManager = $userManager;
        $this->dispatcher = $dispatcher;
    }

    static public function getRelations()
    {
        return array(
            self::BLOCKS,
            self::FAVORITES,
            self::LIKES,
            self::DISLIKES,
            self::IGNORES,
            self::REPORTS,
        );
    }

    public function getAll($relation, $from = null, $to = null)
    {
        $this->validate($relation);

        $qb = $this->matchRelationshipQuery($relation, $from, $to);
        $this->returnRelationshipQuery($qb);

        $result = $qb->getQuery()->getResultSet();

        $return = $this->buildMany($result);

        return $return;
    }

    public function get($from, $to, $relation)
    {
        $this->validate($relation);

        $qb = $this->matchRelationshipQuery($relation, $from, $to);
        $this->returnRelationshipQuery($qb);

        $result = $qb->getQuery()->getResultSet();

        if ($result->count() === 0) {
            //throw new NotFoundHttpException(sprintf('There is no relation "%s" from user "%s" to "%s"', $relation, $from, $to));
            return array();
        }

        /* @var $row Row */
        $row = $result->current();

        return $this->buildOne($row);
    }

    public function countFrom($from, $relation)
    {
        return $this->count($relation, $from, null);
    }

    public function countTo($to, $relation)
    {
        return $this->count($relation, null, $to);
    }

    protected function count($relation, $from, $to)
    {
        $this->validate($relation);

        $qb = $this->matchRelationshipQuery($relation, $from, $to);
        $this->returnCountRelationshipsQuery($qb);

        $result = $qb->getQuery()->getResultSet();

        /* @var $row Row */
        $row = $result->current();

        return $row->offsetGet('count');
    }

    public function create($from, $to, $relation, $data = array())
    {
        $this->validate($relation);

        if (!$this->relationMustBeCreated($from, $to, $relation)) {
            return array();
        }

        $qb = $this->mergeRelationshipQuery($relation, $from, $to);

        $this->setTimestampQuery($qb, $data);
        $this->setRelationshipAttributesQuery($qb, $data);

        $this->addExtraRelationshipsQuery($qb, $relation);
        $this->deleteExtraRelationshipsQuery($qb, $relation);

        $this->returnRelationshipQuery($qb);

        $result = $qb->getQuery()->getResultSet();

        if ($result->count() === 0) {
            throw new NotFoundHttpException(sprintf('Unable to create relation "%s" from user "%s" to "%s"', $relation, $from, $to));
        }

        if ($relation === self::LIKES && !empty($this->get($to, $from, self::LIKES))) {
            $this->dispatcher->dispatch(\AppEvents::USER_BOTH_LIKED, new UserBothLikedEvent($from, $to));
        }

        /* @var $row Row */
        $row = $result->current();

        return $this->buildOne($row);
    }

    public function remove($from, $to, $relation)
    {
        $this->validate($relation);
        $return = $this->get($from, $to, $relation);

        $qb = $this->matchRelationshipQuery($relation, $from, $to);
        $this->deleteExtraRelationshipsQuery($qb, $relation);
        $qb->delete('r');

        return $return;
    }

    public function contactFrom($id)
    {
        $messaged = $this->getMessaged($id);

        $qb = $this->gm->createQueryBuilder();

        $qb->match('(from:User {qnoow_id: { id }})', '(to:User)')
            ->where('to.qnoow_id <> { id }')
            ->optionalMatch('(from)-[fav:FAVORITES]->(to)')
            ->setParameter('id', (integer)$id)
            ->with('from', 'to', 'fav')
            ->where('to.qnoow_id IN { messaged } OR NOT fav IS NULL')
            ->setParameter('messaged', $messaged)
            ->with('from', 'to')
            ->where('NOT (from)-[:' . RelationsModel::BLOCKS . ']-(to)')
            ->returns('to AS u')
            ->orderBy('u.qnoow_id');

        $result = $qb->getQuery()->getResultSet();
        $return = array();

        foreach ($result as $row) {
            /* @var $row Row */
            $return[] = $this->userManager->build($row);
        }

        return $return;
    }

    public function contactTo($id)
    {
        $messaged = $this->getMessaged($id);

        $qb = $this->gm->createQueryBuilder();

        $qb->match('(from:User {qnoow_id: { id }})', '(to:User)')
            ->where('to.qnoow_id <> { id }')
            ->optionalMatch('(from)<-[fav:FAVORITES]-(to)')
            ->setParameter('id', (integer)$id)
            ->with('from', 'to', 'fav')
            ->where('to.qnoow_id IN { messaged } OR NOT fav IS NULL')
            ->setParameter('messaged', $messaged)
            ->with('from', 'to')
            ->where('NOT (from)-[:' . RelationsModel::BLOCKS . ']-(to)')
            ->returns('to AS u')
            ->orderBy('u.qnoow_id');

        $result = $qb->getQuery()->getResultSet();
        $return = array();

        foreach ($result as $row) {
            /* @var $row Row */
            $return[] = $this->userManager->build($row);
        }

        return $return;
    }

    public function canContact($from, $to)
    {
        $qb = $this->matchRelationshipQuery(self::BLOCKS, $from, $to);
        $this->returnRelationshipQuery($qb);

        $result = $qb->getQuery()->getResultSet();

        return $result->count() !== 0;
    }

    protected function getMessaged($id)
    {

        $messaged = $this->connectionBrain->executeQuery(
            '
            SELECT * FROM (
              SELECT user_to AS user FROM chat_message
              WHERE user_from = :id
              GROUP BY user_to
              UNION
              SELECT user_from AS user FROM chat_message
              WHERE user_to = :id
              GROUP BY user_from
            ) AS tmp ORDER BY tmp.user',
            array('id' => (integer)$id)
        )->fetchAll(\PDO::FETCH_COLUMN);

        $messaged = array_map(
            function ($i) {
                return (integer)$i;
            },
            $messaged
        );

        return $messaged;
    }

    protected function buildOne(Row $row)
    {
        /* @var $from Node */
        $from = $row->offsetGet('from');
        /* @var $to Node */
        $to = $row->offsetGet('to');
        /* @var $relationship Relationship */
        $relationship = $row->offsetGet('r');

        return array_merge(
            array(
                'id' => $relationship->getId(),
                'from' => $from->getProperties(),
                'to' => $to->getProperties(),
            ),
            $relationship->getProperties()
        );
    }

    /**
     * @param $result
     * @return array
     */
    protected function buildMany(ResultSet $result)
    {
        $return = array();
        /* @var $row Row */
        foreach ($result as $row) {
            $return[] = $this->buildOne($row);
        }

        return $return;
    }

    protected function validate($relation)
    {
        $relations = self::getRelations();

        if (!in_array($relation, $relations)) {
            $message = sprintf('Relation type "%s" not allowed, possible values "%s"', $relation, implode('", "', $relations));
            $errors = array('type' => array($message));
            throw new ValidationException($errors, $message);
        }
    }

    /**
     * @param $qb
     * @param $data
     */
    protected function setTimestampQuery(QueryBuilder $qb, &$data)
    {
        if (isset($data['timestamp'])) {
            $date = new \DateTime($data['timestamp']);
            $timestamp = ($date->getTimestamp()) * 1000;
            unset($data['timestamp']);
            $qb->set('r.timestamp = { timestamp }')
                ->setParameter('timestamp', $timestamp);
        } else {
            $qb->set('r.timestamp = timestamp()');
        }
        $qb->with('from', 'to', 'r');
    }

    /**
     * @param $qb
     * @param $data
     */
    protected function setRelationshipAttributesQuery(QueryBuilder $qb, $data)
    {
        foreach ($data as $key => $value) {
            $qb->set("r.$key = { $key }")
                ->setParameter($key, $value);
        }
        $qb->with('from', 'to', 'r');

    }

    /**
     * @param $qb
     * @param $relation
     */
    protected function deleteExtraRelationshipsQuery(QueryBuilder $qb, $relation)
    {
        $relationsToDelete = $this->getRelationsToDelete($relation);
        foreach ($relationsToDelete as $index => $relationToDelete) {
            $qb->optionalMatch('(from)-[rToDel' . $index . ':' . $relationToDelete . ']->(to)')
                ->delete('rToDel' . $index)
                ->with('from', 'to', 'r');
        }
    }

    /**
     * @param $qb
     * @param $relation
     */
    protected function addExtraRelationshipsQuery(QueryBuilder $qb, $relation)
    {
        $relationsToAdd = $this->getRelationsToAdd($relation);
        foreach ($relationsToAdd as $relationToAdd) {
            $qb->merge('(from)-[:' . $relationToAdd . ']->(to)');
        }
        $qb->with('from', 'to', 'r');
    }

    protected function relationMustBeCreated($from, $to, $relation)
    {
        if ($relation === self::IGNORES) {
            $likes = $this->get($from, $to, self::LIKES);
            $dislikes = $this->get($from, $to, self::DISLIKES);
            if (count($likes) + count($dislikes) > 0) {
                return false;
            }
        }

        return true;
    }

    protected function getRelationsToDelete($relation)
    {
        switch ($relation) {
            case self::LIKES:
                return array(self::DISLIKES, self::IGNORES);
            case self::DISLIKES:
                return array(self::LIKES, self::IGNORES);
            case self::BLOCKS:
                return array(self::LIKES);
            default:
                break;
        }

        return array();
    }

    protected function getRelationsToAdd($relation)
    {
        switch ($relation) {
            case self::REPORTS:
                return array(self::BLOCKS);
            default:
                break;
        }

        return array();
    }

    protected function matchRelationshipQuery($relation, $from = null, $to = null)
    {
        return $this->initialRelationshipQuery($relation, $from, $to, false);
    }

    protected function mergeRelationshipQuery($relation, $from, $to)
    {
        return $this->initialRelationshipQuery($relation, $from, $to, true);
    }

    protected function initialRelationshipQuery($relation, $from, $to, $merge = false)
    {
        $userFrom = $from ? '(from:UserEnabled {qnoow_id: { from }})' : '(from:UserEnabled)';
        $userTo = $to ? '(to:UserEnabled {qnoow_id: { to }})' : '(to:UserEnabled)';

        $qb = $this->gm->createQueryBuilder();
        $relationship = '(from)-[r:' . $relation . ']->(to)';

        if ($merge){
            $qb->merge($userFrom);
            $qb->merge($userTo);
            $qb->merge($relationship);
        } else {
            $qb->match($userFrom, $userTo, $relationship);
        }

        if ($from) {
            $qb->setParameter('from', (integer)$from);
        }
        if ($to) {
            $qb->setParameter('to', (integer)$to);
        }

        $qb->with('from', 'to', 'r');

        return $qb;
    }

    protected function returnRelationshipQuery(QueryBuilder $qb)
    {
        $qb->returns('from', 'to', 'r');
    }

    protected function returnCountRelationshipsQuery(QueryBuilder $qb)
    {
        $qb->returns('count (r) AS count');
    }
}