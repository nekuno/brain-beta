<?php

namespace Model\User;

use Event\ContentRatedEvent;
use Everyman\Neo4j\Client;
use Everyman\Neo4j\Query\Row;
use Everyman\Neo4j\Relationship;
use Model\Exception\ValidationException;
use Model\Neo4j\GraphManager;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class RateModel
 *
 * @package Model\User
 */
class RateModel
{

    const LIKE = 'LIKES';
    const DISLIKE = 'DISLIKES';
    const IGNORE = 'IGNORE';

    const QUERY_NAME = 'link.like';

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     * @var \Everyman\Neo4j\Client
     */
    protected $client;
    /**
     * @var \Model\Neo4j\GraphManager
     */
    protected $gm;

    /**
     * @param EventDispatcher $dispatcher
     * @param Client $client
     * @param GraphManager $gm
     */
    public function __construct(EventDispatcher $dispatcher, Client $client, GraphManager $gm)
    {

        $this->dispatcher = $dispatcher;
        $this->client = $client;
        $this->gm = $gm;
    }

    /**
     * For post-like actions
     * @param $userId
     * @param $data array
     * @param $rate
     * @param bool $fireEvent
     * @return array
     * @throws \Exception
     */
    public function userRateLink($userId, array $data, $rate, $fireEvent = true)
    {
        $this->validate($rate);

        switch ($rate) {
            case $this::LIKE :
                $result = $this->userLikeLink($userId, $data);
                break;
            case $this::DISLIKE :
                $result = $this->userDislikeLink($userId, $data);
                break;
            default:
                return array();
        }

        if ($fireEvent) {
            $this->dispatcher->dispatch(\AppEvents::CONTENT_RATED, new ContentRatedEvent($userId));
        }

        return $result;
    }

    public function addLikeToTransaction($userId, array $data)
    {
        $query = $this->getLikeLinkQuery($userId, $data);

        return $this->gm->addToTransaction($this::QUERY_NAME, $query);
    }

    //TODO: Add $this->unrate for delete-like actions

    /**
     * @param $userId
     * @param $rate
     * @param int $limit
     * @return array
     * @throws \Exception
     */
    public function getRatesByUser($userId, $rate, $limit = 999999)
    {
        $this->validate($rate);

        $qb = $this->gm->createQueryBuilder();

        $qb->match('(u:User{qnoow_id: {userId} })')
            ->match("(u)-[r:$rate]->(l:Link)")
            ->returns('r', 'l.url as linkUrl')
            ->limit('{limit}');

        $qb->setParameters(array(
            'userId' => (integer)$userId,
            'limit' => (integer) $limit,
        ));

        $rs = $qb->getQuery()->getResultSet();

        $rates = array();
        foreach ($rs as $row)
        {
            if ($rate == $this::LIKE){
                $rates[] = $this->buildLike($row);
            } else if ($rate == $this::DISLIKE){
                $rates[] = $this->buildDislike($row);
            }
        }

        return $rates;
    }

    /**
     * Meant to work only on empty likes as is.
     * @param $likeId
     * @return array|bool
     * @throws \Model\Neo4j\Neo4jException
     */
    public function completeLikeById($likeId){

        $rate = $this::LIKE;
        $qb = $this->gm->createQueryBuilder();
        $qb->match("(u:User)-[r:$rate]->(l:Link)")
            ->where('id(r)={likeId}')
            ->set('r.nekuno = timestamp()', 'r.last_liked = timestamp()')
            ->returns('r','l.url');
        $qb->setParameters(array(
            'likeId' => (integer)$likeId
        ));

        $rs = $qb->getQuery()->getResultSet();

        if ($rs->count() == 0){
            return false;
        }

        return $this->buildLike($rs->current());
    }

    public function commitQueries()
    {
        return $this->gm->executeTransaction($this::QUERY_NAME);
    }

    /**
     * @param $userId
     * @param array $data
     * @return array
     * @throws \Exception
     */
    protected function userLikeLink($userId, array $data = array())
    {

        if (empty($userId) || empty($data['id'])) return array('empty thing' => 'true'); //TODO: Fix this return

        $query = $this->getLikeLinkQuery($userId, $data);

        $result = $query->getResultSet();

        $return = array();
        foreach ($result as $row) {
            $return[] = $this->buildLike($row);
        }

        return $return;

    }

    protected function getLikeLinkQuery ($userId, array $data = array())
    {
        $qb = $this->gm->createQueryBuilder();

        $qb->setParameters(array(
            'userId' => (integer)$userId,
            'linkId' => (integer)$data['id'],
            'timestamp' => isset($data['timestamp']) ? $data['timestamp'] : 1000*time(),
        ));

        $qb->match('(u:User)', '(l:Link)')
            ->where('u.qnoow_id = { userId }', 'id(l) = { linkId }')
            ->merge('(u)-[r:DISLIKES]->(l)')
            ->set('r.disliked={timestamp}');

        $qb->with('u, r, l')
            ->optionalMatch('(u)-[a:AFFINITY|LIKES]-(l)')
            ->delete('a');

        $qb->returns('r');

        return $qb->getQuery();
    }

    private function userDislikeLink($userId, $data)
    {
        if (empty($userId) || empty($data['id'])) return array('empty thing' => 'true');

        $qb = $this->gm->createQueryBuilder();

        $qb->setParameters(array(
            'userId' => (integer)$userId,
            'linkId' => (integer)$data['id'],
            'timestamp' => isset($data['timestamp']) ? $data['timestamp'] : 1000*time(),
        ));

        $qb->match('(u:User)', '(l:Link)')
            ->where('u.qnoow_id = { userId }', 'id(l) = { linkId }')
            ->merge('(u)-[r:DISLIKES]->(l)')
            ->set('r.disliked={timestamp}');

        $qb->with('u, r, l')
            ->optionalMatch('(u)-[a:AFFINITY|LIKES]-(l)')
            ->delete('a');

        $qb->returns('r');

        $result = $qb->getQuery()->getResultSet();

        $return = array();
        foreach ($result as $row) {
            $return[] = $this->buildDislike($row);
        }

        return $return;
    }

    /**
     * Intended to mimic a Like object
     * @param Row $row with r as Like relationship
     * @return array
     */
    protected function buildLike($row)
    {
        /* @var $relationship Relationship */
        $relationship = $row->offsetGet('r');

        $resources = array();
        $resourceOwners = array_merge(array('nekuno'), TokensModel::getResourceOwners());
        foreach ($resourceOwners as $resourceOwner){
            if ($relationship->getProperty($resourceOwner))
            {
                $resources[$resourceOwner] = $relationship->getProperty($resourceOwner);
            }
        }


        return array(
            'id' => $relationship->getId(),
            'resources' => $resources,
            'timestamp' => $relationship->getProperty('last_liked'),
            'linkUrl' => $row->offsetGet('linkUrl'),
        );
    }

    /**
     * Intended to mimic a Dislike object
     * @param Row $row with r as Dislike relationship
     * @return array
     */
    protected function buildDislike($row)
    {
        /* @var $relationship Relationship */
        $relationship = $row->offsetGet('r');

        return array(
            'id' => $relationship->getId(),
            'timestamp' => $relationship->getProperty('timestamp'),
        );
    }

    /**
     * @param $rate
     * @throws \Exception
     */
    private function validate($rate)
    {
        $errors = array();
        if ($rate !== self::LIKE && $rate != self::DISLIKE && $rate != self::IGNORE) {
            $errors['rate'] = array(sprintf('%s is not a valid rate', $rate));
        }

        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }
    }

}
