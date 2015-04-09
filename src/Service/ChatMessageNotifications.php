<?php

namespace Service;

use Model\Entity\EmailNotification;
use Doctrine\ORM\EntityManager;
use Model\User\ProfileModel;
use Model\UserModel;
use Doctrine\DBAL\Connection;
use Service\EmailNotifications;
use Silex\Translator;
use Silex\Application;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ChatMessageNotifications
 */
class ChatMessageNotifications
{

    /**
     * @var EmailNotifications
     */
    private $emailNotifications;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @var Connection
     */
    protected $driver;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var UserModel
     */
    protected $userModel;

    /**
     * @var ProfileModel
     */
    protected $profileModel;


    function __construct(EmailNotifications $emailNotifications, EntityManager $em, Connection $driver, Translator $translator,  UserModel $userModel, ProfileModel $profileModel)
    {
        $this->emailNotifications = $emailNotifications;
        $this->em = $em;
        $this->driver = $driver;
        $this->translator = $translator;
        $this->userModel = $userModel;
        $this->profileModel = $profileModel;
    }

    function sendUnreadChatMessages($limit = 99999, OutputInterface $output)
    {
        $usersIds = $this->getUsersWithUnreadMessages($limit);

        $output->writeln( count($usersIds) . ' users with unread messages found');

        foreach($usersIds as $userId)
        {
            $userId = (int) $userId['user_to'];

            $chatMessages = $this->getUnReadMessagesByUser($userId);

            if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                $output->writeln( count($chatMessages) . ' unread messages found for user ' . $userId );
            }

            $filteredChatMessages = $this->filterMessages($chatMessages);

            if (OutputInterface::VERBOSITY_VERY_VERBOSE <= $output->getVerbosity()) {
                foreach($filteredChatMessages as $message)
                {
                    $output->writeln( 'Message for user ' . $userId );
                    foreach($message as $messageData)
                    {
                        $output->writeln( $messageData );
                    }
                }
            }

            $user = $this->userModel->getById($userId);
            $profile = $this->profileModel->getById($userId);

            if(! $user)
            {
                throw new \Exception('User not found', 404);
            }

            if(isset($profile['options']['InterfaceLanguage']['id']) && $profile['options']['InterfaceLanguage']['id'])
            {
                $this->translator->setLocale($profile['options']['InterfaceLanguage']['id']);
            }

            $this->emailNotifications->send(EmailNotification::create()
                ->setType(EmailNotification::UNREAD_CHAT_MESSAGES)
                ->setUserId($userId)
                ->setRecipient($user['email'])
                ->setSubject($this->translator->trans('notifications.messages.unread.subject'))
                ->setInfo($this->saveInfo($user, $filteredChatMessages))
            );

            if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                $output->writeln( 'Email sent to user ' . $userId . ' with ' .  count($filteredChatMessages) . ' messages.' );
            }
        }
    }

    protected function filterMessages(array $chatMessages)
    {

        $usersFrom = array();
        $return = array();

        // Get users_from
        foreach($chatMessages as $chatMessage)
        {
            if(! in_array($chatMessage['user_from'], $usersFrom))
                $usersFrom[] = $chatMessage['user_from'];
        }

        // Get filtered messages
        foreach($usersFrom as $indexUser => $userFrom)
        {
            // Maximum 3 users
            if($indexUser > 3)
            {
                break;
            }

            foreach($chatMessages as $chatMessage)
            {
                if($chatMessage['user_from'] === $userFrom)
                {
                    $return[] = $chatMessage;

                    // Maximum 1 message per user
                    break;
                }
            }
        }

        return $return;

    }

    /**
     * Get users with unread chat messages (until 24h ago) (SOCIAL DB)
     *
     * @param int $limit
     * @return array
     */
    protected function getUsersWithUnreadMessages($limit = 999999999999)
    {
        $yesterday = new \DateTime('-1 day');
        $qb = $this->driver->createQueryBuilder('chat_message')
            ->select('chat_message.user_to')
            ->from('chat_message')
            ->where('chat_message.readed = 0')
            ->where('chat_message.createdAt > :yesterday')
            ->orderBy('chat_message.createdAt', 'asc')
            ->setMaxResults($limit)
            ->setParameter('yesterday', $yesterday->getTimestamp());

        return $qb->execute()->fetchAll();
    }

    /**
     * Get unread chat messages by user (until 24h ago) (SOCIAL DB)
     *
     * @param int $userId
     * @return array
     */
    protected function getUnReadMessagesByUser($userId)
    {
        $yesterday = new \DateTime('-1 day');
        $qb = $this->driver->createQueryBuilder('chat_message')
            ->select('*')
            ->from('chat_message')
            ->where('chat_message.readed = 0')
            ->where('chat_message.createdAt > :yesterday')
            ->where('chat_message.user_to = :user_to')
            ->groupBy('chat_message.user_from')
            ->orderBy('chat_message.createdAt', 'desc')
            ->setParameter('user_to', $userId)
            ->setParameter('yesterday', $yesterday->getTimestamp());

        return $qb->execute()->fetchAll();
    }

    protected function saveInfo(array $user, array $chatMessages)
    {
        foreach($chatMessages as $index => $chatMessage)
        {
            $chatMessages[$index]['username_from'] = $this->userModel->getById($chatMessage['user_from'])['username'];
        }

        return array(
            'unReadMessagesCount' => count($chatMessages),
            'username' => $user['username'],
            'email' => $user['email'],
            'messages' => $chatMessages
        );
    }

}