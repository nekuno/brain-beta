<?php

namespace Provider;

use Model\LanguageText\LanguageTextManager;
use Model\Location\LocationManager;
use Model\User\Photo\GalleryManager;
use Model\User\Photo\PhotoManager;
use Model\EnterpriseUser\EnterpriseUserModel;
use Model\Link\LinkModel;
use Model\Metadata\CategoryMetadataManager;
use Model\Metadata\MetadataManagerFactory;
use Model\Metadata\MetadataUtilities;
use Model\Popularity\PopularityManager;
use Model\Popularity\PopularityPaginatedModel;
use Model\User\ContactModel;
use Model\User\Content\ContentReportModel;
use Model\User\Device\DeviceModel;
use Model\User\ProfileOptionManager;
use Model\User\Question\Admin\QuestionAdminBuilder;
use Model\User\Question\Admin\QuestionAdminManager;
use Model\User\Question\QuestionCategory\QuestionCategoryManager;
use Model\User\Question\QuestionCorrelationManager;
use Model\User\Question\QuestionModel;
use Model\User\Affinity\AffinityModel;
use Model\User\Question\AnswerManager;
use Model\EnterpriseUser\CommunityModel;
use Model\User\Content\ContentComparePaginatedModel;
use Model\User\Content\ContentPaginatedModel;
use Model\User\Content\ContentTagModel;
use Model\User\Filters\FilterContentManager;
use Model\User\Filters\FilterUsersManager;
use Model\User\GhostUser\GhostUserManager;
use Model\User\Group\GroupContentPaginatedModel;
use Model\User\Group\GroupModel;
use Model\User\InvitationModel;
use Model\User\LookUpModel;
use Model\User\Matching\MatchingModel;
use Model\User\Question\OldQuestionComparePaginatedModel;
use Model\User\PrivacyModel;
use Model\User\ProfileModel;
use Model\User\ProfileTagModel;
use Model\User\Question\QuestionComparePaginatedModel;
use Model\User\Question\Admin\QuestionsAdminPaginatedModel;
use Model\User\Question\QuestionNextSelector;
use Model\User\Question\UserAnswerPaginatedModel;
use Model\User\Rate\RateModel;
use Model\User\Recommendation\ContentPopularRecommendationPaginatedModel;
use Model\User\Recommendation\ContentRecommendationPaginatedModel;
use Model\User\Recommendation\ContentRecommendationTagModel;
use Model\User\Recommendation\UserPopularRecommendationPaginatedModel;
use Model\User\Recommendation\UserRecommendationPaginatedModel;
use Model\User\RelationsModel;
use Model\User\RelationsPaginatedModel;
use Model\User\Shares\SharesManager;
use Model\User\Similarity\SimilarityModel;
use Model\User\SocialNetwork\LinkedinSocialNetworkModel;
use Model\User\SocialNetwork\SocialProfileManager;
use Model\User\Thread\ThreadCachedManager;
use Model\User\Thread\ThreadDataManager;
use Model\User\Thread\ThreadManager;
use Model\User\Thread\ThreadPaginatedModel;
use Model\User\Token\TokensModel;
use Model\User\Token\TokenStatus\TokenStatusManager;
use Model\User\UserDisabledPaginatedModel;
use Model\User\Stats\UserStatsCalculator;
use Manager\UserManager;
use Model\User\UserPaginatedModel;
use Model\User\UserTrackingModel;
use Security\UserProvider;
use Service\Validator\FilterUsersValidator;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;

class ModelsServiceProvider implements ServiceProviderInterface
{

    /**
     * { @inheritdoc }
     */
    public function register(Application $app)
    {

        $app['security.password_encoder'] = $app->share(
            function () {

                return new MessageDigestPasswordEncoder();
            }
        );

        $app['security.users_provider'] = $app['users'] = $app->share(
            function ($app) {

                return new UserProvider($app['users.manager']);
            }
        );

        $app['users.manager'] = $app->share(
            function ($app) {

                return new UserManager($app['dispatcher'], $app['neo4j.graph_manager'], $app['security.password_encoder'], $app['users.photo.manager'], $app['slugify'], $app['images_web_dir']);
            }
        );

        $app['users.tokens.model'] = $app->share(
            function ($app) {

                $validator = $app['validator.factory']->build('tokens');
                return new TokensModel($app['dispatcher'], $app['neo4j.graph_manager'], $validator);
            }
        );

        $app['users.tokenStatus.manager'] = $app->share(
            function ($app) {
                $validator = $app['validator.factory']->build('tokenStatus');
                return new TokenStatusManager($app['neo4j.graph_manager'], $validator);
            }
        );

        $app['users.location.manager'] = $app->share(
            function ($app) {
                return new LocationManager($app['neo4j.graph_manager']);
            }
        );

        $app['users.profile.model'] = $app->share(
            function ($app) {
                $profileValidator = $app['validator.factory']->build('profile');

                return new ProfileModel($app['neo4j.graph_manager'], $app['users.profileMetadata.manager'], $app['users.profileOption.manager'], $app['users.profile.tag.model'], $app['users.location.manager'], $app['metadata.utilities'],  $app['dispatcher'], $profileValidator);
            }
        );

        $app['users.profileOption.manager'] = $app->share(
            function ($app) {

                return new ProfileOptionManager($app['neo4j.graph_manager'], $app['users.profileMetadata.manager'], $app['metadata.utilities']);
            }
        );

        $app['users.metadataManager.factory'] = $app->share(
            function ($app) {

                return new MetadataManagerFactory($app['metadata.config'], $app['translator'], $app['metadata.utilities'], $app['fields'], $app['locale.options']['default']);
            }
        );

        $app['users.userFilterMetadata.manager'] = $app->share(
            function ($app) {
                return $app['users.metadataManager.factory']->build('user_filter');
            }
        );

        $app['users.profileMetadata.manager'] = $app->share(
            function ($app) {

                return $app['users.metadataManager.factory']->build('profile');
            }
        );

        $app['users.profileCategories.manager'] = $app->share(
            function ($app) {

                /** @var CategoryMetadataManager $model */
                $model = $app['users.metadataManager.factory']->build('categories');

                return $model;
            }
        );

        $app['users.contentFilter.model'] = $app->share(
            function ($app) {

                return $app['users.metadataManager.factory']->build('content_filter');
            }
        );

        $app['metadata.utilities'] = $app->share(
            function () {
                return new MetadataUtilities();
            }
        );

        $app['users.privacy.model'] = $app->share(
            function ($app) {

                return new PrivacyModel($app['neo4j.graph_manager'], $app['dispatcher'], $app['fields']['privacy'], $app['locale.options']['default']);
            }
        );

        $app['users.profile.tag.model'] = $app->share(
            function ($app) {

                return new ProfileTagModel($app['neo4j.graph_manager'], $app['users.languageText.manager'], $app['metadata.utilities']);
            }
        );

        $app['users.languageText.manager'] = $app->share(
            function ($app) {

                return new LanguageTextManager($app['neo4j.graph_manager']);
            }
        );

        $app['users.answers.model'] = $app->share(
            function ($app) {

                $validator = $app['validator.factory']->build('answers');
                return new AnswerManager($app['neo4j.graph_manager'], $app['questionnaire.questions.model'], $app['users.manager'], $validator, $app['dispatcher']);
            }
        );

        $app['users.questions.model'] = $app->share(
            function ($app) {

                return new UserAnswerPaginatedModel($app['neo4j.graph_manager'], $app['users.answers.model'], $app['questionnaire.questions.model']);
            }
        );

        $app['users.questionCorrelation.manager'] = $app->share(
            function ($app) {
                return new QuestionCorrelationManager($app['neo4j.graph_manager']);
            }
        );

        $app['old.users.questions.compare.model'] = $app->share(
            function ($app) {

                return new OldQuestionComparePaginatedModel($app['neo4j.client']);
            }
        );

        $app['users.questions.compare.model'] = $app->share(
            function ($app) {

                return new QuestionComparePaginatedModel($app['neo4j.graph_manager'], $app['users.answers.model'], $app['questionnaire.questions.model']);
            }
        );

        $app['users.content.model'] = $app->share(
            function ($app) {
                $validator = $app['validator.factory']->build('content_filter');
                return new ContentPaginatedModel($app['neo4j.graph_manager'], $app['links.model'], $validator);
            }
        );

        $app['users.content.compare.model'] = $app->share(
            function ($app) {
                $validator = $app['validator.factory']->build('content_filter');
                return new ContentComparePaginatedModel($app['neo4j.graph_manager'], $app['links.model'], $validator);
            }
        );

        $app['users.content.tag.model'] = $app->share(
            function ($app) {

                return new ContentTagModel($app['neo4j.graph_manager']);
            }
        );

        $app['users.rate.model'] = $app->share(
            function ($app) {

                return new RateModel($app['dispatcher'], $app['neo4j.graph_manager']);
            }
        );

        $app['users.content.report.model'] = $app->share(
            function ($app) {
                $validator = $app['validator.factory']->build('content_filter');
                return new ContentReportModel($app['neo4j.graph_manager'], $app['links.model'], $validator);
            }
        );

        $app['users.matching.model'] = $app->share(
            function ($app) {

                return new MatchingModel($app['dispatcher'], $app['neo4j.graph_manager']);

            }
        );
        $app['users.similarity.model'] = $app->share(
            function ($app) {

                return new SimilarityModel($app['dispatcher'], $app['neo4j.graph_manager'], $app['popularity.manager'], $app['users.questions.model'], $app['users.content.model'], $app['users.profile.model'], $app['users.groups.model']);
            }
        );

        $app['links.popularity.paginated.model'] = $app->share(
            function ($app) {

                return new PopularityPaginatedModel($app['neo4j.graph_manager'], $app['popularity.manager']);
            }
        );

        $app['users.paginated.model'] = $app->share(
            function($app) {
                return new UserPaginatedModel($app['neo4j.graph_manager'], $app['users.manager']);
            }
        );

        $app['users.recommendation.users.model'] = $app->share(
            function ($app) {

                return new UserRecommendationPaginatedModel($app['neo4j.graph_manager'], $app['metadata.utilities'], $app['users.userFilterMetadata.manager'], $app['users.photo.manager'], $app['users.profile.model'], $app['users.languageText.manager']);
            }
        );

        $app['users.recommendation.popularusers.model'] = $app->share(
            function ($app) {

                return new UserPopularRecommendationPaginatedModel($app['neo4j.graph_manager'], $app['metadata.utilities'], $app['users.userFilterMetadata.manager'], $app['users.photo.manager'], $app['users.profile.model'], $app['users.languageText.manager']);
            }
        );

        $app['users.affinity.model'] = $app->share(
            function ($app) {

                return new AffinityModel($app['neo4j.graph_manager']);
            }
        );

        $app['users.recommendation.content.model'] = $app->share(
            function ($app) {
                $validator = $app['validator.factory']->build('content_filter');
                return new ContentRecommendationPaginatedModel($app['neo4j.graph_manager'], $app['users.affinity.model'], $app['links.model'], $validator, $app['imageTransformations.service']);
            }
        );

        $app['users.recommendation.popularcontent.model'] = $app->share(
            function ($app) {
                $validator = $app['validator.factory']->build('content_filter');

                return new ContentPopularRecommendationPaginatedModel($app['neo4j.graph_manager'], $app['links.model'], $validator, $app['imageTransformations.service']);
            }
        );

        $app['users.group.content.model'] = $app->share(
            function ($app) {

                return new GroupContentPaginatedModel($app['neo4j.graph_manager'], $app['links.model'], $app['validator.service'], $app['imageTransformations.service']);
            }
        );

        $app['users.recommendation.content.tag.model'] = $app->share(
            function ($app) {

                return new ContentRecommendationTagModel($app['neo4j.graph_manager']);
            }
        );

        $app['users.ghostuser.manager'] = $app->share(
            function ($app) {

                return new GhostUserManager($app['neo4j.graph_manager'], $app['users.manager']);
            }
        );

        $app['users.socialprofile.manager'] = $app->share(
            function ($app) {

                return new SocialProfileManager($app['neo4j.graph_manager'], $app['users.tokens.model'], $app['users.lookup.model']);
            }
        );

        $app['users.stats.manager'] = $app->share(
            function ($app) {

                return new UserStatsCalculator($app['neo4j.graph_manager'], $app['api_consumer.link_processor.image_analyzer']);
            }
        );

        $app['users.shares.manager'] = $app->share(
            function ($app) {
                return new SharesManager($app['neo4j.graph_manager']);
            }
        );

        $app['users.device.model'] = $app->share(
            function ($app) {
                $validator = $app['validator.factory']->build('device');
                return new DeviceModel($app['neo4j.graph_manager'], $app['push_private_key'], $validator);
            }
        );

        $app['users.lookup.model'] = $app->share(
            function ($app) {

                return new LookUpModel($app['neo4j.graph_manager'], $app['orm.ems']['mysql_brain'], $app['users.tokens.model'], $app['lookUp.fullContact.service'], $app['lookUp.peopleGraph.service'], $app['dispatcher']);
            }
        );

        $app['users.socialNetwork.linkedin.model'] = $app->share(
            function ($app) {

                return new LinkedinSocialNetworkModel($app['neo4j.graph_manager'], $app['parser.linkedin']);
            }
        );

        $app['questionnaire.questions.model'] = $app->share(
            function ($app) {
                $validator = $app['validator.factory']->build('questions');
                return new QuestionModel($app['neo4j.graph_manager'], $validator);
            }
        );

        $app['questionnaire.admin.questions.model'] = $app->share(
            function ($app) {
                $validator = $app['validator.factory']->build('questions_admin');
                return new QuestionAdminManager($app['neo4j.graph_manager'], $app['questionnaire.questions.admin.builder'], $validator);
            }
        );

        $app['questionnaire.questions.admin.builder'] = $app->share(
            function () {
                return new QuestionAdminBuilder();
            }
        );

        $app['admin.questions.paginated.model'] = $app->share(
            function ($app) {
                $validator = $app['validator.factory']->build('questions_admin');
                return new QuestionsAdminPaginatedModel($app['neo4j.graph_manager'], $app['questionnaire.questions.admin.builder'], $validator);
            }
        );

        $app['questionnaire.questions.next.selector'] = $app->share(
            function ($app) {
                return new QuestionNextSelector($app['neo4j.graph_manager']);
            }
        );

        $app['questionnaire.questions.category.manager'] = $app->share(
            function ($app) {
                return new QuestionCategoryManager($app['neo4j.graph_manager']);
            }
        );

        $app['links.model'] = $app->share(
            function ($app) {

                return new LinkModel($app['neo4j.graph_manager']);
            }
        );

        $app['popularity.manager'] = $app->share(
            function ($app) {
                return new PopularityManager($app['neo4j.graph_manager']);
            }
        );

        $app['users.filterusers.manager'] = $app->share(
            function ($app) {

                $validator = new FilterUsersValidator($app['neo4j.graph_manager'], $app['metadata.service'], $app['fields']);
                return new FilterUsersManager($app['neo4j.graph_manager'], $app['users.userFilterMetadata.manager'], $app['users.profileOption.manager'], $app['metadata.utilities'], $validator);
            }
        );

        $app['users.filtercontent.manager'] = $app->share(
            function ($app) {

                $validator = $app['validator.factory']->build('content_filter');
                return new FilterContentManager($app['neo4j.graph_manager'], $validator);
            }
        );

        $app['users.groups.model'] = $app->share(
            function ($app) {
                return new GroupModel($app['neo4j.graph_manager'], $app['dispatcher'], $app['users.photo.manager'],$app['admin_domain_plus_post']);
            }
        );

        $app['users.threads.manager'] = $app->share(
            function ($app) {
                $validator = $app['validator.factory']->build('threads');

                return new ThreadManager(
                    $app['neo4j.graph_manager'], $validator
                );
            }
        );

        $app['users.threadData.manager'] = $app->share(
            function ($app) {
                return new ThreadDataManager($app['users.profile.model'], $app['translator']);
            }
        );

        $app['users.threadCached.manager'] = $app->share(
            function ($app) {
                return new ThreadCachedManager($app['neo4j.graph_manager'], $app['users.recommendation.users.model'], $app['users.recommendation.content.model'], $app['links.model']);
            }
        );

        $app['users.threads.paginated.model'] = $app->share(
            function ($app) {

                return new ThreadPaginatedModel($app['neo4j.graph_manager']);
            }
        );

        $app['users.invitations.model'] = $app->share(
            function ($app) {
                $invitationValidator = $app['validator.factory']->build('invitations');

                return new InvitationModel($app['tokenGenerator.service'], $app['neo4j.graph_manager'], $invitationValidator, $app['admin_domain_plus_post']);
            }
        );

        $app['users.relations.model'] = $app->share(
            function ($app) {

                return new RelationsModel($app['neo4j.graph_manager'], $app['dispatcher']);
            }
        );

        $app['users.relations.paginated.model'] = $app->share(
            function ($app) {

                return new RelationsPaginatedModel($app['neo4j.graph_manager'], $app['dispatcher']);
            }
        );

        $app['users.disabled.paginated.model'] = $app->share(
            function ($app) {

                return new UserDisabledPaginatedModel($app['neo4j.graph_manager'], $app['users.manager']);
            }
        );

        $app['users.contact.model'] = $app->share(
            function ($app) {

                return new ContactModel($app['neo4j.graph_manager'], $app['dbs']['mysql_brain'], $app['users.manager'], $app['users.relations.model']);
            }
        );

        $app['enterpriseUsers.model'] = $app->share(
            function ($app) {

                return new EnterpriseUserModel($app['neo4j.graph_manager']);
            }
        );

        $app['enterpriseUsers.communities.model'] = $app->share(
            function ($app) {

                return new CommunityModel($app['neo4j.graph_manager'], $app['users.manager'], $app['users.photo.manager']);
            }
        );

        $app['users.photo.manager'] = $app->share(
            function ($app) {

                return new PhotoManager($app['neo4j.graph_manager'], $app['users.gallery.manager'], $app['images_web_dir'], $app['params']['social.host']);
            }
        );

        $app['users.gallery.manager'] = $app->share(
            function($app) {
                return new GalleryManager($app['images_web_dir']);
            }
        );

        $app['users.tracking.model'] = $app->share(
            function ($app) {

                return new UserTrackingModel($app['neo4j.graph_manager'], $app['orm.ems']['mysql_brain'], $app['dispatcher']);
            }
        );
    }

    /**
     * { @inheritdoc }
     */
    public function boot(Application $app)
    {

    }

}
