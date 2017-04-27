<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Model\Entity\DataStatus;
use Model\Neo4j\Neo4jException;
use Model\User\Token\TokenStatus\TokenStatusManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MigrateDataStatusCommand extends ApplicationAwareCommand
{
    protected function configure()
    {
        $this->setName('migrate:data-status')
            ->setDescription('Migrate social network status to brain');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $entityManager = $this->app['orm.ems']['mysql_brain'];
        if ($entityManager->getConnection()->ping() === false) {
            $entityManager->getConnection()->close();
            $entityManager->getConnection()->connect();
        }
        $repository = $entityManager->getRepository('\Model\Entity\DataStatus');

        $userManager = $this->app['users.manager'];
        $users = $userManager->getAll();

        foreach ($users as $user) {
            $criteria = array('userId' => $user->getId());
            $dataStatuses = $repository->findBy($criteria);

            /** @var DataStatus $dataStatus */
            foreach ($dataStatuses as $dataStatus) {
                $this->migrateStatus($dataStatus, $output);
            }
        }

        $output->writeln(count($users) . ' users processed');

        $output->writeln('Done');
    }

    protected function migrateStatus(DataStatus $dataStatus, OutputInterface $output)
    {
        $userId = $dataStatus->getUserId();
        $resourceOwner = $dataStatus->getResourceOwner();
        $fetched = $dataStatus->getFetched();
        $processed = $dataStatus->getProcessed();
        $updatedAt = $dataStatus->getUpdateAt()->getTimestamp() * 1000;

        $output->writeln(sprintf('Migrating user %s and resource %s', $userId, $resourceOwner));

        /** @var TokenStatusManager $tokenStatusManager */
        $tokenStatusManager = $this->app['users.tokenStatus.manager'];

        try {
            $tokenStatusManager->setFetched($userId, $resourceOwner, (integer)$fetched);
            $tokenStatusManager->setProcessed($userId, $resourceOwner, (integer)$processed);
            $tokenStatusManager->setUpdatedAt($userId, $resourceOwner, $updatedAt);
        } catch (NotFoundHttpException $e) {
            $output->writeln(sprintf('NOTICE: Token for this user and resource not found.'));
        } catch (Neo4jException $e) {
            $output->writeln($e->getQuery());
        }
    }
}
