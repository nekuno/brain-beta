<?php

namespace Service\Links;

use Model\Link\LinkModel;
use Service\AMQPManager;
use Symfony\Component\Console\Output\OutputInterface;

class EnqueueLinksService
{
    const LINKS_LIMIT = 1000;
    /**
     * @var LinkModel
     */
    protected $linkModel;

    /**
     * @var AMQPManager
     */
    protected $amqpManager;

    public function __construct(LinkModel $linkModel, AMQPManager $amqpManager)
    {
        $this->linkModel = $linkModel;
        $this->amqpManager = $amqpManager;
    }

    public function enqueueLinksCheck(OutputInterface $output)
    {
        $queuedMessages = $this->amqpManager->getMessagesCount(AMQPManager::LINKS_CHECK);
        if ($queuedMessages > 0) {
            $output->writeln(sprintf('There are %s messages pending to consume in this queue', $queuedMessages));
            return;
        }
        $oneMonthAgo = new \DateTime('-1 month');
        $offset = 0;
        $links = array();
        $conditions = array(
            'link.processed = 1',
            'NOT EXISTS (link.lastChecked) OR link.lastChecked < ' . $oneMonthAgo->getTimestamp() * 1000,
        );
        while ($offset == 0 || count($links) >= self::LINKS_LIMIT) {
            $links = $this->linkModel->getLinks($conditions, $offset, self::LINKS_LIMIT);
            $offset = $offset + self::LINKS_LIMIT;

            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln(count($links) . ' links');
            }

            foreach ($links as $link) {
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln('Link ' . $link['url'] . ' will be checked');
                    $output->writeln(sprintf('Enqueuing url check for "%s"', $link['url']));
                }
                $this->amqpManager->enqueueLinkCheck(array('link' => $link));
            }
        }
    }

    public function enqueueLinksReprocess(OutputInterface $output)
    {
        $queuedMessages = $this->amqpManager->getMessagesCount(AMQPManager::LINKS_REPROCESS);
        if ($queuedMessages > 0) {
            $output->writeln(sprintf('There are %s messages pending to consume in this queue', $queuedMessages));
            return;
        }
        $oneMonthAgo = new \DateTime('-1 month');
        $offset = 0;
        $links = array();
        $conditions = array(
            'link.processed = 0',
            'NOT EXISTS (link.lastReprocessed) OR link.lastReprocessed < ' . $oneMonthAgo->getTimestamp() * 1000,
            'NOT EXISTS (link.reprocessedCount) OR link.reprocessedCount < 2',
        );
        while ($offset == 0 || count($links) >= self::LINKS_LIMIT) {
            $links = $this->linkModel->getLinks($conditions, $offset, self::LINKS_LIMIT);
            $offset = $offset + self::LINKS_LIMIT;

            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln(count($links) . ' links');
            }

            foreach ($links as $link) {
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln('Link ' . $link['url'] . ' will be reprocessed');
                    $output->writeln(sprintf('Enqueuing url reprocess for "%s"', $link['url']));
                }
                $this->amqpManager->enqueueLinkReprocess(array('link' => $link));
            }
        }
    }
}