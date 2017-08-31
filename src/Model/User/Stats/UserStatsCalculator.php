<?php

namespace Model\User\Stats;

use ApiConsumer\Images\ImageAnalyzer;
use Everyman\Neo4j\Query\Row;
use Model\Neo4j\GraphManager;
use Model\User\Group\Group;
use Model\User\UserComparedStats;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserStatsCalculator
{
    /**
     * @var GraphManager
     */
    protected $graphManager;

    /**
     * @var ImageAnalyzer
     */
    protected $imageAnalyzer;

    function __construct(GraphManager $graphManager, ImageAnalyzer $imageAnalyzer)
    {
        $this->graphManager = $graphManager;
        $this->imageAnalyzer = $imageAnalyzer;
    }

    public function calculateStats($id)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(u:User {qnoow_id: { id }})')
            ->setParameter('id', (integer)$id)
            ->with('u')
            ->optionalMatch('(u)-[r:ANSWERS]->(:Answer)')
            ->returns('count(r) AS questionsAnswered', 'u.available_invitations AS available_invitations');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException('User not found');
        }

        /* @var $row Row */
        $row = $result->current();

        $userStats = new UserStats();
        $userStats->setNumberOfQuestionsAnswered($row->offsetGet('questionsAnswered'));
        $userStats->setAvailableInvitations($row->offsetGet('available_invitations'));

        return $userStats;

    }

    /**
     * @param $id1
     * @param $id2
     * @return UserComparedStats
     * @throws \Exception
     */
    public function calculateComparedStats($id1, $id2)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->setParameters(
            array(
                'id1' => (integer)$id1,
                'id2' => (integer)$id2
            )
        );

        $qb->match('(u:User {qnoow_id: { id1 }}), (u2:User {qnoow_id: { id2 }})')
            ->optionalMatch('(u)-[:BELONGS_TO]->(g:Group)<-[:BELONGS_TO]-(u2)')
            ->with('u', 'u2', 'collect(distinct g) AS groupsBelonged')
            ->optionalMatch('(u)-[:TOKEN_OF]-(token:Token)')
            ->with('u', 'u2', 'groupsBelonged', 'collect(distinct token.resourceOwner) as resourceOwners')
            ->optionalMatch('(u2)-[:TOKEN_OF]-(token2:Token)');
        $qb->with('u, u2', 'groupsBelonged', 'resourceOwners', 'collect(distinct token2.resourceOwner) as resourceOwners2')
            ->optionalMatch('(u)-[:LIKES]->(link:Link)')
            ->where('(u2)-[:LIKES]->(link)', 'link.processed = 1', 'NOT link:LinkDisabled')
            ->with('u', 'u2', 'groupsBelonged', 'resourceOwners', 'resourceOwners2', 'count(distinct(link)) AS commonContent')
            ->optionalMatch('(u)-[:ANSWERS]->(answer:Answer)')
            ->where('(u2)-[:ANSWERS]->(answer)')
            ->returns('groupsBelonged, resourceOwners, resourceOwners2, commonContent, count(distinct(answer)) as commonAnswers');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        /* @var $row Row */
        $row = $result->current();

        $groups = array();
        foreach ($row->offsetGet('groupsBelonged') as $groupNode) {
            $groups[] = Group::createFromNode($groupNode);
        }

        $resourceOwners = array();
        foreach ($row->offsetGet('resourceOwners') as $resourceOwner) {
            $resourceOwners[] = $resourceOwner;
        }
        $resourceOwners2 = array();
        foreach ($row->offsetGet('resourceOwners2') as $resourceOwner2) {
            $resourceOwners2[] = $resourceOwner2;
        }

        $commonContent = $row->offsetGet('commonContent') ?: 0;
        $commonAnswers = $row->offsetGet('commonAnswers') ?: 0;

        $userStats = new UserComparedStats(
            $groups,
            $resourceOwners,
            $resourceOwners2,
            $commonContent,
            $commonAnswers
        );

        return $userStats;
    }

    public function calculateTopLinks($userId1, $userId2)
    {
        $amountDesired = 3;
        $excluded = array();
        $workingThumbnails = array();
        do {
            list($newThumbnails, $linkIds) = $this->getTopLinks($userId1, $userId2, $excluded);

            $workingThumbnails += $this->getWorkingThumbnails($newThumbnails);
            $excluded += $linkIds;

            $enoughResults = count($workingThumbnails) >= $amountDesired;
            $moreResultsAvailable = count($newThumbnails) !== 0;
        } while (!$enoughResults && $moreResultsAvailable);

        $workingThumbnails = array_slice($workingThumbnails, 0, $amountDesired);

        return $workingThumbnails;
    }

    /**
     * @param $userId1
     * @param $userId2
     * @param array $excludedIds
     * @return array
     */
    protected function getTopLinks($userId1, $userId2, $excludedIds)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(u1:User{qnoow_id: {id1}})', '(u2:User{qnoow_id: {id2}})')
            ->setParameter('id1', (integer)$userId1)
            ->setParameter('id2', (integer)$userId2)
            ->with('u1', 'u2');

        $qb->match('(u1)-[:LIKES]->(video:Video)<-[:LIKES]-(u2)')
            ->with('u1', 'u2', 'collect(video) AS links');
        $qb->match('(u1)-[:LIKES]->(audio:Audio)<-[:LIKES]-(u2)')
            ->with('u1', 'u2', 'links', 'collect(audio) AS audios')
            ->with('u1', 'u2', 'links + audios AS links');
        $qb->match('(u1)-[:LIKES]->(creator:Creator)<-[:LIKES]-(u2)')
            ->with('u1', 'u2', 'links', 'collect(creator) AS creators')
            ->with('u1', 'u2', 'links + creators AS links');

        $qb->unwind('links AS link')
            ->match('(link)-[:HAS_POPULARITY]->(p:Popularity)')
            ->where('link.processed = 1', 'EXISTS link.thumbnail', 'EXISTS p.popularity', 'p.popularity > 0', 'NOT id(link) IN {excluded}')
            ->setParameter('excluded', $excludedIds)
            ->with('link.thumbnail AS thumbnail', 'id(link) AS linkId', 'p.popularity AS popularity');

        $qb->returns('collect(thumbnail) AS thumbnails', 'collect(linkId) AS linkIds')
            ->orderBy('popularity ASC')
            ->limit(3);

        $result = $qb->getQuery()->getResultSet();

        $thumbnails = $result->offsetGet('thumbnails');
        $linkIds = $result->offsetGet('linkIds');

        return array($thumbnails, $linkIds);
    }

    protected function getWorkingThumbnails(array $thumbnails)
    {
        $workingThumbnails = array();
        foreach ($thumbnails as $key => $thumbnail) {
            $imageResponse = $this->imageAnalyzer->buildResponse($thumbnail);
            if (!$imageResponse->isValid()) {
                $workingThumbnails[] = $thumbnail;
            }
        }

        return $workingThumbnails;
    }
}