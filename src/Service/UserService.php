<?php

namespace Service;

use Model\User\Photo\GalleryManager;
use Model\User\Photo\PhotoManager;
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
    protected $galleryManager;

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
     * @param GalleryManager $galleryManager
     */
    public function __construct(UserManager $userManager, ProfileModel $profileManager, TokensModel $tokensModel, TokenStatusManager $tokenStatusManager, RateModel $rateModel, LinkService $linkService, InstantConnection $instantConnection, PhotoManager $photoManager, GalleryManager $galleryManager)
    {
        $this->userManager = $userManager;
        $this->profileManager = $profileManager;
        $this->tokensModel = $tokensModel;
        $this->tokenStatusManager = $tokenStatusManager;
        $this->rateModel = $rateModel;
        $this->linkService = $linkService;
        $this->instantConnection = $instantConnection;
        //TODO: Move to PhotoService and remove USerManager->PhotoManager dependencies
        $this->photoManager = $photoManager;
        $this->galleryManager = $galleryManager;
    }

    public function createUser(array $userData, array $profileData)
    {
        //TODO: Extract createUserPhoto to here
        $user = $this->userManager->create($userData);
        $this->profileManager->create($user->getId(), $profileData);

        return $user;
    }

    public function updateUser(array $userData)
    {
        $this->updateEnabled($userData);
        $user = $this->userManager->update($userData);

        return $user;
    }
    
    protected function updateEnabled(array $userData)
    {
        $userId = $userData['userId'];
        $user = $this->userManager->getById($userId);

        if ($user->isEnabled() !== $userData['enabled'])
        {
            $fromAdmin = true;
            $this->userManager->setEnabled($userId, $userData['enabled'], $fromAdmin);
        }
    }

    public function deleteUser($userId)
    {
        $messagesData = array('userId' => $userId);
//        $this->instantConnection->deleteMessages($messagesData);

        $user = $this->userManager->getById($userId);
        $photoId = $user->getPhoto()->getId();
        if ($photoId)
        {
            $this->photoManager->remove($photoId);
        }

        $this->galleryManager->deleteAllFromUser($user);

        $this->tokenStatusManager->removeAll($userId);
        $this->tokensModel->removeAll($userId);

        $deletedLikesUrls = $this->rateModel->deleteAllLinksByUser($userId);
        $this->linkService->deleteNotLiked($deletedLikesUrls);

        $this->profileManager->remove($userId);

        $this->userManager->delete($userId);

        return $user;
    }

    public function getOneUser($userId)
    {
        $user = $this->userManager->getById($userId);

        return $user;
    }

}