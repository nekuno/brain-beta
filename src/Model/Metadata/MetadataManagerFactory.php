<?php

namespace Model\Metadata;

class MetadataManagerFactory
{
    protected $config;
    protected $graphManager;
    protected $translator;
    protected $defaultLocale;
    protected $metadata;

    /**
     * MetadataManagerFactory constructor.
     * @param $config
     * @param $graphManager
     * @param $translator
     * @param $defaultLocale
     * @param $metadata
     */
    public function __construct($config, $graphManager, $translator, $defaultLocale, $metadata)
    {
        $this->config = $config;
        $this->graphManager = $graphManager;
        $this->translator = $translator;
        $this->defaultLocale = $defaultLocale;
        $this->metadata = $metadata;
    }

    /**
     * @param $name
     * @return MetadataManager
     */
    public function build($name)
    {
        $class = isset($this->config[$name]) ? $this->config[$name] : $this->config['default'];
        $metadata = isset($this->metadata[$name]) ? $this->metadata[$name] : [];

        return new $class($this->graphManager, $this->translator, $metadata, $this->defaultLocale);
    }
}