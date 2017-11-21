<?php

namespace Service\Consistency;

use Event\ExceptionEvent;
use Everyman\Neo4j\Label;
use Everyman\Neo4j\Node;
use Model\Exception\ValidationException;
use Model\Neo4j\GraphManager;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ConsistencyCheckerService
{
    protected $graphManager;
    protected $dispatcher;
    protected $consistencyRules;

    /**
     * ConsistencyChecker constructor.
     * @param GraphManager $graphManager
     * @param EventDispatcher $dispatcher
     * @param array $consistencyRules
     */
    public function __construct(GraphManager $graphManager, EventDispatcher $dispatcher, array $consistencyRules)
    {
        $this->graphManager = $graphManager;
        $this->dispatcher = $dispatcher;
        $this->consistencyRules = $consistencyRules;
    }

    public function getDatabaseErrors($label = null)
    {
        //dispatch consistency start
        $this->dispatcher->dispatch(\AppEvents::CONSISTENCY_START);
        $paginationSize = 1000;
        $offset = 0;

        $errorList = array();
        do {
            $qb = $this->graphManager->createQueryBuilder();

            if (null !== $label) {
                $qb->match("(a:$label)");
            } else {
                $qb->match('(a)');
            }

            $qb->returns('a')
                ->skip('{offset}')
                ->limit($paginationSize)
                ->setParameter('offset', $offset);;

            $result = $qb->getQuery()->getResultSet();
            foreach ($result as $row) {
                /** @var Node $node */
                $node = $row->offsetGet('a');
                $nodeErrors = $this->checkNode($node);
                $errorList = array_merge($errorList, $nodeErrors);
            }

            $offset += $paginationSize;
            $moreResultsAvailable = $result->count() >= $paginationSize;

        } while ($moreResultsAvailable);

        //dispatch consistency end
        $this->dispatcher->dispatch(\AppEvents::CONSISTENCY_END);

        return $errorList;
    }

    /** @var $errors ConsistencyError[]
     * @return ConsistencyError[]
     */
    public function solveDatabaseErrors(array $errors)
    {
        foreach ($errors as $error) {
            $solver = $this->chooseSolver($error->getRule());
            $isSolved = $solver->solve($error);
            $error->setSolved($isSolved);
        }

        return $errors;
    }

    /**
     * @param Node $node
     * @return array
     */
    public function checkNode(Node $node)
    {
        /** @var Label[] $labels */
        $labelNames = $this->getLabelNames($node);
        $nodeId = $node->getId();

        $rules = $this->consistencyRules;
        $errors = array();

        foreach ($rules['nodes'] as $rule) {
            if (!in_array($rule['label'], $labelNames)) {
                continue;
            }

            $nodeRule = new ConsistencyNodeRule($rule);
            $checker = $this->chooseChecker($nodeRule);

            try {
                $checker->check($node, $nodeRule);
            } catch (ValidationException $e) {
                $newErrors = $e->getErrors();
                foreach ($newErrors as $newError) {
                    /** @var ConsistencyError $newError */
                    $newError->setRule($nodeRule);
                    $newError->setNodeId($nodeId);
                }
                $errors += $newErrors;

                $this->dispatcher->dispatch(\AppEvents::CONSISTENCY_ERROR, new ExceptionEvent($e, 'Checking node ' . $nodeId));
            }
        }

        return $errors;
    }

    /**
     * @param ConsistencyNodeRule $rule
     * @return ConsistencyChecker
     */
    protected function chooseChecker(ConsistencyNodeRule $rule)
    {
        $ruleClass = $rule->getCheckerClass();
        $defaultClass = ConsistencyChecker::class;

        if ($ruleClass){
            return new $ruleClass();
        } else {
            return new $defaultClass();
        }
    }

    /**
     * @param ConsistencyNodeRule $rule
     * @return ConsistencySolver
     */
    protected function chooseSolver(ConsistencyNodeRule $rule)
    {
        $ruleClass = $rule->getSolverClass();
        $defaultClass = ConsistencySolver::class;

        if ($ruleClass){
            return new $ruleClass($this->graphManager);
        } else {
            return new $defaultClass($this->graphManager);
        }
    }

    static function getLabelNames(Node $node)
    {
        /** @var Label[] $labels */
        $labels = $node->getLabels();
        $labelNames = array();
        foreach ($labels as $label) {
            $labelNames[] = $label->getName();
        }

        return $labelNames;
    }
}