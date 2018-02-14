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
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(node)')
            ->where('id(node) = {nodeId}')
            ->setParameter('nodeId', (integer)$nodeId)
            ->with('node')
            ->limit(1);

        $qb->merge("(t: TextLanguage{text: {text}, locale: {locale}})-[:TEXT_OF]->(node)")
            ->setParameter('text', $text)
            ->setParameter('locale', $locale);

        $qb->returns('t.text AS text, t.locale AS locale');

        $result = $qb->getQuery()->getResultSet();

        return $this->buildOne($result);
    }

    protected function buildOne(ResultSet $resultSet)
    {
        $row = $resultSet->current();

        $locale = $row->offsetGet('locale');
        $text = $row->offsetGet('text');

        $languageText = new LanguageText();
        $languageText->setText($text);
        $languageText->setLanguage($locale);

        return $languageText;
    }
}