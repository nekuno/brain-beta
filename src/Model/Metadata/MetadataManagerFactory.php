<?php

namespace Model\Metadata;

use Model\User\ProfileOptionManager;
use Silex\Translator;

class MetadataManagerFactory
{
    protected $config;
    protected $translator;
    protected $metadataUtilities;
    protected $defaultLocale;
    protected $metadata;

    /**
     * MetadataManagerFactory constructor.
     * @param $config
     * @param Translator $translator
     * @param MetadataUtilities $metadataUtilities
     * @param $metadata
     * @param $defaultLocale
     */
    public function __construct($config, Translator $translator, MetadataUtilities $metadataUtilities,  $metadata, $defaultLocale)
    {
        $this->config = $config;
        $this->translator = $translator;
        $this->metadataUtilities = $metadataUtilities;
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

        return new $class($this->translator, $this->metadataUtilities, $metadata, $this->defaultLocale);
    }
}