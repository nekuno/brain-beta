<?php

namespace ApiConsumer\Factory;

use ApiConsumer\LinkProcessor\Processor\ProcessorInterface;
use ApiConsumer\LinkProcessor\Processor\ScraperProcessor;

class ProcessorFactory
{
    private $resourceOwnerFactory;
    private $scrapperProcessor;
    private $options;
    private $brainBaseUrl;

    /**
     * ProcessorFactory constructor.
     * @param ResourceOwnerFactory $resourceOwnerFactory
     * @param ScraperProcessor $scraperProcessor
     * @param array $options
     * @param string $brainBaseUrl
     */
    public function __construct(ResourceOwnerFactory $resourceOwnerFactory, ScraperProcessor $scraperProcessor, array $options, $brainBaseUrl)
    {
        $this->resourceOwnerFactory = $resourceOwnerFactory;
        $this->scrapperProcessor = $scraperProcessor;
        $this->options = $options;
        $this->brainBaseUrl = $brainBaseUrl;
    }

    /**
     * @param $processorName
     * @return ProcessorInterface
     */
    public function build($processorName) {

        if (!isset($this->options[$processorName])){
            return $this->scrapperProcessor;
        }

        $options = $this->options[$processorName];
        $processorClass = $options['class'];
        $parserClass = $options['parser'];
        $resourceOwner = $this->resourceOwnerFactory->build($options['resourceOwner']);
        $processor = new $processorClass($resourceOwner, new $parserClass(), $this->brainBaseUrl);

        return $processor;
    }

    public function getScrapperProcessor()
    {
        return $this->scrapperProcessor;
    }
}