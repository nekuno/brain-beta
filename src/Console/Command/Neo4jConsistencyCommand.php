<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Model\Neo4j\GraphManager;
use Service\Consistency\ConsistencyCheckerService;
use Service\Consistency\ConsistencyErrors\ConsistencyError;
use Service\Consistency\ConsistencyErrors\MissingPropertyConsistencyError;
use Service\Consistency\ConsistencyErrors\RelationshipAmountConsistencyError;
use Service\Consistency\ConsistencyErrors\RelationshipMultipleSimilarConsistencyError;
use Service\Consistency\ConsistencyErrors\RelationshipOtherLabelConsistencyError;
use Service\Consistency\ConsistencyErrors\ReverseRelationshipConsistencyError;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Neo4jConsistencyCommand extends ApplicationAwareCommand
{

    protected function configure()
    {
        $this->setName('neo4j:consistency')
            ->setDescription('Detects database consistency')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Check only nodes with that label', null)
            ->addOption('solve', null, InputOption::VALUE_NONE, 'Solve problems where possible')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Node limit to analyze', null)
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, 'Offset start', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $solve = $input->getOption('solve');
        $label = $input->getOption('label');
        $limit = (integer)$input->getOption('limit');
        $offset = (integer)$input->getOption('offset');

        /** @var ConsistencyCheckerService $consistencyChecker */
        $consistencyChecker = $this->app['consistency.service'];
        /** @var GraphManager $graphManager */
        $graphManager = $this->app['neo4j.graph_manager'];
        $totalAmount = $graphManager->countLabel($label);

        $paginationSize = 10000; //Used for error logging
        do {
            $output->writeln('ANALYZING PAGE');

            $errors = $consistencyChecker->getDatabaseErrors($label, $offset, $paginationSize);
            $this->outputErrors($errors, $output);

            if ($solve) {
                $solved = $consistencyChecker->solveDatabaseErrors($errors);
                $this->outputErrors($solved, $output);
            }

            $limitReached = $offset > $limit;
            $databaseCompleted = $offset > $totalAmount;

            $offset += $paginationSize;

        } while (!$limitReached && !$databaseCompleted);

        $output->writeln('Finished.');
    }

    /**
     * @param ConsistencyError[] $errors
     * @param OutputInterface $output
     */
    private function outputErrors(array $errors, OutputInterface $output)
    {
        $missingPropertyErrors = array();
        $reverseRelationshipErrors = array();
        $relationshipAmountConsistencyErrors = array();
        $relationshipOtherLabelConsistencyErrors = array();
        $generalErrors = array();

        foreach ($errors as $error) {
            if ($error->isSolved()) {
                $output->writeln('SOLVED: ' . $error->getMessage());
                continue;
            }

            switch ($error::NAME) {
                case MissingPropertyConsistencyError::NAME:
                    /** @var $error MissingPropertyConsistencyError */
                    $missingPropertyErrors[$error->getPropertyName()][] = $error->getNodeId();
                    break;
                case ReverseRelationshipConsistencyError::NAME:
                    /** @var $error ReverseRelationshipConsistencyError */
                    $reverseRelationshipErrors[] = $error->getRelationshipId();
                    break;
                case RelationshipAmountConsistencyError::NAME:
                    /** @var $error RelationshipAmountConsistencyError */
                    $relationshipAmountConsistencyErrors[$error->getType()][$error->getMessage()][] = $error->getNodeId();
                    break;
                case RelationshipOtherLabelConsistencyError::NAME:
                    /** @var $error RelationshipOtherLabelConsistencyError */
                    $relationshipOtherLabelConsistencyErrors[$error->getMessage()][] = $error->getRelationshipId();
                    break;
                default:
                    $generalErrors[] = array('nodeId' => $error->getNodeId(), 'message' => $error->getMessage());
                    break;
            }
        }

        foreach ($missingPropertyErrors as $propertyName => $nodeIds) {
            $this->outputErrorIds('Missing property: ' . $propertyName, $nodeIds, $output);
        }

        $this->outputErrorIds('Reverse relationships', $reverseRelationshipErrors, $output);

        foreach ($relationshipAmountConsistencyErrors as $type => $typeErrors) {
            foreach ($typeErrors as $message => $nodeIds) {
                $this->outputErrorIds('Relationships of type ' . $type . ' with message ' . $message, $nodeIds, $output);
            }
        }

        foreach ($relationshipOtherLabelConsistencyErrors as $message => $nodeIds) {
            $this->outputErrorIds('Relationships with message ' . $message, $nodeIds, $output);
        }

        $this->outputGeneralErrors($generalErrors, $output);
    }

    protected function outputErrorIds($title, array $ids, OutputInterface $output)
    {
        if (empty($ids)) {
            return;
        }

        $output->writeln('-----------');
        $output->writeln($title);
        $output->writeln('Ids:');
        $output->writeln(json_encode($ids));
    }

    protected function outputGeneralErrors($errors, OutputInterface $output)
    {
        foreach ($errors as $error) {
            $output->writeln('----------');
            $output->writeln('Node id: ' . $error['nodeId']);
            $output->writeln('Message: ' . $error['message']);
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