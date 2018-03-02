<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Manager\UserManager;
use Service\AMQPManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Worker\MatchingCalculatorWorker;

class RabbitMQEnqueueMatchingCommand extends ApplicationAwareCommand
{
    protected $defaultTrigger = MatchingCalculatorWorker::TRIGGER_PERIODIC;

    protected function configure()
    {
        $this->setName('rabbitmq:enqueue:matching')
            ->setDescription('Enqueues a matching taks for all users')
            ->addArgument('userA', InputArgument::OPTIONAL, 'id of the first user?')
            ->addArgument('userB', InputArgument::OPTIONAL, 'id of the second user?')
            ->addOption('trigger', null, InputOption::VALUE_REQUIRED, 'trigger to add to message', $this->defaultTrigger);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $userA = $input->getArgument('userA');
        $userB = $input->getArgument('userB');
        $trigger = $input->getOption('trigger');

        switch ($trigger) {
            case MatchingCalculatorWorker::TRIGGER_PERIODIC:
                if ($userA && $userB && $userA === $userB) {
                    $output->writeln('The two users must be different.');

                    return;
                }

                $data = $this->buildPeriodicData($output, $userA, $userB);
                break;
            case MatchingCalculatorWorker::TRIGGER_PROCESS_FINISHED:
            case MatchingCalculatorWorker::TRIGGER_CONTENT_RATED:
                $data = $this->buildContentData($userA);
                break;
            default:
                $validTriggers = json_encode(array(MatchingCalculatorWorker::TRIGGER_PERIODIC, MatchingCalculatorWorker::TRIGGER_PROCESS_FINISHED, MatchingCalculatorWorker::TRIGGER_CONTENT_RATED));
                $output->writeln(sprintf('Not a valid trigger. Valid triggers are %s', $validTriggers));

                return;
        }

        foreach ($data as $singleData) {
            /* @var $amqpManager AMQPManager */
            $amqpManager = $this->app['amqpManager.service'];

            $amqpManager->enqueueMatching($singleData, $trigger);
        }
    }

    /**
     * @param OutputInterface $output
     * @param $userA
     * @param $userB
     * @return array
     */
    protected function buildPeriodicData(OutputInterface $output, $userA, $userB)
    {
        $combinations = array(
            array(
                0 => $userA,
                1 => $userB
            )
        );

        if (null === $userA || null === $userB) {
            /* @var $userManager UserManager */
            $userManager = $this->app['users.manager'];
            $combinations = $userManager->getAllCombinations(false);
        }

        $data = array();
        foreach ($combinations as $combination) {
            if ($combination[0] == $combination[1]) {
                continue;
            }
            $output->writeln(sprintf('Enqueuing matching and similarity task for users %d and %d', $combination[0], $combination[1]));
            $data[] = array(
                'user_1_id' => $combination[0],
                'user_2_id' => $combination[1]
            );
        }

        return $data;
    }

    protected function buildContentData($userA)
    {
        if ($userA !== null) {
            $userIds = array($userA);
        } else {
            /* @var $userManager UserManager */
            $userManager = $this->app['users.manager'];
            $userIds = $userManager->getAllIds(false);
        }

        $data = array();
        foreach ($userIds as $userId) {
            $data[] = array('userId' => $userId);
        }

        return $data;
    }
}