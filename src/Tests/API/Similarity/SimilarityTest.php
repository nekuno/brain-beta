<?php

namespace Tests\API\Similarity;

use Model\Similarity\SimilarityManager;

class SimilarityTest extends SimilarityAPITest
{
    public function testSimilarity()
    {
        $this->assertSimilarityValues();
    }

    public function assertSimilarityValues()
    {
        /** @var SimilarityManager $similarityModel */
        $similarityModel = $this->app['users.similarity.model'];
        $similarityModel->getSimilarity(1, 2);
        $response = $this->getSimilarity(2);
    }
}