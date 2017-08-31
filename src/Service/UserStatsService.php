<?php

namespace Service;

use Model\User\Content\ContentPaginatedModel;
use Model\User\Group\GroupModel;
use Model\User\RelationsModel;
use Model\User\Shares\Shares;
use Model\User\Shares\SharesManager;
use Model\User\Stats\UserStats;
use Model\User\Stats\UserStatsCalculator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserStatsService
{
    /**
     * @var UserStatsCalculator
     */
    protected $userStatsCalculator;

    /**
     * @var RelationsModel
     */
    protected $relationsModel;

    /**
     * @var GroupModel
     */
    protected $groupModel;

    /**
     * @var ContentPaginatedModel
     */
    protected $contentPaginatedModel;

    /**
     * @var SharesManager
     */
    protected $sharesManager;

    function __construct(
        UserStatsCalculator $userStatsManager,
        GroupModel $groupModel,
        RelationsModel $relationsModel,
        ContentPaginatedModel $contentPaginatedModel,
        SharesManager $sharesManager
    ) {
        $this->userStatsCalculator = $userStatsManager;
        $this->groupModel = $groupModel;
        $this->relationsModel = $relationsModel;
        $this->contentPaginatedModel = $contentPaginatedModel;
    }

    public function getStats($userId)
    {
        $stats = $this->userStatsCalculator->calculateStats($userId);
        $this->completeStats($stats, $userId);

        return $stats;
    }

    protected function completeStats(UserStats $userStats, $userId)
    {
        $this->completeReceivedLikes($userStats, $userId);
        $this->completeUserLikes($userStats, $userId);
        $this->completeGroups($userStats, $userId);
        $this->completeContentLikes($userStats, $userId);
    }

    protected function completeReceivedLikes(UserStats $userStats, $userId)
    {
        $numberOfReceivedLikes = $this->relationsModel->countTo($userId, RelationsModel::LIKES);
        $userStats->setNumberOfReceivedLikes((integer)$numberOfReceivedLikes);
    }

    protected function completeUserLikes(UserStats $userStats, $userId)
    {
        $numberOfUserLikes = $this->relationsModel->countFrom($userId, RelationsModel::LIKES);
        $userStats->setNumberOfUserLikes((integer)$numberOfUserLikes);
    }

    protected function completeGroups(UserStats $userStats, $userId)
    {
        $groups = $this->groupModel->getAllByUserId($userId);
        $userStats->setGroupsBelonged($groups);
    }

    protected function completeContentLikes(UserStats $userStats, $userId)
    {
        $contentLikes = $this->contentPaginatedModel->countAll($userId);
        $userStats->setNumberOfContentLikes($contentLikes['Link']);
        $userStats->setNumberOfAudioLikes($contentLikes['Audio']);
        $userStats->setNumberOfImageLikes($contentLikes['Image']);
        $userStats->setNumberOfVideoLikes($contentLikes['Video']);
    }

    public function getComparedStats($userId, $otherUserId)
    {
        if (null === $otherUserId) {
            throw new NotFoundHttpException('User not found');
        }

        if ($userId === $otherUserId) {
            throw new \InvalidArgumentException('Cannot get compared stats between an user and themselves');
        }

        return $this->userStatsCalculator->calculateComparedStats($userId, $otherUserId);
    }

    public function updateTopLinks($userId1, $userId2)
    {
        $topLinks = $this->userStatsCalculator->calculateTopLinks($userId1, $userId2);

        $shares = new Shares();
        $shares->setTopLinks($topLinks);

        return $this->sharesManager->merge($userId1, $userId2, $shares);
    }
}