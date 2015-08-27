<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Model\Exception\ValidationException;

//Request::setTrustedProxies(array('127.0.0.1'));

$app['users.controller'] = $app->share(
    function () {

        return new \Controller\User\UserController;
    }
);

$app['users.profile.controller'] = $app->share(
    function () {

        return new \Controller\User\ProfileController();
    }
);

$app['users.privacy.controller'] = $app->share(
    function () {

        return new \Controller\User\PrivacyController();
    }
);

$app['users.data.controller'] = $app->share(
    function () {

        return new \Controller\User\DataController();
    }
);

$app['questionnaire.questions.controller'] = $app->share(
    function () {

        return new Controller\Questionnaire\QuestionController;
    }
);

$app['users.answers.controller'] = $app->share(
    function () {

        return new \Controller\User\AnswerController;
    }
);

$app['users.groups.controller'] = $app->share(
    function () use ($app) {

        return new \Controller\User\GroupController($app['users.groups.model']);
    }
);

$app['users.invitations.controller'] = $app->share(
    function () use ($app) {

        return new \Controller\User\InvitationController;
    }
);

$app['enterpriseUsers.controller'] = $app->share(
    function () use ($app) {

        return new \Controller\EnterpriseUser\EnterpriseUserController($app['enterpriseUsers.model']);
    }
);

$app['enterpriseUsers.groups.controller'] = $app->share(
    function () use ($app) {

        return new \Controller\EnterpriseUser\GroupController($app['users.groups.model'], $app['enterpriseUsers.model']);
    }
);

$app['enterpriseUsers.invitations.controller'] = $app->share(
    function () use ($app) {

        return new \Controller\EnterpriseUser\InvitationController($app['users.invitations.model'], $app['enterpriseUsers.model']);
    }
);

$app['fetch.controller'] = $app->share(
    function () {

        return new Controller\FetchController;
    }
);

$app['lookUp.controller'] = $app->share(
    function () use ($app) {

        return new \Controller\User\LookUpController($app['users.lookup.model']);
    }
);


/**
 * Middleware for filter some request
 */
$app->before(
    function (Request $request) use ($app) {
        // Parse request content and populate parameters
        if ($request->getContentType() === 'application/json' || $request->getContentType() === 'json') {
            $data = json_decode(utf8_encode($request->getContent()), true);
            if (json_last_error()) {
                return $app->json(array('Error parsing JSON data.'), 400);
            }
            $request->request->replace(is_array($data) ? $data : array());
        }
    }
);

/**
 * Error handling
 */
$app->error(
    function (\Exception $e, $code) use ($app) {

        $response = array('error' => $e->getMessage());

        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : array();

        if ($e instanceof ValidationException) {
            $response['validationErrors'] = $e->getErrors();
        }

        if ($app['debug']) {
            $response['debug'] = array(
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            );
        }

        return $app->json($response, $code, $headers);
    }

);
