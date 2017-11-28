<?php

namespace Service;

use Manager\PhotoManager;
use Manager\UserManager;
use Model\User\ProfileModel;

class UserService
{
    protected $userManager;
    protected $profileManager;
    protected $instantConnection;
    protected $photoManager;

    /**
     * UserService constructor.
     * @param UserManager $userManager
     * @param ProfileModel $profileManager
     * @param InstantConnection $instantConnection
     */
    public function __construct(UserManager $userManager, ProfileModel $profileManager, InstantConnection $instantConnection, PhotoManager $photoManager)
    {
        $this->userManager = $userManager;
        $this->profileManager = $profileManager;
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
        $user = $this->userManager->getById($userId);

        $messagesData = array('userId' => $userId);
        $this->instantConnection->deleteMessages($messagesData);

        $photoId = $user->getPhoto()->getId();
        $this->photoManager->remove($photoId);

        $this->profileManager->remove($userId);

        $this->userManager->delete($userId);
    }

}