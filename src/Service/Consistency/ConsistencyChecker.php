<?php

namespace Service\Consistency;

use Everyman\Neo4j\Node;
use Everyman\Neo4j\PropertyContainer;
use Everyman\Neo4j\Relationship;
use Model\Exception\ValidationException;

class ConsistencyChecker
{
    public function check(Node $node, ConsistencyNodeRule $userRule) {

//        $this->checkNodeRelationships($node, $userRule->getRelationships());
        $this->checkProperties($node, $userRule->getProperties());
    }

    /**
     * @param Node $node
     * @param $relationshipRules
     */
    protected function checkNodeRelationships(Node $node, $relationshipRules)
    {
        $totalRelationships = $node->getRelationships();

        foreach ($relationshipRules as $relationshipRule) {
            $rule = new ConsistencyRelationshipRule($relationshipRule);

            /** @var Relationship[] $relationships */
            $relationships = array_filter($totalRelationships, function ($relationship) use ($rule) {
                /** @var $relationship Relationship */
                return $relationship->getType() === $rule->getType();
            });

            $errors = array('relationships' => array());

            if (count($relationships) < $rule->getMinimum()) {
                $errors['relationships'][$rule->getType()] = sprintf('Amount of relationships %d is less than %d allowed', count($relationships), $rule->getMinimum());
            }

            if (count($relationships) > $rule->getMaximum()) {
                $errors['relationships'][$rule->getType()] = sprintf('Amount of relationships %d is more than %d allowed', count($relationships), $rule->getMaximum());
            }

            foreach ($relationships as $relationship) {
                $startNode = $relationship->getStartNode();
                $endNode = $relationship->getEndNode();
                $otherNode = $startNode->getId() !== $node->getId() ? $startNode : $endNode;


                if ($rule->getDirection() == 'incoming' && $endNode->getId() != $node->getId()
                    || $rule->getDirection() == 'outgoing' && $startNode->getId() != $node->getId()
                ) {
                    $errors['relationships']['direction'] = sprintf('Direction of relationship %d is not correct', $relationship->getId());
                }

                if (!in_array($rule->getOtherNode(), ConsistencyCheckerService::getLabelNames($otherNode))) {
                    $errors['relationships']['label'] = sprintf('Label of destination node for relationship %d is not correct', $relationship->getId());
                }

                $this->checkProperties($relationship, $rule->getProperties());
            }

            if (!empty($errors['relationships'])) {
                throw new ValidationException($errors, 'Node relationships consistency error for node ' . $node->getId());
            }
        }
    }

    protected function checkProperties(PropertyContainer $propertyContainer, array $propertyRules)
    {
        $properties = $propertyContainer->getProperties();

        foreach ($propertyRules as $name => $propertyRule) {

            $errors = array();
            $rule = new ConsistencyPropertyRule($name, $propertyRule);

            if (!isset($properties[$name])) {
                if (!$rule->isRequired()) {
                    continue;
                }

                $error = new MissingPropertyConsistencyError();
                $error->setPropertyName($name);
                $errors[] = $error;
            } else {
                $value = $properties[$name];

                $options = $rule->getOptions();
                if (!empty($options)) {
                    if (!in_array($value, $options)) {
                        $errors[$name] = sprintf('Element with id %d has property %s with invalid value %s', $propertyContainer->getId(), $name, $value);
                    }
                }

                switch ($rule->getType()) {
                    case null:
                        break;
                    case ConsistencyPropertyRule::TYPE_INTEGER:
                        if (!is_int($value)) {
                            $errors[$name] = sprintf('Element with id %d has property %s with value %s which should be an integer', $propertyContainer->getId(), $name, $value);
                        } else {
                            if ($rule->getMaximum() && $value > $rule->getMaximum()) {
                                $errors[$name] = sprintf('Element with id %d has property %d greater than maximum %d', $propertyContainer->getId(), $name, $value, $rule->getMaximum());
                            }
                            if ($rule->getMinimum() && $value < $rule->getMinimum()) {
                                $errors[$name] = sprintf('Element with id %d has property %d lower than minimum %d', $propertyContainer->getId(), $name, $value, $rule->getMinimum());
                            }
                        }
                        break;
                    case ConsistencyPropertyRule::TYPE_BOOLEAN:
                        if (!is_bool($value)) {
                            $errors[$name] = sprintf('Element with id %d has property %s with value %s which should be a boolean', $propertyContainer->getId(), $name, $value);
                        };
                        break;
                    case ConsistencyPropertyRule::TYPE_ARRAY:
                        if (!is_array($value)) {
                            $errors[$name] = sprintf('Element with id %d has property %s with value %s which should be an array', $propertyContainer->getId(), $name, $value);
                        };
                        break;
                    case ConsistencyPropertyRule::TYPE_DATETIME:
                        $date = new \DateTime($value);

                        if ($rule->getMaximum() && $date > new \DateTime($rule->getMaximum())) {
                            $errors[$name] = sprintf('Element with id %d has property %s later than maximum %s', $propertyContainer->getId(), $name, $rule->getMaximum());
                        }
                        if ($rule->getMinimum() && $value < $rule->getMinimum()) {
                            $errors[$name] = sprintf('Element with id %d has property %s earlier than minimum %s', $propertyContainer->getId(), $name, $rule->getMinimum());
                        }
                        break;
                    default:
                        break;
                }
            }

            if (!empty($errors)) {
                throw new ValidationException($errors, 'Properties consistency error for element ' . $propertyContainer->getId());
            }
        }
    }
}