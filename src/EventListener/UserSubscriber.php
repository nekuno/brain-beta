<?php

namespace EventListener;

use Event\GroupEvent;
use Event\ProfileEvent;
use Event\UserEvent;
use Event\UserTrackingEvent;
use GuzzleHttp\Exception\RequestException;
use Model\User\Thread\ThreadManager;
use Model\User\UserTrackingModel;
use Service\ChatMessageNotifications;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserSubscriber implements EventSubscriberInterface
{
    /**
     * @var ThreadManager
     */
    protected $threadManager;

    protected $chat;

    protected $userTrackingModel;

    public function __construct(ThreadManager $threadManager, ChatMessageNotifications $chat, UserTrackingModel $userTrackingModel)
    {
        $this->threadManager = $threadManager;
        $this->chat = $chat;
        $this->userTrackingModel = $userTrackingModel;
    }

    public static function getSubscribedEvents()
    {
        return array(
            \AppEvents::USER_CREATED => array('onUserCreated'),
            \AppEvents::GROUP_ADDED => array('onGroupAdded'),
            \AppEvents::GROUP_REMOVED => array('onGroupRemoved'),
            \AppEvents::PROFILE_CREATED => array('onProfileCreated'),
            \AppEvents::USER_REGISTERED => array('onUserRegistered'),
        );
    }

    public function onUserCreated(UserEvent $event)
    {
    }

    public function onGroupAdded(GroupEvent $groupEvent)
    {
        $userId = $groupEvent->getUserId();
        $group = $groupEvent->getGroup();

        $this->threadManager->createGroupThread($group, $userId);
    }

    public function onGroupRemoved(GroupEvent $groupEvent)
    {
        $groupId = $groupEvent->getGroup()->getId();
        $userId = $groupEvent->getUserId();

//        $this->threadManager->deleteGroupThreads($userId, $groupId);
    }

    public function onProfileCreated(ProfileEvent $profileEvent)
    {
        $profile = $profileEvent->getProfile();
        $id = $profileEvent->getUserId();

        if (!$id || !$profile){
            return false;
        }

        $locale = isset($profile['interfaceLanguage']) ? $profile['interfaceLanguage'] : 'en';

        try {
            $this->chat->createDefaultMessage($id, $locale);
        } catch (RequestException $e) {
            return false;
        }

        return true;
    }

    public function onUserRegistered(UserTrackingEvent $event)
    {
        $user = $event->getUser();
        $threads = $this->threadManager->getDefaultThreads($user, ThreadManager::SCENARIO_DEFAULT_LITE);
        $this->threadManager->createBatchForUser($user->getId(), $threads);

        $data = json_encode($event->getData());
        $this->userTrackingModel->set($user->getId(), 'Registration success', 'Registration', null, $data);
    }
}