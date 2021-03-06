<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Silex\Application;
use Service\ChatMessageNotifications;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class SwiftMailerChatSendCommand extends ApplicationAwareCommand
{

    protected function configure()
    {
        $this->setName('swiftmailer:chat:send')
            ->setDescription('Send chat notifications (unread messages since last 24h)')
            ->addOption('limit', 'lim', InputOption::VALUE_OPTIONAL, 'Notifications limit', 99999);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $limit = $input->getOption('limit');

        if ($limit === 0) {
            $limit = 99999;
        }

        if (!is_int($limit)) {
            $output->writeln(sprintf('Limit must be an integer, %s given.', gettype($limit)));

            return;
        }

        /* @var $chatMessageNotifications ChatMessageNotifications */
        $chatMessageNotifications = $this->app['chatMessageNotifications.service'];

        try {

            $chatMessageNotifications->sendUnreadChatMessages($limit, $output, $this);

            $style = new OutputFormatterStyle('green', 'black', array('bold', 'blink'));
            $output->getFormatter()->setStyle('success', $style);
            $output->writeln('<success>SUCCESS</success>');

        } catch (\Exception $e) {

            $style = new OutputFormatterStyle('red', 'black', array('bold', 'blink'));
            $output->getFormatter()->setStyle('error', $style);
            $output->writeln('<error>Error trying to send emails: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>FAIL</error>');
        }

        $this->app['swiftmailer.spooltransport']->getSpool()->flushQueue($this->app['swiftmailer.transport']);
        $output->writeln('Spool sent.');
        $output->writeln('Done.');

    }

}
