<?php

namespace Model\Metadata;

use Model\Neo4j\GraphManager;
use Silex\Translator;

class MetadataManagerFactory
{
    protected $config;
    /**
     * @var GraphManager
     */
    protected $graphManager;
    /**
     * @var Translator
     */
    protected $translator;
    protected $defaultLocale;
    protected $metadata;

    /**
     * MetadataManagerFactory constructor.
     * @param $config
     * @param GraphManager $graphManager
     * @param Translator $translator
     * @param $defaultLocale
     * @param $metadata
     */
    public function __construct($config, GraphManager $graphManager, Translator $translator, $metadata, $defaultLocale)
    {
        $this->config = $config;
        $this->graphManager = $graphManager;
        $this->translator = $translator;
        $this->metadata = $metadata;
        $this->defaultLocale = $defaultLocale;
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