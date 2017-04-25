<?php

namespace Service\Validator;

class ValidatorFactory
{
    protected $metadata;

    protected $graphManager;

    protected $config;

    /**
     * ValidatorFactory constructor.
     * @param $graphManager
     * @param $metadata
     * @param $config
     */
    public function __construct($graphManager, $metadata, $config)
    {
        $this->metadata = $metadata;
        $this->graphManager = $graphManager;
        $this->config = $config;
    }

    public function build($name)
    {
        $class = $this->getClass($name);
        $metadata = $this->getMetadata($name);

        return new $class()
    }

    /**
     * @param $name
     * @return mixed
     */
    protected function getClass($name)
    {
        $config = $this->config;
        $defaultValidator = 'Service/Validator';
        $class = isset($config[$name]) && isset($config[$name]['class']) ? $config[$name]['class'] : $defaultValidator;

        return $class;
    }

    /**
     * @param $name
     * @return mixed
     */
    protected function getMetadata($name)
    {
        $config = $this->config;
        $defaultMetadata = $this->metadata;
        $metadata = isset($config[$name]) && isset($config[$name]['metadata']) ? $this->metadata[$name] : $defaultMetadata;

        return $metadata;
    }
}