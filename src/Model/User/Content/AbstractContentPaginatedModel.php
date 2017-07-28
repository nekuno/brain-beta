<?php

namespace Model\User\Content;

use Everyman\Neo4j\Node;
use Everyman\Neo4j\Query\Row;
use Model\Link\LinkModel;
use Model\Neo4j\GraphManager;
use Paginator\PaginatedInterface;
use Service\Validator\ValidatorInterface;

abstract class AbstractContentPaginatedModel implements PaginatedInterface
{
    /**
     * @var GraphManager
     */
    protected $gm;

    /**
     * @var LinkModel
     */
    protected $linkModel;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    public function __construct(GraphManager $gm, LinkModel $linkModel, ValidatorInterface $validator)
    {
        $this->gm = $gm;
        $this->linkModel = $linkModel;
        $this->validator = $validator;
    }

    /**
     * Hook point for validating the query.
     * @param array $filters
     * @return boolean
     */
    public function validateFilters(array $filters)
    {
        $filters['userId'] = isset($filters['id'])? $filters['id'] : null;
        return $this->validator->validateOnUpdate($filters);
    }

    protected function hydrateNodeProperties(Interest $content, Row $row)
    {
        /** @var Node $contentNode */
        $contentNode = $row->offsetGet('content');

        $content->setUrl($contentNode->getProperty('url'));
        $content->setTitle($contentNode->getProperty('title'));
        $content->setDescription($contentNode->getProperty('description'));
        $content->setThumbnail($contentNode->getProperty('thumbnail'));

        if ($contentNode->getProperty('embed_type')) {
            $content->setEmbed(
                array(
                    'type' => $contentNode->getProperty('embed_type'),
                    'id' => $contentNode->getProperty('embed_id'),
                )
            );
        }
    }

    protected function hydrateSynonymous(Interest $content, Row $row)
    {
        if ($row->offsetExists('synonymous')) {
            foreach ($row->offsetGet('synonymous') as $synonymousLink) {
                /* @var $synonymousLink Node */
                $synonymous = new Interest();
                $synonymous->setId($synonymousLink->getId());

                $synonymous->setUrl($synonymousLink->getProperty('url'));
                $synonymous->setTitle($synonymousLink->getProperty('title'));
                $synonymous->setThumbnail($synonymousLink->getProperty('thumbnail'));

                $content->addSynonymous($synonymous);
            }
        }
    }

    protected function hydrateTags(Interest $content, Row $row)
    {
        foreach ($row->offsetGet('tags') as $tag) {
            $content->addTag($tag);
        }
    }

    protected function hydrateTypes(Interest $content, Row $row)
    {
        foreach ($row->offsetGet('types') as $type) {
            $content->addType($type);
        }
    }
}