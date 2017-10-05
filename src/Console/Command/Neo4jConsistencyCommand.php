<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use EventListener\ExceptionLoggerSubscriber;
use Model\User;
use Model\User\ProfileModel;
use Manager\UserManager;
use Service\Consistency\ConsistencyCheckerService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Neo4jConsistencyCommand extends ApplicationAwareCommand
{

    protected function configure()
    {
        $this->setName('neo4j:consistency')
            ->setDescription('Detects database consistency')
            ->addOption('status', null, InputOption::VALUE_NONE, 'Check users status', null)
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Check only nodes with that label', null)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Solve problems where possible', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $force = $input->getOption('force');
        $status = $input->getOption('status');
        $label = $input->getOption('label');

//        $output->writeln('Getting user list.');
//        /** @var UserManager $userManager */
//        $userManager = $this->app['users.manager'];

//        $users = $userManager->getAll();
//        $output->writeln('Got ' . count($users) . ' users.');

        //checking status

//        if ($status) {
//            $this->checkStatus($users, $force, $output);
//        }
//
//        if ($profile) {
//            $this->checkProfile($users, $force, $output);
//        }

//        $dispatcher = $this->app['dispatcher.service'];
//        $dispatcher->addSubscriber(new ExceptionLoggerSubscriber($this->app['monolog']));

        /** @var ConsistencyCheckerService $consistencyChecker */
        $consistencyChecker = $this->app['consistency.service'];

        $errors = $consistencyChecker->checkDatabase($label);

        foreach ($errors as $message => $ids)
        {
            $output->writeln($message);
            $output->writeln(json_encode($ids));
        }
//        foreach ($users as $user){
//            try{
//                $this->checkUser($user);
//                $output->writeln(sprintf('user %d checked', $user->getId()));
//            } catch(ValidationException $e) {
//                var_dump($e->getErrors());
//            }
//        }

        $output->writeln('Finished.');
    }

    /**
     * @param $users array
     * @param $force boolean
     * @param $output OutputInterface
     */
    private function checkStatus($users, $force, $output)
    {
        /** @var UserManager $userManager */
        $userManager = $this->app['users.manager'];

        $output->writeln('Checking users status.');

        $userStatusChanged = array();
        foreach ($users as $user) {
            /* @var $user User */
            try {
                $status = $userManager->calculateStatus($user->getId(), $force);

                if ($status->getStatusChanged()) {

                    $userStatusChanged[$user->getId()] = $status->getStatus();

                }
            } catch (\Exception $e) {
                $output->writeln(sprintf('ERROR: Fail to calculate status for user %d', $user->getId()));
            }

        }

        foreach ($userStatusChanged as $userId => $newStatus) {
            if ($force) {
                $output->writeln(sprintf('SUCCESS: User %d had their status changed to %s', $userId, $newStatus));
            } else {
                $output->writeln(sprintf('User %d needs their status to be changed to %s', $userId, $newStatus));
            }
        }

        if ($force) {
            $output->writeln(sprintf('%d new statuses updated', count($userStatusChanged)));
        } else {
            $output->writeln(sprintf('%d new statuses need to be updated', count($userStatusChanged)));
        }

    }

    /**
     * @param $users array
     * @param $force boolean
     * @param $output OutputInterface
     */
    private function checkProfile($users, $force, $output)
    {
        /** @var ProfileModel $profileModel */
        $profileModel = $this->app['users.profile.model'];
        foreach ($users as $user) {
            /* @var $user User */
            try {
                $profile = $profileModel->getById($user->getId());
            } catch (NotFoundHttpException $e) {
                $output->writeln(sprintf('Profile for user with id %d not found.', $user->getId()));
                if ($force) {
                    $output->writeln(sprintf('Creating profile for user %d.', $user->getId()));
                    $profile = $profileModel->create(
                        $user->getId(),
                        array(
                            'birthday' => '1970-01-01',
                            'gender' => 'male',
                            'orientation' => array('heterosexual'),
                            'interfaceLanguage' => 'es',
                            'location' => array(
                                'latitude' => 40.4167754,
                                'longitude' => -3.7037902,
                                'address' => 'Madrid',
                                'locality' => 'Madrid',
                                'country' => 'Spain'
                            )
                        )
                    );
                    $output->writeln(sprintf('SUCCESS: Created profile for user %d.', $user->getId()));
                }
            }

            if (isset($profile) && is_array($profile)) {
                $output->writeln(sprintf('Found profile for user %d.', $user->getId()));
            }

        }
    }

    public function checkUser(User $user)
    {
        $qb = $this->app['neo4j.graph_manager']->createQueryBuilder();

        $qb->match('(u:User{qnoow_id: {userId}})')
            ->setParameter('userId', $user->getId())
            ->returns('u');

        $result = $qb->getQuery()->getResultSet();

        $userNode = $result->current()->offsetGet('u');

        $this->app['consistency.service']->checkNode($userNode);
    }

}