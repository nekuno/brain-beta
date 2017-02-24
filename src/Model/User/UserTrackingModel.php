<?php

namespace Model\User;

use Doctrine\ORM\EntityManager;
use Model\Entity\UserTrackingEvent;
use Model\Neo4j\GraphManager;
use Symfony\Component\EventDispatcher\EventDispatcher;

class UserTrackingModel
{
    /**
     * @var GraphManager
     */
    protected $gm;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    public function __construct(GraphManager $gm, EntityManager $em, EventDispatcher $dispatcher)
    {
        $this->gm = $gm;
        $this->em = $em;
        $this->dispatcher = $dispatcher;
    }

    public function getAll()
    {
        $userTrackingEvents = $this->em->getRepository('\Model\Entity\UserTrackingEvent')->findAll();

        return $this->formatUserTrackingEventsArray($userTrackingEvents);
    }

    public function get($userId)
    {
        $userTrackingEvents = $this->em->getRepository('\Model\Entity\UserTrackingEvent')->findBy(array('userId' => $userId), array('createdAt' => 'DESC'));

        return $this->formatUserTrackingEventsArray($userTrackingEvents);
    }

    public function set($userId, $action = null, $category = null, $tag = null, $data = null)
    {
        /** @var UserTrackingEvent $userTrackingEvent */
        $userTrackingEvent = new UserTrackingEvent();
        $userTrackingEvent->setUserId($userId);
        $userTrackingEvent->setAction($action);
        $userTrackingEvent->setCategory($category);
        $userTrackingEvent->setTag($tag);
        $userTrackingEvent->setData($data);

        $this->em->persist($userTrackingEvent);
        $this->em->flush();

        return $userTrackingEvent->toArray();
    }

    protected function formatUserTrackingEventsArray(array $userTrackingEvents)
    {
        $userTrackingEventsArray = array();
        /** @var UserTrackingEvent $userTrackingEvent */
        foreach ($userTrackingEvents as $userTrackingEvent) {
            $username = $this->getUsername($userTrackingEvent->getUserId());
            $userTrackingEventsArray[] = $userTrackingEvent->toArray() + array('username' => $username);
        }

        return $userTrackingEventsArray;
    }

    protected function getUsername($userId)
    {
        $qb = $this->gm->createQueryBuilder()
            ->match('(u:User {qnoow_id: { userId }})')
            ->setParameter('userId', $userId)
            ->returns('u.username AS username');
        $query = $qb->getQuery();
        $result = $query->getResultSet();
        if ($result->count() > 0) {
            /* @var $row Row */
            $row = $result->current();
            $username = $row->offsetGet('username');

            return $username;
        }

        return 'No username';
    }
}