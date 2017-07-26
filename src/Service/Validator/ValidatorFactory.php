<?php

namespace Service\Validator;

use Model\Metadata\MetadataManagerFactory;
use Model\Neo4j\GraphManager;

class ValidatorFactory
{
    protected $metadataManagerFactory;

    protected $graphManager;

    protected $config;

    /**
     * ValidatorFactory constructor.
     * @param $graphManager
     * @param $metadataManagerFactory
     * @param $config
     */
    public function __construct(GraphManager $graphManager, MetadataManagerFactory $metadataManagerFactory, $config)
    {
        $this->graphManager = $graphManager;
        $this->metadataManagerFactory = $metadataManagerFactory;
        $this->config = $config;
    }

    public function build($name)
    {
        $class = $this->getClass($name);
        /** @var Validator $validator */
        $metadata = $this->metadataManagerFactory->build($name)->getMetadata();
        $validator = new $class($this->graphManager, $metadata);

        return $validator;
    }

    /**
     * @param $name
     * @return mixed
     */
    protected function getClass($name)
    {
        $config = $this->config;
        $defaultValidator = $config['default'];
        $class = isset($config[$name]) ? $config[$name] : $defaultValidator;

        return $class;
    }
}