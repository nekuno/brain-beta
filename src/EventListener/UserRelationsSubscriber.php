<?php

namespace EventListener;

use Event\UserLikedEvent;
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
     * @var string
     */
    protected $host;

    /**
     * @var UserManager
     */
    protected $userManager;

    public function __construct(Client $client, UserManager $userManager, $host)
    {
        $this->client = $client;
        $this->userManager = $userManager;
        $this->host = $host;
    }

    public static function getSubscribedEvents()
    {
        return array(
            \AppEvents::USER_LIKED => array('onUserLiked'),
        );
    }

    public function onUserLiked(UserLikedEvent $event)
    {
        $userFromId = $event->getUserFromId();
        $userFrom = $this->userManager->getById($userFromId);

        $json = array(
            'userId' => $event->getUserToId(),
            'data' => array(
                'type' => 'user_liked',
                'slug' => $userFrom->getSlug(),
                'username' => $userFrom->getUsername(),
                'photo' => $userFrom->getPhoto(),
            ),
        );
        try {
            $this->client->post($this->host . 'api/notification', array('json' => $json));
        } catch (RequestException $e) {

        }
    }
}