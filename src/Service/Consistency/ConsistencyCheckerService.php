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
    protected $consistency;

    /**
     * ConsistencyChecker constructor.
     * @param GraphManager $graphManager
     * @param EventDispatcher $dispatcher
     * @param array $consistency
     */
    public function __construct(GraphManager $graphManager, EventDispatcher $dispatcher, array $consistency)
    {
        $this->graphManager = $graphManager;
        $this->dispatcher = $dispatcher;
        $this->consistency = $consistency;
    }

    public function checkDatabase($label = null)
    {
        //dispatch consistency start
        $this->dispatcher->dispatch(\AppEvents::CONSISTENCY_START);
        $paginationSize = 1000;
        $offset = 0;

        $errors = array();
        do {
            var_dump($offset);
            var_dump(count($errors));

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
                $node = $row->offsetGet('a');
                $newErrors = $this->checkNode($node);
                foreach ($newErrors as $field =>$id) {
                    $errors[$field][] = $id;
                }
            }

            $offset += $paginationSize;
            $moreResultsAvailable = $result->count() >= $paginationSize;

        } while ($moreResultsAvailable);

        //dispatch consistency end
        $this->dispatcher->dispatch(\AppEvents::CONSISTENCY_END);

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

        $rules = $this->consistency;
        $errors = array();
        foreach ($rules['nodes'] as $rule) {
            if (!in_array($rule['label'], $labelNames)) {
                continue;
            }

            if (isset($rule['class'])) {
                $checker = new $rule['class']();
            } else {
                $checker = new ConsistencyChecker();
            }

            $nodeRule = new ConsistencyNodeRule($rule);
            try {
                $checker->check($node, $nodeRule);
            } catch (ValidationException $e) {
                foreach ($e->getErrors() as $field => $messages) {
                    foreach ($messages as $type => $message) {
                        $key = $field . '-' . $type . ' -> ' . $message;
                        $errors[$key] = $node->getId();
                    }
                }
                $this->dispatcher->dispatch(\AppEvents::CONSISTENCY_ERROR, new ExceptionEvent($e, 'Checking node ' . $node->getId()));
            }
        }

        return $errors;
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