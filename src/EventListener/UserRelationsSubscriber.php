<?php

namespace EventListener;

use Event\UserBothLikedEvent;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Manager\UserManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserRelationsSubscriber implements EventSubscriberInterface
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var UserManager
     */
    protected $userManager;

    public function __construct(Client $client, UserManager $userManager)
    {
        $this->client = $client;
        $this->userManager = $userManager;
    }

    public static function getSubscribedEvents()
    {
        return array(
            \AppEvents::USER_BOTH_LIKED => array('onUserBothLiked'),
        );
    }

    public function onUserBothLiked(UserBothLikedEvent $event)
    {
        $userFromId = $event->getUserFromId();
        $userFrom = $this->userManager->getById($userFromId);
        $userToId = $event->getUserToId();
        $userTo = $this->userManager->getById($userToId);

        $jsonTo = array(
            'userId' => $userToId,
            'category' => 'user_both_liked',
            'data' => array(
                'slug' => $userFrom->getSlug(),
                'username' => $userFrom->getUsername(),
                'photo' => $userFrom->getPhoto(),
            ),
        );
        $jsonFrom = array(
            'userId' => $event->getUserFromId(),
            'category' => 'user_both_liked',
            'data' => array(
                'slug' => $userTo->getSlug(),
                'username' => $userTo->getUsername(),
                'photo' => $userTo->getPhoto(),
            ),
        );
        try {
            $this->client->post('api/notification', array('json' => $jsonTo));
            $this->client->post('api/notification', array('json' => $jsonFrom));
        } catch (RequestException $e) {

        }
    }
}