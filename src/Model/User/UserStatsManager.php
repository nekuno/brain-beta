<?php

namespace Model\User;

use Everyman\Neo4j\Query\Row;
use Model\Neo4j\GraphManager;
use Model\User\Group\GroupModel;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserStatsManager
{
    /**
     * @var GraphManager
     */
    protected $graphManager;

    /**
     * @var RelationsModel
     */
    protected $relationsModel;

    /**
     * @var GroupModel
     */
    protected $groupModel;

    function __construct(GraphManager $graphManager,
                         GroupModel $groupModel,
                         RelationsModel $relationsModel)
    {
        $this->graphManager = $graphManager;
        $this->groupModel = $groupModel;
        $this->relationsModel = $relationsModel;
    }

    public function getStats($id)
    {

        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(u:User {qnoow_id: { id }})')
            ->setParameter('id', (integer)$id)
            ->with('u')
            ->optionalMatch('(u)-[r:LIKES]->(:Link)')
            ->with('u,count(r) AS contentLikes')
            ->optionalMatch('(u)-[r:LIKES]->(:Video)')
            ->with('u,contentLikes,count(r) AS videoLikes')
            ->optionalMatch('(u)-[r:LIKES]->(:Audio)')
            ->with('u,contentLikes,videoLikes,count(r) AS audioLikes')
            ->optionalMatch('(u)-[r:LIKES]->(:Image)')
            ->with('u, contentLikes, videoLikes, audioLikes, count(r) AS imageLikes')
            ->optionalMatch('(u)-[r:ANSWERS]->(:Answer)')
            ->returns('contentLikes', 'videoLikes', 'audioLikes', 'imageLikes', 'count(r) AS questionsAnswered', 'u.available_invitations AS available_invitations');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException('User not found');
        }

        /* @var $row Row */
        $row = $result->current();

        $numberOfReceivedLikes = $this->relationsModel->countTo($id, RelationsModel::LIKES);
        $numberOfUserLikes = $this->relationsModel->countFrom($id, RelationsModel::LIKES);

        $groups = $this->groupModel->getAllByUserId($id);

        $userStats = new UserStatsModel(
            $row->offsetGet('contentLikes'),
            $row->offsetGet('videoLikes'),
            $row->offsetGet('audioLikes'),
            $row->offsetGet('imageLikes'),
            (integer)$numberOfReceivedLikes,
            (integer)$numberOfUserLikes,
            $groups,
            $row->offsetGet('questionsAnswered'),
            $row->offsetGet('available_invitations')
        );

        return $userStats;

    }


}