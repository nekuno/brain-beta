<?php

namespace Model\LanguageText;

use Everyman\Neo4j\Query\ResultSet;
use Model\Neo4j\GraphManager;

class LanguageTextManager
{
    protected $graphManager;


    public function __construct(GraphManager $graphManager)
    {
        $this->graphManager = $graphManager;
    }

    public function merge($nodeId, $locale, $text)
    {
        $languageLabel = $this->localeToLabel($locale);
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(node)')
            ->where('id(node) = {nodeId}')
            ->setParameter('nodeId', (integer)$nodeId)
            ->with('node')
            ->limit(1);

        $qb->merge("(languageText: $languageLabel)")
            ->set('languageText.text = {text}')
            ->setParameter('text', $text);

        $qb->merge('(languageText)-[:TEXT_OF]->(node)');

        $qb->returns('languageText.text AS text, labels(languageText) AS labels');

        $result = $qb->getQuery()->getResultSet();

        return $this->buildOne($result);
    }

    public function localeToLabel($locale)
    {
        switch($locale) {
            case 'en':
                $language = 'English';
                break;
            case 'es':
                $language = 'Spanish';
                break;
            default:
                $language = 'English';
                break;
        }

        return 'Text' . $language;
    }

    protected function labelsToLocale(array $labels)
    {
        $labels = array_filter($labels, function($label){
            return strpos($label, 'Language') === 0;
        });

        //TODO: Throw ¿consistency? exception if count !== 1
        $label = reset($labels);

        $language = substr($label, strlen('Language'));

        switch($language){
            case 'English':
                return 'en';
            case 'Spanish':
                return 'es';
            default:
                return 'en';
        }
    }

    protected function buildOne(ResultSet $resultSet)
    {
        $row = $resultSet->current();

        $labels = $row->offsetGet('labels');
        $locale = $this->labelsToLocale($labels);
        $text = $row->offsetGet('text');

        $languageText = new LanguageText();
        $languageText->setText($text);
        $languageText->setLanguage($locale);

        return $languageText;
    }
}