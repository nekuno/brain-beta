<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Model\User\Question\QuestionCorrelationManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class QuestionsGetUncorrelatedCommand extends ApplicationAwareCommand
{

    protected function configure()
    {
        $this->setName('questions:get-uncorrelated')
            ->setDescription('Get a selection of uncorrelated questions groups.')
            ->addArgument('preselect', InputArgument::OPTIONAL, 'How many top ranking questions are analyzed', 500)
            ->addOption('correlated', null, InputOption::VALUE_NONE, 'If we want the more correlated questions')
            ->addOption('save', null, InputOption::VALUE_NONE, 'Set output questions as divisive');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /* @var $model QuestionCorrelationManager */
        $model = $this->app['users.questionCorrelation.manager'];

        $preselected = $input->getArgument('preselect');

        if ($input->getOption('correlated')) {
            $correlations = $model->getCorrelatedQuestions($preselected);

            foreach ($correlations as $correlation => $ids) {

                $output->writeln(sprintf('%d, %d, %s', $ids[0], $ids[1], $correlation));
            }

        } else {
            $result = $model->getUncorrelatedQuestions($preselected);

            if ($input->getOption('save')) {
                $previous = $model->unsetAllDivisiveQuestions();
                foreach ($result as $mode => $questionsByMode) {
                    $model->setDivisiveQuestions($questionsByMode['questions'], $mode);
                }
                if (OutputInterface::VERBOSITY_NORMAL < $output->getVerbosity()) {
                    $output->writeln(sprintf('There were %d questions set as divisive', $previous));
                }
            }

            //for debugging, modify return to appropriate array in QuestionModel.php;
//        $this->outputCorrelations($result, $output);
            $this->outputResult($result, $output);
        }
    }

    /**
     * @param $resultRaw
     * @param $output OutputInterface
     */
    protected function outputCorrelations($resultRaw, $output)
    {
        $output->writeln('----------------------------');

        $size = 0;
        foreach ($resultRaw as $mode => $result) {
            $output->writeln(sprintf('Results for mode %s', $mode));
            foreach ($result as $question1 => $questions2) {
                foreach ($questions2 as $question2 => $correlation) {
                    $output->writeln(sprintf('Correlation %f between question %s and question %s ', $correlation, $question1, $question2));
                    $size++;
                }
            }
            $output->writeln('----------------------------');
        }

        $output->writeln($size);
    }

    /**
     * @param $resultRaw
     * @param $output OutputInterface
     */
    protected function outputResult($resultRaw, $output)
    {
        $output->writeln('----------------------------');

        foreach ($resultRaw as $mode => $result) {
            try {
                $output->writeln(sprintf('Results for mode %s', $mode));

                $output->writeln(
                    sprintf(
                        'Total correlation %s with questions %s, %s, %s and %s',
                        $result['totalCorrelation'],
                        $result['questions']['q1'],
                        $result['questions']['q2'],
                        $result['questions']['q3'],
                        $result['questions']['q4']
                    )
                );
                $output->writeln('Total correlation: ' . $result['totalCorrelation']);

            } catch (\Exception $e) {

                $output->writeln(sprintf('Error trying to get the uncorrelated questions: %s', $e->getMessage()));

                return;
            }

            $output->writeln('----------------------------');
        }
    }

}