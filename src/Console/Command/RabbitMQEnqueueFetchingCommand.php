<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Model\User\User;
use Model\User\Token\TokensManager;
use Service\AMQPManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RabbitMQEnqueueFetchingCommand extends ApplicationAwareCommand
{

    protected function configure()
    {
        $this->setName('rabbitmq:enqueue:fetching')
            ->setDescription('Enqueues a fetching task for all users')
            ->addOption(
                'user',
                null,
                InputOption::VALUE_OPTIONAL,
                'If set, only will enqueue fetching process for given user'
            )->addOption(
                'resource',
                null,
                InputOption::VALUE_OPTIONAL,
                'If set, only will enqueue fetching process for given resource owner'
            )->addOption(
                'public',
                null,
                InputOption::VALUE_NONE,
                'Fetch as Nekuno instead of as the user'
            )->addOption(
                'limit',
                null,
                InputOption::VALUE_OPTIONAL,
                'Users limit'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $userIdOption = $input->getOption('user');
        $resourceOwnerOption = $input->getOption('resource');
        $public = $input->getOption('public');
        $limit = $input->getOption('limit');

        if (!$this->isValidResourceOwner($resourceOwnerOption)) {
            $output->writeln(sprintf('%s is not a valid resource owner', $resourceOwnerOption));
            exit;
        }

        $messages = $this->getMessages($userIdOption, $resourceOwnerOption, $public, $limit, $output);
        $this->enqueueMessages($messages, $output);
    }

    private function isValidResourceOwner($resourceOwnerOption)
    {
        $availableResourceOwners = TokensManager::getResourceOwners();

        return $resourceOwnerOption == null || in_array($resourceOwnerOption, $availableResourceOwners);
    }

    private function getMessages($userIdOption, $resourceOwnerOption, $public, $limit, OutputInterface $output)
    {
        if ($userIdOption && $resourceOwnerOption){
            $messages = array($this->buildMessage($userIdOption, $resourceOwnerOption, $public));
        } else {
            $users = $this->getUsers($userIdOption, $limit, $output);
            $messages = $this->buildMessages($users, $resourceOwnerOption, $public);
        }

        return $messages;
    }

    private function getUsers($userIdOption, $limit, OutputInterface $output)
    {
        $usersModel = $this->app['users.manager'];

        try {
            return null == $userIdOption ? $usersModel->getAll(false, $limit) : array($usersModel->getById($userIdOption));
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
            exit;
        }
    }

    private function getResourceOwners($resourceOwnerOption, $userId)
    {
        /** @var TokensManager $tokensModel */
        $tokensModel = $this->app['users.tokens.model'];
        $connectedNetworks = $tokensModel->getConnectedNetworks($userId);

        if (null == $resourceOwnerOption) {
            return $connectedNetworks;
        }

        return in_array($resourceOwnerOption, $connectedNetworks) ? array($resourceOwnerOption) : array();
    }

    /**
     * @param $messages
     * @param OutputInterface $output
     */
    protected function enqueueMessages($messages, OutputInterface $output)
    {
        /* @var $amqpManager AMQPManager */
        $amqpManager = $this->app['amqpManager.service'];
        foreach ($messages as $message) {
            $output->writeln(sprintf('Enqueuing resource %s for user %d', $message['resourceOwner'], $message['userId']));
            $amqpManager->enqueueRefetching($message);
        }
    }

    /**
     * @param $users User[]
     * @param $resourceOwnerOption
     * @param $public
     * @return array
     */
    private function buildMessages($users, $resourceOwnerOption, $public)
    {
        $messages = array();
        foreach ($users as $user) {
            $resourceOwners = $this->getResourceOwners($resourceOwnerOption, $user->getId());

            foreach ($resourceOwners as $resourceOwner) {
                $messages[] = $this->buildMessage($user->getId(), $resourceOwner, $public);
            }
        }

        return $messages;
    }

    private function buildMessage($userId, $resourceOwner, $public)
    {
        return array(
            'userId' => $userId,
            'resourceOwner' => $resourceOwner,
            'public' => $public,
        );
    }
}