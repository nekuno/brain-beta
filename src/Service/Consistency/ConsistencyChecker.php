<?php

namespace Service\Consistency;

use Model\Exception\ErrorList;
use Model\Exception\ValidationException;
use Service\Consistency\ConsistencyErrors\ConsistencyError;
use Service\Consistency\ConsistencyErrors\MissingPropertyConsistencyError;
use Service\Consistency\ConsistencyErrors\RelationshipAmountConsistencyError;
use Service\Consistency\ConsistencyErrors\RelationshipOtherLabelConsistencyError;
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
            $errors = new ErrorList();

            $count = count($totalRelationships);
            if ($count < $rule->getMinimum() || $count > $rule->getMaximum()) {
                $error = new RelationshipAmountConsistencyError();
                $error->setCurrentAmount($count);
                $error->setMinimum($rule->getMinimum());
                $error->setMaximum($rule->getMaximum());
                $error->setType($rule->getType());
                $errors->addError($rule->getType(), $error);
            }

            foreach ($incoming as $relationship) {
                if ($rule->getDirection() == 'outgoing'){
                    $errors[] = new ReverseRelationshipConsistencyError($relationship->getId());
                }

                $otherNodeLabels = $relationship->getStartNodeLabels();

                if (!in_array($rule->getOtherNode(), $otherNodeLabels)) {
                    $error = new RelationshipOtherLabelConsistencyError();
                    $error->setType($relationship->getType());
                    $error->setOtherNodeLabel($rule->getOtherNode());
                    $error->setRelationshipId($relationship->getId());
                    $errors->addError($relationship->getType(), $error);
                }

                $this->checkProperties($relationship->getProperties(), $relationship->getId(), $rule->getProperties());
            }

            foreach ($outgoing as $relationship) {
                if ($rule->getDirection() == 'incoming'){
                    $error = new ReverseRelationshipConsistencyError($relationship->getId());
                    $errors->addError($relationship->getType(), $error);
                }

                $otherNodeLabels = $relationship->getEndNodeLabels();


                if (!in_array($rule->getOtherNode(), $otherNodeLabels)) {
                    $error = new ConsistencyError();
                    $error->setMessage(sprintf('Label of node for relationship %d is not correct', $relationship->getId()));
                    $errors->addError($relationship->getType(), $error);
                }

                $this->checkProperties($relationship->getProperties(), $relationship->getId(), $rule->getProperties());
            }

            $this->throwErrors($errors, $nodeId);
        }
    }

    protected function checkProperties(array $properties, $id, array $propertyRules)
    {
        foreach ($propertyRules as $name => $propertyRule) {

            $errors = new ErrorList();
            $rule = new ConsistencyPropertyRule($name, $propertyRule);

            if (!isset($properties[$name])) {
                if (!$rule->isRequired()) {
                    continue;
                }

                $error = new MissingPropertyConsistencyError();
                $error->setPropertyName($name);
                $errors->addError($name, $error);
            } else {
                $value = $properties[$name];

                $options = $rule->getOptions();
                if (!empty($options)) {
                    if (!in_array($value, $options)) {
                        $error = new ConsistencyError();
                        $error->setMessage(sprintf('Element with id %d has property %s with invalid value %s', $id, $name, $value));
                        $errors->addError($name, $error);
                    }
                }

                switch ($rule->getType()) {
                    case null:
                        break;
                    case ConsistencyPropertyRule::TYPE_INTEGER:
                        if (!is_int($value)) {
                            $error = new ConsistencyError();
                            $error->setMessage(sprintf('Element with id %d has property %s with value %s which should be an integer', $id, $name, $value));
                            $errors->addError($name, $error);
                        } else {
                            if ($rule->getMaximum() && $value > $rule->getMaximum()) {
                                $error = new ConsistencyError();
                                $error->setMessage(sprintf('Element with id %d has property %d greater than maximum %d', $id, $name, $value, $rule->getMaximum()));
                                $errors->addError($name, $error);
                            }

                            if ($rule->getMinimum() && $value < $rule->getMinimum()) {
                                $error = new ConsistencyError();
                                $error->setMessage(sprintf('Element with id %d has property %d lower than minimum %d', $id, $name, $value, $rule->getMinimum()));
                                $errors->addError($name, $error);
                            }
                        }
                        break;
                    case ConsistencyPropertyRule::TYPE_BOOLEAN:
                        if (!is_bool($value)) {
                            $error = new ConsistencyError();
                            $error->setMessage(sprintf('Element with id %d has property %s that should be a value', $id, json_encode($value)));
                            $errors->addError($name, $error);
                        };
                        break;
                    case ConsistencyPropertyRule::TYPE_ARRAY:
                        if (!is_array($value)) {
                            $error = new ConsistencyError();
                            $error->setMessage(sprintf('Element with id %d has property %s with value %s which should be an array', $id, $name, $value));
                            $errors->addError($name, $error);
                        };
                        break;
                    case ConsistencyPropertyRule::TYPE_DATETIME:
                        $date = $this->getDateTimeFromTimestamp($value);

                        if ($rule->getMaximum() && $date > new \DateTime($rule->getMaximum())) {
                            $error = new ConsistencyError();
                            $error->setMessage(sprintf('Element with id %d has property %s later than maximum %s', $id, $name, $rule->getMaximum()));
                            $errors->addError($name, $error);
                        }
                        if ($rule->getMinimum() && $value < $rule->getMinimum()) {
                            $error = new ConsistencyError();
                            $error->setMessage(sprintf('Element with id %d has property %s earlier than minimum %s', $id, $name, $rule->getMinimum()));
                            $errors->addError($name, $error);
                        }
                        break;
                    default:
                        break;
                }
            }

            $this->throwErrors($errors, $id);
        }
    }

    protected function getDateTimeFromTimestamp($timestamp)
    {
        $dateTime = new \DateTime();
        $dateTime->setTimestamp($timestamp);

        return $dateTime;
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

    protected function throwErrors(ErrorList $errors, $id)
    {
        if ($errors->hasErrors()) {
            throw new ValidationException($errors, 'Properties consistency error for element ' . $id);
        }
    }
}