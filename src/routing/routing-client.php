<?php

use Model\User\RelationsModel;

/**
 * Client and social routes
 */

/** User Routes */
$app->match('{url}', 'auth.controller:preflightAction')->assert('url', '.+')->method('OPTIONS');
$app->post('/login', 'auth.controller:loginAction');
$app->get('/autologin', 'users.controller:autologinAction');

$app->get('/users', 'users.controller:getAction');
$app->get('/users/{slug}', 'users.controller:getOtherAction');
$app->get('/public/users/{slug}', 'users.controller:getPublicAction');
$app->post('/register', 'users.controller:registerAction');
$app->put('/users', 'users.controller:putAction');
$app->get('/users/available/{username}', 'users.controller:availableAction');
$app->post('users/enable', 'users.controller:setEnableAction');

$app->get('/profile', 'users.profile.controller:getAction');
$app->get('/profile/{userId}', 'users.profile.controller:getOtherAction')->value('userId', null);
$app->put('/profile', 'users.profile.controller:putAction');
$app->get('/profile/metadata', 'users.profile.controller:getMetadataAction');
$app->get('/profile/categories', 'users.profile.controller:getCategoriesAction');
$app->get('/profile/filters', 'users.profile.controller:getFiltersAction');
$app->get('/profile/tags/{type}', 'users.profile.controller:getProfileTagsAction');

$app->get('/privacy', 'users.privacy.controller:getAction');
$app->post('/privacy', 'users.privacy.controller:postAction');
$app->put('/privacy', 'users.privacy.controller:putAction');
$app->delete('/privacy', 'users.privacy.controller:deleteAction');
$app->get('/privacy/metadata', 'users.privacy.controller:getMetadataAction');

/** Relations routes */
$app->get('/blocks', 'users.relations.controller:indexAction')->value('relation', RelationsModel::BLOCKS);
$app->get('/blocks/{to}', 'users.relations.controller:getAction')->value('relation', RelationsModel::BLOCKS);
$app->post('/blocks/{to}', 'users.relations.controller:postAction')->value('relation', RelationsModel::BLOCKS);
$app->delete('/blocks/{to}', 'users.relations.controller:deleteAction')->value('relation', RelationsModel::BLOCKS);

$app->get('/favorites', 'users.relations.controller:indexAction')->value('relation', RelationsModel::FAVORITES);
$app->get('/favorites/{to}', 'users.relations.controller:getAction')->value('relation', RelationsModel::FAVORITES);
$app->post('/favorites/{to}', 'users.relations.controller:postAction')->value('relation', RelationsModel::FAVORITES);
$app->delete('/favorites/{to}', 'users.relations.controller:deleteAction')->value('relation', RelationsModel::FAVORITES);

$app->get('/likes', 'users.relations.controller:indexAction')->value('relation', RelationsModel::LIKES);
$app->get('/likes/{to}', 'users.relations.controller:getAction')->value('relation', RelationsModel::LIKES);
$app->post('/likes/{to}', 'users.relations.controller:postAction')->value('relation', RelationsModel::LIKES);
$app->delete('/likes/{to}', 'users.relations.controller:deleteAction')->value('relation', RelationsModel::LIKES);

$app->get('/dislikes', 'users.relations.controller:indexAction')->value('relation', RelationsModel::DISLIKES);
$app->get('/dislikes/{to}', 'users.relations.controller:getAction')->value('relation', RelationsModel::DISLIKES);
$app->post('/dislikes/{to}', 'users.relations.controller:postAction')->value('relation', RelationsModel::DISLIKES);
$app->delete('/dislikes/{to}', 'users.relations.controller:deleteAction')->value('relation', RelationsModel::DISLIKES);

$app->get('/ignores', 'users.relations.controller:indexAction')->value('relation', RelationsModel::IGNORES);
$app->get('/ignores/{to}', 'users.relations.controller:getAction')->value('relation', RelationsModel::IGNORES);
$app->post('/ignores/{to}', 'users.relations.controller:postAction')->value('relation', RelationsModel::IGNORES);
$app->delete('/ignores/{to}', 'users.relations.controller:deleteAction')->value('relation', RelationsModel::IGNORES);

$app->get('/reports', 'users.relations.controller:indexAction')->value('relation', RelationsModel::REPORTS);
$app->get('/reports/{to}', 'users.relations.controller:getAction')->value('relation', RelationsModel::REPORTS);
$app->post('/reports/{to}', 'users.relations.controller:postAction')->value('relation', RelationsModel::REPORTS);
$app->delete('/reports/{to}', 'users.relations.controller:deleteAction')->value('relation', RelationsModel::REPORTS);

$app->get('/relations/{to}', 'users.relations.controller:getAction');
$app->get('/other-relations/{from}', 'users.relations.controller:getOtherAction');

$app->get('/matching/{userId}', 'users.controller:getMatchingAction');
$app->get('/similarity/{userId}', 'users.controller:getSimilarityAction');
$app->get('content', 'users.controller:getUserContentAction');
$app->get('/content/compare/{userId}', 'users.controller:getUserContentCompareAction');
$app->get('/content/tags', 'users.controller:getUserContentTagsAction');
$app->post('/content/rate', 'users.controller:rateContentAction');
$app->post('/content/report', 'users.controller:reportContentAction');
$app->get('/filters', 'users.controller:getAllFiltersAction');
$app->get('/threads', 'users.threads.controller:getByUserAction');
$app->post('/threads', 'users.threads.controller:postAction');
$app->get('/recommendations/users', 'users.controller:getUserRecommendationAction');
$app->get('/recommendations/content', 'users.controller:getContentRecommendationAction');
$app->get('/recommendations/content/tags', 'users.controller:getContentAllTagsAction');
$app->get('/status', 'users.controller:statusAction');
$app->get('/stats', 'users.controller:statsAction');
$app->get('/stats/compare/{userId}', 'users.controller:statsCompareAction');

$app->get('/affinity/{linkId}', 'users.controller:getAffinityAction');

/** Answer routes */
$app->get('/answers', 'users.answers.controller:indexAction');
$app->get('/answers/compare/{userId}', 'users.answers.controller:getUserAnswersCompareAction');
$app->post('/answers/explain', 'users.answers.controller:explainAction');
$app->post('/answers', 'users.answers.controller:answerAction');
$app->get('/users/{userId}/answers/count', 'users.answers.controller:countAction');
$app->get('/answers/{questionId}', 'users.answers.controller:getAnswerAction');
$app->delete('/answers/{questionId}', 'users.answers.controller:deleteAnswerAction');

$app->get('/data/status', 'users.data.controller:getStatusAction')->value('resourceOwner', null);

/** Questionnaire routes */
$app->get('/questions/next', 'questionnaire.questions.controller:getNextQuestionAction');
$app->get('/other-questions/{userId}/next', 'questionnaire.questions.controller:getNextOtherQuestionAction');
$app->get('/questions/register', 'questionnaire.questions.controller:getDivisiveQuestionsAction');
$app->post('/questions', 'questionnaire.questions.controller:postQuestionAction');
$app->get('/questions/{questionId}', 'questionnaire.questions.controller:getQuestionAction');
$app->post('/questions/{questionId}/skip', 'questionnaire.questions.controller:skipAction');
$app->post('/questions/{questionId}/report', 'questionnaire.questions.controller:reportAction');

/** Content routes */
$app->post('/add/links', 'fetch.controller:addLinkAction');
$app->put('links/images', 'links.controller:checkImagesAction');

/** LookUp routes */
$app->get('/lookUp', 'lookUp.controller:getAction');
$app->post('lookUp/users/{userId}', 'lookUp.controller:setAction');

$app->post('/lookUp/webHook', 'lookUp.controller:setFromWebHookAction')->bind('setLookUpFromWebHook');

/** Thread routes */
$app->get('/threads/{threadId}/recommendation', 'users.threads.controller:getRecommendationAction');
$app->put('/threads/{threadId}', 'users.threads.controller:putAction');
$app->delete('/threads/{threadId}', 'users.threads.controller:deleteAction');

/** Group routes */
$app->get('/groups/{groupId}', 'users.groups.controller:getAction');
$app->post('/groups', 'users.groups.controller:postAction');
$app->get('/groups/{groupId}/members', 'users.groups.controller:getMembersAction');
$app->post('/groups/{groupId}/members', 'users.groups.controller:addUserAction');
$app->delete('/groups/{groupId}/members', 'users.groups.controller:removeUserAction');
$app->get('/groups/{groupId}/contents', 'users.groups.controller:getContentsAction');

/** Invitation routes */
$app->get('/invitations', 'users.invitations.controller:indexByUserAction');
$app->get('/invitations/available', 'users.invitations.controller:getAvailableByUserAction');
$app->post('/invitations/available/{nOfAvailable}', 'users.invitations.controller:setUserAvailableAction');
$app->get('/invitations/{invitationId}', 'users.invitations.controller:getAction');
$app->post('/invitations', 'users.invitations.controller:postAction');
$app->put('/invitations/{invitationId}', 'users.invitations.controller:putAction');
$app->delete('/invitations/{invitationId}', 'users.invitations.controller:deleteAction');
$app->post('/invitations/token/validate/{token}', 'users.invitations.controller:validateTokenAction');
$app->post('/invitations/consume/{token}', 'users.invitations.controller:consumeAction');
$app->get('/invitations/count', 'users.invitations.controller:countByUserAction');
$app->post('/invitations/{invitationId}/send', 'users.invitations.controller:sendAction');

/**
 * Tokens routes
 */

$app->post('/tokens/{resourceOwner}', 'users.tokens.controller:postAction');
$app->put('/tokens/{resourceOwner}', 'users.tokens.controller:putAction');

/**
 * Client routes
 */
$app->get('/client/status', 'client.controller:getStatusAction');
$app->get('/client/blog-feed', 'client.controller:getBlogFeedAction');

/** Photo routes */
$app->get('/photos', 'users.photos.controller:getAllAction');
$app->get('/photos/{userId}', 'users.photos.controller:getAction');
$app->post('/photos', 'users.photos.controller:postAction');
$app->post('/photos/{photoId}/profile', 'users.photos.controller:postProfileAction');
$app->delete('/photos/{photoId}', 'users.photos.controller:deleteAction');

/** Push notifications routes */
$app->post('/notifications/subscribe', 'users.devices.controller:subscribeAction');
$app->post('/notifications/unsubscribe', 'users.devices.controller:unSubscribeAction');