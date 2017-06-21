<?php

namespace Controller\Security;

use Service\AuthService;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;


class AuthController
{

    public function preflightAction(Request $request)
    {
        $response = new Response();
        $response->headers->set('Access-Control-Allow-Methods', $request->headers->get('Access-Control-Request-Method'));
        $response->headers->set('Access-Control-Allow-Headers', $request->headers->get('Access-Control-Request-Headers'));

        return $response;
    }

    public function loginAction(Request $request, Application $app)
    {

        $username = $request->request->get('username');
        $password = $request->request->get('password');
	    $oauth = $request->request->get('oauth');
	    $locale = $request->query->get('locale');

	    /* @var $authService AuthService */
	    $authService = $app['auth.service'];
	    if ($username && $password) {
	        $jwt = $authService->login($username, $password);
	    }
	    elseif ($oauth) {
		    $jwt = $authService->loginByResourceOwner($oauth);
	    }
	    else {
		    throw new BadRequestHttpException('Los datos introducidos no coinciden con nuestros registros.');
	    }

	    $user = $authService->getUser($jwt);

	    $profile = $app['users.profile.model']->getById($user->getId(), $locale);

	    $questionsFilters = array('id' => $user->getId(), 'locale' => $locale);
	    $countQuestions = $app['users.questions.model']->countTotal($questionsFilters);

        return $app->json(array('jwt' => $jwt, 'profile' => $profile, 'questionsTotal' => $countQuestions));
    }
}