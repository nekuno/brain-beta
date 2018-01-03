<?php

namespace Service\Consistency;

use Model\Exception\ValidationException;
use Service\Consistency\ConsistencyErrors\ConsistencyError;
use Service\Consistency\ConsistencyErrors\MissingPropertyConsistencyError;
use Service\Consistency\ConsistencyErrors\ReverseRelationshipConsistencyError;

class ConsistencyChecker
{
    public function checkNode(ConsistencyNodeData $nodeData, ConsistencyNodeRule $rule)
    {
        $this->checkNodeRelationships($nodeData, $rule);
        $this->checkProperties($nodeData->getProperties(), $nodeData->getId(), $rule->getProperties());
    }

    /**
     * @param ConsistencyNodeData $nodeData
     * @param ConsistencyNodeRule $rule
     * @internal param array $totalRelationships
     * @internal param $nodeId
     */
    protected function checkNodeRelationships(ConsistencyNodeData $nodeData,  ConsistencyNodeRule $rule)
    {
        $relationshipRules = $rule->getRelationships();
        $nodeId = $nodeData->getId();
        foreach ($relationshipRules as $relationshipRule) {
            $rule = new ConsistencyRelationshipRule($relationshipRule);

            list($incoming, $outgoing) = $this->chooseByRule($nodeData, $rule);
            $totalRelationships = array_merge($incoming + $outgoing);
            $errors = array();

            $count = count($totalRelationships);
            if ($count < $rule->getMinimum()) {
                $error = new ConsistencyError();
                $error->setMessage(sprintf('Amount of relationships %d is less than %d allowed', $count, $rule->getMinimum()));
                $errors[] = $error;
            }

            if ($count > $rule->getMaximum()) {
                $error = new ConsistencyError();
                $error->setMessage(sprintf('Amount of relationships %d is more than %d allowed', $count, $rule->getMaximum()));
                $errors[] = $error;
            }

            foreach ($incoming as $relationship) {
                if ($rule->getDirection() == 'outgoing'){
                    $errors[] = new ReverseRelationshipConsistencyError($relationship->getId());
                }

                $otherNodeLabels = $relationship->getStartNodeLabels();

                if (!in_array($rule->getOtherNode(), $otherNodeLabels)) {
                    $error = new ConsistencyError();
                    $error->setMessage(sprintf('Label of node for relationship %d is not correct', $relationship->getId()));
                    $errors[] = $error;
                }

                $this->checkProperties($relationship->getProperties(), $relationship->getId(), $rule->getProperties());
            }

            foreach ($outgoing as $relationship) {
                if ($rule->getDirection() == 'incoming'){
                    $errors[] = new ReverseRelationshipConsistencyError($relationship->getId());
                }

                $otherNodeLabels = $relationship->getEndNodeLabels();


                if (!in_array($rule->getOtherNode(), $otherNodeLabels)) {
                    $error = new ConsistencyError();
                    $error->setMessage(sprintf('Label of node for relationship %d is not correct', $relationship->getId()));
                    $errors[] = $error;
                }

                $this->checkProperties($relationship->getProperties(), $relationship->getId(), $rule->getProperties());
            }

            $this->throwErrors($errors, $nodeId);
        }
    }

    protected function checkProperties(array $properties, $id, array $propertyRules)
    {
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
                        $error = new ConsistencyError();
                        $error->setMessage(sprintf('Element with id %d has property %s with invalid value %s', $id, $name, $value));
                        $errors[] = $error;
                    }
                }

                switch ($rule->getType()) {
                    case null:
                        break;
                    case ConsistencyPropertyRule::TYPE_INTEGER:
                        if (!is_int($value)) {
                            $error = new ConsistencyError();
                            $error->setMessage(sprintf('Element with id %d has property %s with value %s which should be an integer', $id, $name, $value));
                            $errors[] = $error;
                        } else {
                            if ($rule->getMaximum() && $value > $rule->getMaximum()) {
                                $error = new ConsistencyError();
                                $error->setMessage(sprintf('Element with id %d has property %d greater than maximum %d', $id, $name, $value, $rule->getMaximum()));
                                $errors[] = $error;
                            }

                            if ($rule->getMinimum() && $value < $rule->getMinimum()) {
                                $error = new ConsistencyError();
                                $error->setMessage(sprintf('Element with id %d has property %d lower than minimum %d', $id, $name, $value, $rule->getMinimum()));
                                $errors[] = $error;
                            }
                        }
                        break;
                    case ConsistencyPropertyRule::TYPE_BOOLEAN:
                        if (!is_bool($value)) {
                            $error = new ConsistencyError();
                            $error->setMessage(sprintf('Element with id %d has property %s that should be a value', $id, json_encode($value)));
                            $errors[] = $error;
                        };
                        break;
                    case ConsistencyPropertyRule::TYPE_ARRAY:
                        if (!is_array($value)) {
                            $error = new ConsistencyError();
                            $error->setMessage(sprintf('Element with id %d has property %s with value %s which should be an array', $id, $name, $value));
                            $errors[] = $error;
                        };
                        break;
                    case ConsistencyPropertyRule::TYPE_DATETIME:
                        $date = new \DateTime($value);

                        if ($rule->getMaximum() && $date > new \DateTime($rule->getMaximum())) {
                            $error = new ConsistencyError();
                            $error->setMessage(sprintf('Element with id %d has property %s later than maximum %s', $id, $name, $rule->getMaximum()));
                            $errors[] = $error;
                        }
                        if ($rule->getMinimum() && $value < $rule->getMinimum()) {
                            $error = new ConsistencyError();
                            $error->setMessage(sprintf('Element with id %d has property %s earlier than minimum %s', $id, $name, $rule->getMinimum()));
                            $errors[] = $error;
                        }
                        break;
                    default:
                        break;
                }
            }

            $this->throwErrors($errors, $id);
        }
    }

    /**
     * @param ConsistencyNodeData $nodeData
     * @param ConsistencyRelationshipRule $rule
     * @return ConsistencyRelationshipData[][]
     */
    protected function chooseByRule(ConsistencyNodeData $nodeData, ConsistencyRelationshipRule $rule)
    {
        $incoming = array();
        foreach ($nodeData->getIncoming() as $candidateRelationship) {
            if ($candidateRelationship->getType() === $rule->getType()) {
                $incoming[] = $candidateRelationship;
            }
        }

        $outgoing = array();
        foreach ($nodeData->getOutgoing() as $candidateRelationship) {
            if ($candidateRelationship->getType() === $rule->getType()) {
                $outgoing[] = $candidateRelationship;
            }
        }

        return array($incoming, $outgoing);
    }

    protected function throwErrors(array $errors, $id)
    {
        if (!empty($errors)) {
            throw new ValidationException($errors, 'Properties consistency error for element ' . $id);
        }
    }
}