<?php

namespace Model\Metadata;

use Model\Neo4j\GraphManager;
use Symfony\Component\Translation\Translator;

class MetadataManager implements MetadataManagerInterface
{
    protected $gm;
    protected $translator;
    protected $metadata;
    protected $defaultLocale;

    static public $validLocales = array('en', 'es');

    public function __construct(GraphManager $gm, Translator $translator, array $metadata, $defaultLocale)
    {
        $this->gm = $gm;
        $this->translator = $translator;
        $this->metadata = $metadata;
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * @param null $locale Locale of the metadata
     * @return array
     */
    public function getMetadata($locale = null)
    {
        $this->setLocale($locale);

        $metadata = array();
        foreach ($this->metadata as $name => $values) {
            $publicField = $values;
            $publicField['label'] = $this->getLabel($values);

            $publicField = $this->modifyPublicField($publicField, $name, $values);

            $metadata[$name] = $publicField;
        }

        $metadata = $this->orderByLabel($metadata);

        return $metadata;
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

    protected function getLabel($field)
    {
        $labelField = isset($field['label']) ? $field['label'] : null;

        return $labelField ? $this->getLocaleString($labelField) : null;
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

    protected function orderByLabel($metadata) {
        $labels = $this->getLabels($metadata);

        if (!empty($labels)) {
            array_multisort($labels, SORT_ASC, $metadata);
        }

        return $metadata;
    }

    protected function getLabels($metadata) {
        $labels = array();
        foreach ($metadata as $key => &$item) {
            $labels[] = $item['label'];
        }

        return $labels;
    }

    protected function modifyPublicField($publicField, $name, $values)
    {
        return $publicField;
    }

    public function labelToType($labelName)
    {

        return lcfirst($labelName);
    }

    public function typeToLabel($typeName)
    {
        return ucfirst($typeName);
    }

    public function getLanguageFromTag($tag)
    {
        return $this->translateTypicalLanguage($this->formatLanguage($tag));
    }

    //TODO: Refactor this translation functions
    protected function translateTypicalLanguage($language)
    {
        switch ($language) {
            case 'Español':
                return 'Spanish';
            case 'Castellano':
                return 'Spanish';
            case 'Inglés':
                return 'English';
            case 'Ingles':
                return 'English';
            case 'Francés':
                return 'French';
            case 'Frances':
                return 'French';
            case 'Alemán':
                return 'German';
            case 'Aleman':
                return 'German';
            case 'Portugués':
                return 'Portuguese';
            case 'Portugues':
                return 'Portuguese';
            case 'Italiano':
                return 'Italian';
            case 'Chino':
                return 'Chinese';
            case 'Japonés':
                return 'Japanese';
            case 'Japones':
                return 'Japanese';
            case 'Ruso':
                return 'Russian';
            case 'Árabe':
                return 'Arabic';
            case 'Arabe':
                return 'Arabic';
            default:
                return $language;
        }
    }

    public function translateLanguageToLocale($language, $locale)
    {
        $locale = $this->getLocale($locale);

        if ($locale === 'en') {
            return $language;
        }
        if ($locale === 'es') {
            switch ($language) {
                case 'Spanish':
                    return 'Español';
                case 'English':
                    return 'Inglés';
                case 'French':
                    return 'Francés';
                case 'German':
                    return 'Alemán';
                case 'Portuguese':
                    return 'Portugués';
                case 'Italian':
                    return 'Italiano';
                case 'Chinese':
                    return 'Chino';
                case 'Japanese':
                    return 'Japonés';
                case 'Russian':
                    return 'Ruso';
                case 'Arabic':
                    return 'Árabe';
            }
        }

        return $language;
    }

    protected function formatLanguage($typeName)
    {
        $firstCharacter = mb_strtoupper(mb_substr($typeName, 0, 1, 'UTF-8'), 'UTF-8');
        $restString = mb_strtolower(mb_substr($typeName, 1, null, 'UTF-8'), 'UTF-8');

        return $firstCharacter . $restString;
    }

}