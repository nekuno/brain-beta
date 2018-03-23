<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Model\Exception\ValidationException;
use Model\Neo4j\Neo4jException;
use Model\User\User;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

//Request::setTrustedProxies(array('127.0.0.1'));

$app['auth.controller'] = function () {

    return new \Controller\Security\AuthController;
};

$app['users.controller'] = function () {

    return new \Controller\User\UserController;
};

$app['users.profile.controller'] = function () {

    return new \Controller\User\ProfileController;
};

$app['users.privacy.controller'] = function () {

    return new \Controller\User\PrivacyController;
};

$app['users.data.controller'] = function () {

    return new \Controller\User\DataController;
};

$app['links.controller'] = function () {

    return new \Controller\LinkController;
};

$app['questionnaire.questions.controller'] = function () {

    return new Controller\Questionnaire\QuestionController;
};

$app['users.answers.controller'] = function () {

    return new \Controller\User\AnswerController;
};

$app['users.groups.controller'] = function () {

    return new \Controller\User\GroupController;
};

$app['users.threads.controller'] = function () {

    return new \Controller\User\ThreadController;
};

$app['users.invitations.controller'] = function () {

    return new \Controller\User\InvitationController;
};

$app['users.relations.controller'] = function () {

    return new \Controller\User\RelationsController;
};

$app['users.tokens.controller'] = function() {

    return new \Controller\User\TokensController;
};

$app['users.photos.controller'] = function() {

    return new \Controller\User\PhotoController();
};

$app['users.devices.controller'] = function() {

    return new \Controller\User\DeviceController;
};

$app['client.controller'] = function() {

    return new Controller\ClientController;
};

$app['fetch.controller'] = function () {

    return new Controller\FetchController;
};

$app['lookUp.controller'] = function () {

    return new \Controller\User\LookUpController;
};

$app['admin.groups.controller'] = function () {

    return new \Controller\Admin\GroupController;
};

$app['admin.invitations.controller'] = function () {

    return new \Controller\Admin\InvitationController;
};

$app['admin.enterpriseUsers.controller'] = function () {

    return new \Controller\Admin\EnterpriseUser\EnterpriseUserController;
};

$app['admin.enterpriseUsers.groups.controller'] = function () {

    return new \Controller\Admin\EnterpriseUser\GroupController;
};

$app['admin.enterpriseUsers.communities.controller'] = function () {

    return new \Controller\Admin\EnterpriseUser\CommunityController;
};

$app['admin.enterpriseUsers.invitations.controller'] = function () {

    return new \Controller\Admin\EnterpriseUser\InvitationController;
};

$app['admin.users.controller'] = function () {

    return new \Controller\Admin\UserController;
};

$app['admin.userTracking.controller'] = function () {

    return new \Controller\Admin\UserTrackingController;
};

$app['admin.userReport.controller'] = function () {

    return new \Controller\Admin\UserReportController;
};

$app['admin.content.controller'] = function () {

    return new \Controller\Admin\ContentController;
};

$app['admin.developers.controller'] = function () {

    return new \Controller\Admin\DevelopersController;
};

$app['admin.questions.controller'] = function() {

    return new \Controller\Admin\QuestionController;
};

$app['instant.users.controller'] = function () {

    return new \Controller\Instant\UserController;
};

$app['instant.relations.controller'] = function () {

    return new \Controller\Instant\RelationsController;
};

$app['instant.pushNotifications.controller'] = function () {

    return new \Controller\Instant\PushNotificationsController;
};


/**
 * Middleware for filter some request
 */
$app->before(
    function (Request $request) use ($app) {
        // Parse request content and populate parameters
        if ($request->getContentType() === 'application/json' || $request->getContentType() === 'json') {
            $encoding = mb_detect_encoding($request->getContent(), 'auto');
            $content = $encoding === 'UTF-8' ? $request->getContent() : utf8_encode($request->getContent());
            $data = json_decode($content, true);
            if (json_last_error()) {
                return $app->json(array('Error parsing JSON data.'), 400);
            }
            $request->request->replace(is_array($data) ? $data : array());
        }

        return false;
    }
);

$app->before(
    function (Request $request) use ($app) {
        if ($app['user'] instanceof User) {
            /* @var $user User */
            $user = $app['user'];
            if ($user->isGuest() && in_array($request->getMethod(), array('POST', 'PUT', 'DELETE'))) {
                throw new MethodNotAllowedHttpException(array('GET'), 'Method not supported');
            }
        }
    }
);

$app->after(
    function (Request $request, Response $response) {
        $response->headers->set('Access-Control-Allow-Origin', '*');
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

        if ($e instanceof Neo4jException) {
            $response['error'] = isset($e->getData()['message']) ? $e->getData()['message'] : $e->getData() ? $e->getData() : $e->getMessage();
            $response['query'] = $e->getQuery();
            $response['headers'] = $e->getHeaders();
            $response['data'] = $e->getData();
        }

        if ($app['debug']) {
            $response['debug'] = array(
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString())
            );
        }

        return $app->json($response, $code, $headers);
    }

);
