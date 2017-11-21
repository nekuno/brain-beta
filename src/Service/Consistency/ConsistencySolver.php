<?php

namespace Service\Consistency;

use Model\Neo4j\GraphManager;

class ConsistencySolver
{
    protected  $graphManager;

    /**
     * ConsistencySolver constructor.
     * @param $graphManager
     */
    public function __construct(GraphManager $graphManager)
    {
        $this->graphManager = $graphManager;
    }

    public function solve(ConsistencyError $error){
        switch (get_class($error)){
            case MissingPropertyConsistencyError::class:
                /** @var $error MissingPropertyConsistencyError */
                return $this->writeDefaultProperty($error);
            default:
                return false;
        }
    }

    protected function writeDefaultProperty(MissingPropertyConsistencyError $error)
    {
        $default = $error->getDefaultProperty();
        if (!$default) {
            return false;
        }

        $name = $error->getPropertyName();
        $nodeId = $error->getNodeId();

        return $this->writeNodeProperty($nodeId, $name, $default);
    }

    protected function writeNodeProperty($nodeId, $propertyName, $propertyValue)
    {
        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(node)')
            ->where('id(node) = {nodeId}')
            ->setParameter('nodeId', (integer)$nodeId);

        if (strpos($propertyValue, 'node.') !== false){
            $qb->set("node.$propertyName = $propertyValue");

        } else {
            $qb->set("node.$propertyName = {value}")
                ->setParameter('value', $propertyValue);
        }

        $qb->getQuery()->getResultSet();

        return true;
    }
}