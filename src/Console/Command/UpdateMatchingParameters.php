<?php

namespace Console\Command;

use Model\User\Matching\MatchingModel;

use Silex\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateMatchingParameters extends ApplicationAwareCommand
{

    protected function configure()
    {
        $this->setName('matching:updateMatchingParameters')
             ->setDescription("Update the Average and Standard Deviation for the Normal Distributions used in matchings");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $modelObject = new MatchingModel($this->app['neo4j.client'], $this->app['users.content.model'], $this->app['users.answer.model']);

        try {
            $modelObject->updateContentNormalDistributionVariables();
            $modelObject->updateQuestionsNormalDistributionVariables();
        } catch (\Exception $e) {
            $output->writeln(
               'Error trying to update parameters for the Normal Distributions with message: ' . $e->getMessage()
            );

            return;
        }

        $response = 'Parameters set. Values: ' .
            'Average(content)= ' . $modelObject->ave_content .
            ' - Standard Deviation(content)= ' . $modelObject->stdev_content .
            ' // Average(questions)= ' . $modelObject->ave_questions .
            ' - Standard Deviation(questions)= ' . $modelObject->stdev_questions;

        $output->writeln($response);
    }
}
