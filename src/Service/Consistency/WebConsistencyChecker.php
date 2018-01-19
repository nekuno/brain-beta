<?php

namespace Service\Consistency;

use Service\Consistency\ConsistencyErrors\ConsistencyError;

class WebConsistencyChecker extends ConsistencyChecker
{
    protected function checkProperties(array $properties, $id, array $propertyRules)
    {
        $isWellProcessed = isset($properties['processed']) && $properties['processed'] != 0;
        if (!$isWellProcessed) {
            return;
        }

        $hasTitle = isset($properties['title']);
        $hasThumbnail = isset($properties['thumbnail']);

        if (!$hasTitle && !$hasThumbnail)
        {
            $error = new ConsistencyError();
            $error->setMessage(sprintf('Web with id %d is marked as processed but lacks title and thumbnail', $id));
            $this->throwErrors(array($error), $id);
        }
    }

}