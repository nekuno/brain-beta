<?php

namespace Service;

use Manager\PhotoManager;
use Manager\UserManager;
use Model\User\ProfileModel;
use Model\User\Rate\RateModel;
use Model\User\Token\TokensModel;
use Model\User\Token\TokenStatus\TokenStatusManager;

class UserService
{
    protected $userManager;
    protected $profileManager;
    protected $tokensModel;
    protected $tokenStatusManager;
    protected $rateModel;
    protected $linkService;
    protected $instantConnection;
    protected $photoManager;

    /**
     * UserService constructor.
     * @param UserManager $userManager
     * @param ProfileModel $profileManager
     * @param TokensModel $tokensModel
     * @param TokenStatusManager $tokenStatusManager
     * @param RateModel $rateModel
     * @param LinkService $linkService
     * @param InstantConnection $instantConnection
     * @param PhotoManager $photoManager
     */
    public function __construct(UserManager $userManager, ProfileModel $profileManager, TokensModel $tokensModel, TokenStatusManager $tokenStatusManager, RateModel $rateModel, LinkService $linkService, InstantConnection $instantConnection, PhotoManager $photoManager)
    {
        $this->userManager = $userManager;
        $this->profileManager = $profileManager;
        $this->tokensModel = $tokensModel;
        $this->tokenStatusManager = $tokenStatusManager;
        $this->rateModel = $rateModel;
        $this->linkService = $linkService;
        $this->instantConnection = $instantConnection;
        $this->photoManager = $photoManager;
    }

    public function createUser(array $userData, array $profileData)
    {
        //TODO: Extract createUserPhoto to here
        $user = $this->userManager->create($userData);
        $this->profileManager->create($user->getId(), $profileData);

        return $user;
    }

    public function deleteUser($userId)
    {
        $messagesData = array('userId' => $userId);
        $this->instantConnection->deleteMessages($messagesData);

        $user = $this->userManager->getById($userId);
        $photoId = $user->getPhoto()->getId();
        if ($photoId)
        {
            $this->photoManager->remove($photoId);
        }

        $this->tokenStatusManager->removeAll($userId);
        $this->tokensModel->removeAll($userId);

        $deletedLikesUrls = $this->rateModel->deleteAllLinksByUser($userId);
        $this->linkService->deleteNotLiked($deletedLikesUrls);

        $this->profileManager->remove($userId);

        $this->userManager->delete($userId);

        return $user;
    }

}