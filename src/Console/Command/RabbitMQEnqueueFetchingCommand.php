<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Model\User;
use Model\User\Token\TokensModel;
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $userId = $input->getOption('user');
        $resourceOwnerOption = $input->getOption('resource');
        $public = $input->getOption('public');

        if (!$this->isValidResourceOwner($resourceOwnerOption)) {
            $output->writeln(sprintf('%s is not an valid resource owner', $resourceOwnerOption));
            exit;
        }

        $resourceOwners = $this->getResourceOwners($resourceOwnerOption);

        try{
            $users = $this->getUsers($userId);
        } catch (\Exception $e){
            $output->writeln($e->getMessage());
            exit;
        }

        /* @var $amqpManager AMQPManager */
        $amqpManager = $this->app['amqpManager.service'];

        foreach ($users as $user) {
            /* @var $user User */
            foreach ($resourceOwners as $name) {
                $data = array(
                    'userId' => $user->getId(),
                    'resourceOwner' => $name,
                    'public' => $public,
                );
                $output->writeln(sprintf('Enqueuing resource %s for user %d', $name, $user->getId()));
                $amqpManager->enqueueRefetching($data);
            }
        }
    }

    private function isValidResourceOwner($resourceOwnerOption)
    {
        $availableResourceOwners = TokensModel::getResourceOwners();

        return $resourceOwnerOption == null || in_array($resourceOwnerOption, $availableResourceOwners);
    }

    private function getResourceOwners($resourceOwner)
    {
        $availableResourceOwners = TokensModel::getResourceOwners();

        return $resourceOwner ? array($resourceOwner) : $availableResourceOwners;
    }

    private function getUsers($userId)
    {
        $usersModel = $this->app['users.manager'];

        return null == $userId ? $usersModel->getAll() : $usersModel->getById($userId);
    }

}