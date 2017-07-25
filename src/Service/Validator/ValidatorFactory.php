<?php

namespace Service\Validator;

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
    public function __construct($graphManager, $metadataManagerFactory, $config)
    {
        $this->graphManager = $graphManager;
        $this->metadataManagerFactory = $metadataManagerFactory;
        $this->config = $config;
    }

    public function build($name)
    {
        $class = $this->getClass($name);

        return new $class($this->graphManager, $this->metadataManagerFactory);
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