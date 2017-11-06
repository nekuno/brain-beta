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
            ->addOption('group', null, InputArgument::OPTIONAL, 'How many questions we want the selection to include')
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

            $correlations = $model->sortCorrelations($correlations);

            foreach ($correlations as $correlation => $ids) {

                $output->writeln(sprintf('%d, %d, %s', $ids[0], $ids[1], $correlation));
            }

        } else {
            $result = $model->getUncorrelatedQuestions($preselected);

            if (array() === $result['questions']) {
                $output->writeln('We couldn´t get the questions');

                return;
            }

            if ($input->getOption('save')) {
                $previous = $model->unsetDivisiveQuestions();
                $model->setDivisiveQuestions($result['questions']);
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
     * @param $result array
     * @param $output OutputInterface
     */
    protected function outputCorrelations($result, $output)
    {
        $size = 0;
        foreach ($result as $question1 => $questions2) {
            foreach ($questions2 as $question2 => $correlation) {
                $output->writeln(sprintf('Correlation %f between question %s and question %s ', $correlation, $question1, $question2));
                $size++;
            }
        }

        $output->writeln($size);
    }

    /**
     * @param $result array
     * @param $output OutputInterface
     */
    protected function outputResult($result, $output)
    {
        try {

            $output->writeln(
                sprintf(
                    'Total correlation %s with questions %s and %s',
                    $result['totalCorrelation'],
                    $result['questions']['q1'],
                    $result['questions']['q2']
                )
            );
            $output->writeln('Total correlation: ' . $result['totalCorrelation']);

        } catch (\Exception $e) {

            $output->writeln(sprintf('Error trying to get the uncorrelated questions: %s', $e->getMessage()));

            return;
        }
    }

}