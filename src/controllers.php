<?php

use Symfony\Component\HttpFoundation\Request;

//Request::setTrustedProxies(array('127.0.0.1'));

$app['users.controller'] = $app->share(
    function () {

        return new Controller\UserController;
    }
);

$app['questions.controller'] = $app->share(
    function () {

        return new Controller\QuestionController;
    }
);

$app['answers.controller'] = $app->share(
    function () {

        return new Controller\AnswerController;
    }
);

$app['fetch.controller'] = $app->share(
    function () {

        return new Controller\FetchController;
    }
);

$app['data.status.controller'] = $app->share(
    function () {

        return new \Controller\Data\StatusController();
    }
);

/**
 * Middleware for filter some request
 */
$app->before(
    function (Request $request) use ($app) {

        // Filter access by IP
        $validClientIP = array(
            '127.0.0.1'
        );

        if (!in_array($ip = $request->getClientIp(), $validClientIP)) {
            return $app->json(array(), 403); // 403 Access forbidden
        }

        // Parse request content and populate parameters
        if ($request->getContentType() === 'json') {
            $data = json_decode(utf8_encode($request->getContent()), true);
            $request->request->replace(is_array($data) ? $data : array());
        }
    }
);

/**
 * Error handling
 */
$app->error(
    function (\Exception $e, $code) use ($app) {

        $response = array(
            "error" => array(
                "code" => $code,
                "text" => "An error ocurred",
            )
        );

        if ($app['debug']) {
            $response['error']['code'] = $e->getCode();
            $response['error']['text'] = $e->getMessage();
            $response['error']['file'] = $e->getFile();
            $response['error']['line'] = $e->getLine();
            $response['error']['trace'] = $e->getTrace();
        }

        return $app->json($response, $code);
    }

);
