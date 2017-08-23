<?php

namespace Tests\API;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\SchemaTool;
use Silex\Application;
use Silex\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Service\AuthService;

abstract class APITest extends WebTestCase
{
    const OWN_USER_ID = 1;
    const OTHER_USER_ID = 2;
    const UNDEFINED_USER_ID = 3;

    protected $app;

    public function createApplication()
    {
        $app = require __DIR__ . '/../../app.php';
        require __DIR__ . '/../../controllers.php';
        require __DIR__ . '/../../routing.php';
        $app['debug'] = true;
        unset($app['exception_handler']);
        $app['session.test'] = true;

        return $app;
    }

    public function setUp()
    {
        parent::setUp();
        /* @var $app Application */
        $app = $this->app;
        $fixtures = new TestingFixtures($this->app);
        $fixtures->load();
        // Create brain DB
        $em = $app['orm.ems']['mysql_brain'];
        $schemaTool = new SchemaTool($em);
        $schemaTool->dropDatabase();
        $metadatas = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($metadatas);

        /* @var $bm Connection */
        $bm = $app['dbs']['mysql_brain'];
        $bm->executeQuery('DROP TABLE IF EXISTS chat_message');
        $bm->executeQuery('CREATE TABLE chat_message (id INTEGER PRIMARY KEY NOT NULL, text VARCHAR(255) NOT NULL, createdAt DATETIME NOT NULL, readed TINYINT(1) NOT NULL, user_from INT DEFAULT NULL, user_to INT DEFAULT NULL)');
    }

    protected function getResponseByRouteWithCredentials($route, $method = 'GET', $data = array(), $userId = self::OWN_USER_ID)
    {
        $headers = array();
        if ($userId) {
            $headers = $this->tryToGetJwtByUserId($userId);
        }

        return $this->getResponseByRoute($route, $method, $data, $headers);
    }

    protected function getResponseByRouteWithoutCredentials($route, $method = 'GET', $data = array())
    {
        return $this->getResponseByRoute($route, $method, $data);
    }

    private function getResponseByRoute($route, $method = 'GET', $data = array(), $headers = array())
    {
        $headers += array('CONTENT_TYPE' => 'application/json');
        $client = static::createClient();
        $client->request($method, $route, array(), array(), $headers, json_encode($data));

        return $client->getResponse();
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

    protected function assertValidationErrorFormat($exception)
    {
        $this->assertArrayHasKey('error', $exception, "Validation exception has not error key");
        $this->assertArrayHasKey('validationErrors', $exception, "Validation exception has not validationErrors key");
        $this->assertEquals('Validation error', $exception['error'], "error key is not Validation error");
    }

    protected function assertArrayOfType($type, $array, $message)
    {
        $this->isType('array')->evaluate($array, 'Is not an array when '. $message);
        foreach ($array as $item)
        {
            $this->isType($type)->evaluate($item, 'Is not an item of type ' . $type . ' when ' .$message);
        }
    }

    protected function loginOwnUser()
    {
        return $this->getResponseByRouteWithCredentials('/login', 'POST', $this->getUserAFixtures());
    }

    protected function getUserAFixtures()
    {
        return array(
            'resourceOwner' => 'facebook',
            'accessToken' => $this->app['userA.access_token'],
        );
    }

    private function tryToGetJwtByUserId($userId)
    {
        try {
            /** @var AuthService $authService */
            $authService = $this->app['auth.service'];
            $jwt = $authService->getToken($userId);

            return array('HTTP_PHP_AUTH_DIGEST' => 'Bearer ' . $jwt);
        } catch (\Exception $e) {
            return array();
        }
    }
}