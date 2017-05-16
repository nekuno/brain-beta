<?php

namespace Model\User;

use Doctrine\DBAL\Connection;
use Everyman\Neo4j\Query\Row;
use Model\Neo4j\GraphManager;
use Manager\UserManager;

class ContactModel
{
    /**
     * @var Connection
     */
    protected $connectionBrain;

    /**
     * @var UserManager
     */
    protected $userManager;

    /**
     * @var GraphManager
     */
    protected $graphManager;

    /**
     * @var RelationsModel
     */
    protected $relationsModel;

    /**
     * @param GraphManager $graphManager
     * @param Connection $connectionBrain
     * @param UserManager $userManager
     * @param RelationsModel $model
     */
    public function __construct(GraphManager $graphManager, Connection $connectionBrain, UserManager $userManager, RelationsModel $relationsModel)
    {
        $this->graphManager = $graphManager;
        $this->connectionBrain = $connectionBrain;
        $this->userManager = $userManager;
        $this->relationsModel = $relationsModel;
    }

    public function contactFrom($id)
    {
        $messaged = $this->getMessaged($id);

        $qb = $this->graphManager->createQueryBuilder();

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
        $users = $this->userManager->buildMany($result);

        return $users;
    }

    public function contactTo($id)
    {
        $messaged = $this->getMessaged($id);

        $qb = $this->graphManager->createQueryBuilder();

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
        $users = $this->userManager->buildMany($result);

        return $users;
    }

    public function canContact($from, $to)
    {
        $qb = $this->relationsModel->getCanContactQuery($from, $to);
        $result = $qb->getQuery()->getResultSet();

        return $result->count() === 0;
    }

    protected function getMessaged($id)
    {
        $messaged = $this->fetchMessagedByUser($id);
        $messaged = $this->castToInteger($messaged);

        return $messaged;
    }

    /**
     * @param $id
     * @return array
     */
    protected function fetchMessagedByUser($id)
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

        return $messaged;
    }

    /**
     * @param $messaged
     * @return array
     */
    protected function castToInteger($messaged)
    {
        $messaged = array_map(
            function ($i) {
                return (integer)$i;
            },
            $messaged
        );

        return $messaged;
    }
}