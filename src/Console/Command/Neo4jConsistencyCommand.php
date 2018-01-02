<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Service\Consistency\ConsistencyCheckerService;
use Service\Consistency\ConsistencyErrors\ConsistencyError;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Neo4jConsistencyCommand extends ApplicationAwareCommand
{

    protected function configure()
    {
        $this->setName('neo4j:consistency')
            ->setDescription('Detects database consistency')
            ->addOption('status', null, InputOption::VALUE_NONE, 'Check users status', null)
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Check only nodes with that label', null)
            ->addOption('solve', null, InputOption::VALUE_NONE, 'Solve problems where possible', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $solve = $input->getOption('solve');
        $label = $input->getOption('label');


        /** @var ConsistencyCheckerService $consistencyChecker */
        $consistencyChecker = $this->app['consistency.service'];

        $errors = $consistencyChecker->getDatabaseErrors($label);
        $this->outputErrors($errors, $output);

        if ($solve) {
            $solved = $consistencyChecker->solveDatabaseErrors($errors);
            $this->outputErrors($solved, $output);
        }

        $output->writeln('Finished.');
    }

    /**
     * @param ConsistencyError[] $errors
     * @param OutputInterface $output
     */
    private function outputErrors(array $errors, OutputInterface $output)
    {
        foreach ($errors as $error) {
            if ($error->isSolved()){
                $output->writeln('SOLVED: '. $error->getMessage());
            } else {
                $output->writeln($error->getMessage());
            }
        }
    }

    //TODO: Move to UserConsistencyChecker
    /**
     * @param $users array
     * @param $force boolean
     * @param $output OutputInterface
     */
//    private function checkStatus($users, $force, $output)
//    {
//        /** @var UserManager $userManager */
//        $userManager = $this->app['users.manager'];
//
//        $output->writeln('Checking users status.');
//
//        $userStatusChanged = array();
//        foreach ($users as $user) {
//            /* @var $user User */
//            try {
//                $status = $userManager->calculateStatus($user->getId(), $force);
//
//                if ($status->getStatusChanged()) {
//
//                    $userStatusChanged[$user->getId()] = $status->getStatus();
//
//                }
//            } catch (\Exception $e) {
//                $output->writeln(sprintf('ERROR: Fail to calculate status for user %d', $user->getId()));
//            }
//
//        }
//
//        foreach ($userStatusChanged as $userId => $newStatus) {
//            if ($force) {
//                $output->writeln(sprintf('SUCCESS: User %d had their status changed to %s', $userId, $newStatus));
//            } else {
//                $output->writeln(sprintf('User %d needs their status to be changed to %s', $userId, $newStatus));
//            }
//        }
//
//        if ($force) {
//            $output->writeln(sprintf('%d new statuses updated', count($userStatusChanged)));
//        } else {
//            $output->writeln(sprintf('%d new statuses need to be updated', count($userStatusChanged)));
//        }
//
//    }

    //TODO: Move to ProfileConsistencyChecker
    /**
     * @param $users array
     * @param $force boolean
     * @param $output OutputInterface
     */
//    private function checkProfile($users, $force, $output)
//    {
//        /** @var ProfileModel $profileModel */
//        $profileModel = $this->app['users.profile.model'];
//        foreach ($users as $user) {
//            /* @var $user User */
//            try {
//                $profile = $profileModel->getById($user->getId());
//            } catch (NotFoundHttpException $e) {
//                $output->writeln(sprintf('Profile for user with id %d not found.', $user->getId()));
//                if ($force) {
//                    $output->writeln(sprintf('Creating profile for user %d.', $user->getId()));
//                    $profile = $profileModel->create(
//                        $user->getId(),
//                        array(
//                            'birthday' => '1970-01-01',
//                            'gender' => 'male',
//                            'orientation' => array('heterosexual'),
//                            'interfaceLanguage' => 'es',
//                            'location' => array(
//                                'latitude' => 40.4167754,
//                                'longitude' => -3.7037902,
//                                'address' => 'Madrid',
//                                'locality' => 'Madrid',
//                                'country' => 'Spain'
//                            )
//                        )
//                    );
//                    $output->writeln(sprintf('SUCCESS: Created profile for user %d.', $user->getId()));
//                }
//            }
//
//            if (isset($profile) && is_array($profile)) {
//                $output->writeln(sprintf('Found profile for user %d.', $user->getId()));
//            }
//
//        }
//    }
}