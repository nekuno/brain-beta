<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Model\Exception\ValidationException;
use Model\Neo4j\Neo4jException;
use Model\Group\GroupManager;
use Model\Thread\ThreadManager;
use Model\User\UserManager;
use Service\RecommendatorService;
use Service\ThreadService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UsersThreadsCreateCommand extends ApplicationAwareCommand
{
    protected function configure()
    {
        $this->setName('users:threads:create')
            ->setDescription('Creates threads for users')
            ->addArgument('scenario', InputArgument::REQUIRED, sprintf('Set of threads to add. Options available: "%s"', implode('", "', ThreadManager::$scenarios)))
            ->addOption('groups', null, InputOption::VALUE_NONE, 'Add threads for each group belonged to')
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Delete existing threads before creating new ones', null)
            ->addOption('all', null, InputOption::VALUE_NONE, 'Create them to all users', null)
            ->addOption('userId', null, InputOption::VALUE_REQUIRED, 'Id of thread owner', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $scenario = $input->getArgument('scenario');
        $groupsOption = $input->getOption('groups');
        $clear = $input->getOption('clear');
        $all = $input->getOption('all');
        $userId = $input->getOption('userId');

        if (!in_array($scenario, ThreadManager::$scenarios)) {
            $output->writeln(sprintf('Scenario not valid. Available scenarios: "%s".', implode('", "', ThreadManager::$scenarios)));

            return;
        }

        if (!($all || $userId)) {
            $output->writeln('Please specify userId or all users');

            return;
        }

        /* @var $userManager UserManager */
        $userManager = $this->app['users.manager'];

        $users = array();
        if ($all) {
            $users = $userManager->getAll();
        } else {
            if ($userId) {
                $users = array($userManager->getById($userId, true));
            }
        }

        $output->writeln(sprintf('Starting process for %d users', count($users)));

        /* @var $threadManager ThreadManager */
        $threadManager = $this->app['users.threads.manager'];
        /** @var ThreadService $threadService */
        $threadService = $this->app['threads.service'];
        /* @var $recommendator RecommendatorService */
        $recommendator = $this->app['recommendator.service'];
        /* @var $groupModel GroupManager */
        $groupModel = $this->app['users.groups.model'];

        foreach ($users as $user) {

            if ($user->isGuest()) {
                continue;
            }

            $output->writeln('-----------------------------------------------------------------------');

            $userId = $user->getId();
            if ($clear) {
                $existingThreads = $threadManager->getAllByUserId($userId);
                foreach ($existingThreads as $existingThread) {
//                    if ($existingThread->getDefault() == true) {
                    $threadManager->deleteById($existingThread->getId());
//                    }
                }
                $output->writeln(sprintf('Deleted threads for user %d', $userId));
            }

            if ($groupsOption) {
                $groups = $groupModel->getAllByUserId($userId);

                foreach ($groups as $group) {
                    $threadService->createGroupThread($group, $userId);
                }
                $output->writeln(sprintf('Created %d group threads for user %d', count($groups), $userId));
            }

            try {
                $createdThreads = $threadService->createDefaultThreads($userId, $scenario);
                $output->writeln('Added threads for scenario ' . $scenario . ' and user with id ' . $userId);
                foreach ($createdThreads as $createdThread) {

                    $result = $recommendator->getRecommendationFromThread($createdThread);

                    $threadService->cacheResults(
                        $createdThread,
                        array_slice($result['items'], 0, 5),
                        $result['pagination']['total']
                    );
                }
            } catch (\Exception $e) {
                $output->writeln('--------------EXCEPTION:');
                $output->writeln($e->getTraceAsString());
                $output->writeln($e->getMessage());
                if ($e instanceof ValidationException) {
                    $output->writeln(print_r($e->getErrors(), true));
                }
                if ($e instanceof Neo4jException) {
                    $output->writeln($e->getQuery());
                }
            }
            $output->writeln(sprintf('Cached results from threads for user %d', $userId));
        }
    }
}