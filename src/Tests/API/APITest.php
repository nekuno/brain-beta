<?php
/**
 * @author Manolo Salsas <manolez@gmail.com>
 */
namespace Tests\API;

use Everyman\Neo4j\Cypher\Query;
use Silex\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

abstract class APITest extends WebTestCase
{
    protected function getResponseByRoute($route, $method = 'GET', $data = array())
    {
        $client = static::createClient();
        $client->request($method, $route, array(), array(), array('CONTENT_TYPE' => 'application/json'), json_encode($data));
        return $client->getResponse();
    }

    public function createApplication()
    {
        $app = require __DIR__.'/../../app.php';
        require __DIR__.'/../../controllers.php';
        require __DIR__.'/../../routing.php';
        $app['debug'] = true;
        unset($app['exception_handler']);
        $app['session.test'] = true;

        return $app;
    }

    public function setUp()
    {
        parent::setUp();
        $app = $this->app;
        // Clean the database
        $query = new Query($app['neo4j.client'], 'MATCH n-[r]-m DELETE n, r, m');
        $query->getResultSet();
    }

    protected function assertJsonResponse(Response $response, $statusCode = 200, $context = "Undefined")
    {
        $this->assertStatusCode($response, $statusCode, $context);

        $this->assertJson($response->getContent(), $context . " response - Not a valid JSON string");

        $formattedResponse = json_decode($response->getContent(), true);

        $this->assertInternalType('array', $formattedResponse, $context . " response - JSON can't be converted into an array");

        return $formattedResponse;
    }

    protected function assertStatusCode(Response $response, $statusCode = 200, $context = "Undefined")
    {
        $this->assertEquals($statusCode, $response->getStatusCode(), $context . " response - Status Code is " . $response->getStatusCode() . ", expected " . $statusCode);
    }

    protected function createUserA()
    {
        $userData = $this->getUserAFixtures();
        return $this->getResponseByRoute('/users', 'POST', $userData);
    }

    protected function getUserA()
    {
        return $this->getResponseByRoute('/users/1');
    }

    protected function getUserAFixtures()
    {
        return array(
            'id' => 1,
            'username' => 'JohnDoe',
            'email' => 'nekuno-johndoe@gmail.com',
        );
    }
}