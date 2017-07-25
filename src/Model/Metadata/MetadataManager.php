<?php

namespace Model\Metadata;

use Model\Neo4j\GraphManager;
use Symfony\Component\Translation\Translator;

abstract class MetadataManager
{
    protected $gm;
    protected $translator;
    protected $metadata;
    protected $defaultLocale;

    protected $validLocales = array('en', 'es');

    public function __construct(GraphManager $gm, Translator $translator, array $metadata, array $socialMetadata, $defaultLocale)
    {
        $this->gm = $gm;
        $this->translator = $translator;
        $this->metadata = $metadata;
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * Returns the metadata for creating search filters
     * @param null $locale
     * @return array
     */
    public function getFilters($locale = null)
    {
        $locale = $this->getLocale($locale);
        $metadata = $this->getMetadata($locale);
        $labels = array();
        foreach ($metadata as $key => &$item) {
            $labels[] = $item['label'];
        }

        if (!empty($labels)) {
            array_multisort($labels, SORT_ASC, $metadata);
        }

        return $metadata;
    }

    /**
     * @param null $locale Locale of the metadata
     * @return array
     */
    public function getMetadata($locale = null)
    {
        $this->setLocale($locale);

        $publicMetadata = array();
        foreach ($this->metadata as $name => $values) {
            $publicField = $values;
            $publicField['label'] = $this->getLabel($values);

            $publicField = $this->modifyPublicFieldByType($publicField, $name, $values);

            $publicMetadata[$name] = $publicField;
        }

        return $publicMetadata;
    }

    protected function setLocale($locale)
    {
        $locale = $this->getLocale($locale);
        $this->translator->setLocale($locale);
    }

    protected function getLocale($locale)
    {
        if (!$locale || !in_array($locale, $this->validLocales)) {
            $locale = $this->defaultLocale;
        }

        return $locale;
    }

    protected function modifyPublicFieldByType($publicField, $name, $values)
    {
        return $publicField;
    }

    protected function getLabel($field)
    {
        $labelField = isset($field['label']) ? $field['label'] : null;

        return $this->getLocaleString($labelField);
    }

    protected function getLocaleString($labelField)
    {
        $locale = $this->translator->getLocale();
        if (null === $labelField || !is_array($labelField) || !isset($labelField[$locale])) {
            $errorMessage = sprintf('Locale %s not present for metadata', $locale);
            throw new \InvalidArgumentException($errorMessage);
        }

        return $labelField[$locale];
    }

//    public function getSocialFilters($locale)
//    {
//        $locale = $this->getLocale($locale);
//        $metadata = $this->getSocialMetadata($locale);
//        $labels = array();
//        foreach ($metadata as $key => &$item) {
//            if (isset($item['labelFilter'])) {
//                $item['label'] = $item['labelFilter'][$locale];
//                unset($item['labelFilter']);
//            }
//            if (isset($item['filterable']) && $item['filterable'] === false) {
//                unset($metadata[$key]);
//            } else {
//                $labels[] = $item['label'];
//            }
//        }
//
//        if (!empty($labels)) {
//            array_multisort($labels, SORT_ASC, $metadata);
//        }
//
//        return $metadata;
//    }
//
//    public function getSocialMetadata($locale)
//    {
//        $locale = $this->getLocale($locale);
//
//        $publicMetadata = array();
//        foreach ($this->socialMetadata as $name => $values) {
//            $publicField = $values;
//            $publicField['label'] = $values['label'][$locale];
//
//            $publicField = $this->modifyPublicFieldByType($publicField, $name, $values, $locale);
//
//            $publicMetadata[$name] = $publicField;
//        }
//
//        foreach ($publicMetadata as &$item) {
//            if (isset($item['labelFilter'])) {
//                unset($item['labelFilter']);
//            }
//            if (isset($item['filterable'])) {
//                unset($item['filterable']);
//            }
//        }
//
//        return $publicMetadata;
//    }
}