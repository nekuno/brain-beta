<?php

namespace Provider;

use Model\LanguageText\LanguageTextManager;
use Model\Location\LocationManager;
use Model\Photo\GalleryManager;
use Model\Photo\PhotoManager;
use Model\EnterpriseUser\EnterpriseUserManager;
use Model\Link\LinkManager;
use Model\Metadata\CategoryMetadataManager;
use Model\Metadata\MetadataManagerFactory;
use Model\Metadata\MetadataUtilities;
use Model\Popularity\PopularityManager;
use Model\Popularity\PopularityPaginatedManager;
use Model\Contact\ContactManager;
use Model\Content\ContentReportManager;
use Model\Device\DeviceManager;
use Model\Profile\ProfileOptionManager;
use Model\Question\Admin\QuestionAdminBuilder;
use Model\Question\Admin\QuestionAdminManager;
use Model\Question\QuestionCategory\QuestionCategoryManager;
use Model\Question\QuestionCorrelationManager;
use Model\Question\QuestionManager;
use Model\Affinity\AffinityManager;
use Model\Question\AnswerManager;
use Model\EnterpriseUser\CommunityManager;
use Model\Content\ContentComparePaginatedManager;
use Model\Content\ContentPaginatedManager;
use Model\Content\ContentTagManager;
use Model\Filters\FilterContentManager;
use Model\Filters\FilterUsersManager;
use Model\GhostUser\GhostUserManager;
use Model\Group\GroupContentPaginatedManager;
use Model\Group\GroupManager;
use Model\Invitation\InvitationManager;
use Model\LookUp\LookUpManager;
use Model\Matching\MatchingManager;
use Model\Question\OldQuestionComparePaginatedManager;
use Model\Privacy\PrivacyManager;
use Model\Profile\ProfileManager;
use Model\Profile\ProfileTagManager;
use Model\Question\QuestionComparePaginatedManager;
use Model\Question\Admin\QuestionsAdminPaginatedManager;
use Model\Question\QuestionNextSelector;
use Model\Question\UserAnswerPaginatedManager;
use Model\Rate\RateManager;
use Model\Recommendation\ContentPopularRecommendationPaginatedManager;
use Model\Recommendation\ContentRecommendationPaginatedManager;
use Model\Recommendation\ContentRecommendationTagModel;
use Model\Recommendation\UserPopularRecommendationPaginatedManager;
use Model\Recommendation\UserRecommendationPaginatedManager;
use Model\Relations\RelationsManager;
use Model\Relations\RelationsPaginatedManager;
use Model\Shares\SharesManager;
use Model\Similarity\SimilarityManager;
use Model\SocialNetwork\LinkedinSocialNetworkManager;
use Model\SocialNetwork\SocialProfileManager;
use Model\Thread\ThreadCachedManager;
use Model\Thread\ThreadDataManager;
use Model\Thread\ThreadManager;
use Model\Thread\ThreadPaginatedManager;
use Model\Token\TokensManager;
use Model\Token\TokenStatus\TokenStatusManager;
use Model\User\UserDisabledPaginatedManager;
use Model\Stats\UserStatsCalculator;
use Model\User\UserManager;
use Model\User\UserPaginatedManager;
use Model\User\UserTrackingManager;
use Pimple\Container;
use Security\UserProvider;
use Service\Validator\FilterUsersValidator;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;

class ModelsServiceProvider implements ServiceProviderInterface
{

    /**
     * { @inheritdoc }
     */
    public function register(Container $app)
    {

        $app['security.password_encoder'] = function () {

            return new MessageDigestPasswordEncoder();
        };

        $app['security.users_provider'] = $app['users'] = function ($app) {

            return new UserProvider($app['users.manager']);
        };

        $app['users.manager'] = function ($app) {

            return new UserManager($app['dispatcher'], $app['neo4j.graph_manager'], $app['security.password_encoder'], $app['users.photo.manager'], $app['slugify'], $app['images_web_dir']);
        };

        $app['users.tokens.model'] = function ($app) {

            $validator = $app['validator.factory']->build('tokens');
            return new TokensManager($app['dispatcher'], $app['neo4j.graph_manager'], $validator);
        };

        $app['users.tokenStatus.manager'] = function ($app) {

            $validator = $app['validator.factory']->build('tokenStatus');
            return new TokenStatusManager($app['neo4j.graph_manager'], $validator);
        };

        $app['users.location.manager'] = function ($app) {

            return new LocationManager($app['neo4j.graph_manager']);
        };

        $app['users.profile.model'] = function ($app) {

            $profileValidator = $app['validator.factory']->build('profile');
            return new ProfileManager($app['neo4j.graph_manager'], $app['users.profileMetadata.manager'], $app['users.profileOption.manager'], $app['users.profile.tag.model'], $app['users.location.manager'], $app['metadata.utilities'],  $app['dispatcher'], $profileValidator);
        };

        $app['users.profileOption.manager'] = function ($app) {

            return new ProfileOptionManager($app['neo4j.graph_manager'], $app['users.profileMetadata.manager'], $app['metadata.utilities']);
        };

        $app['users.metadataManager.factory'] = function ($app) {

            return new MetadataManagerFactory($app['metadata.config'], $app['translator'], $app['metadata.utilities'], $app['fields'], $app['locale.options']['default']);
        };

        $app['users.userFilterMetadata.manager'] = function ($app) {

            return $app['users.metadataManager.factory']->build('user_filter');
        };

        $app['users.profileMetadata.manager'] = function ($app) {

            return $app['users.metadataManager.factory']->build('profile');
        };

        $app['users.profileCategories.manager'] = function ($app) {

            /** @var CategoryMetadataManager $model */
            $model = $app['users.metadataManager.factory']->build('categories');

            return $model;
        };

        $app['users.contentFilter.model'] = function ($app) {

            return $app['users.metadataManager.factory']->build('content_filter');
        };

        $app['metadata.utilities'] = function () {

            return new MetadataUtilities();
        };

        $app['users.privacy.model'] = function ($app) {

            return new PrivacyManager($app['neo4j.graph_manager'], $app['dispatcher'], $app['fields']['privacy'], $app['locale.options']['default']);
        };

        $app['users.profile.tag.model'] = function ($app) {

            return new ProfileTagManager($app['neo4j.graph_manager'], $app['users.languageText.manager'], $app['metadata.utilities']);
        };

        $app['users.languageText.manager'] = function ($app) {

            return new LanguageTextManager($app['neo4j.graph_manager']);
        };

        $app['users.answers.model'] = function ($app) {

            $validator = $app['validator.factory']->build('answers');
            return new AnswerManager($app['neo4j.graph_manager'], $validator, $app['dispatcher']);
        };

        $app['users.questions.model'] = function ($app) {

            return new UserAnswerPaginatedManager($app['neo4j.graph_manager'], $app['answer.service'], $app['questionnaire.questions.model']);
        };

        $app['users.questionCorrelation.manager'] = function ($app) {

            return new QuestionCorrelationManager($app['neo4j.graph_manager']);
        };

        $app['old.users.questions.compare.model'] = function ($app) {

            return new OldQuestionComparePaginatedManager($app['neo4j.client']);
        };

        $app['users.questions.compare.model'] = function ($app) {

            return new QuestionComparePaginatedManager($app['neo4j.graph_manager'], $app['answer.service'], $app['questionnaire.questions.model']);
        };

        $app['users.content.model'] = function ($app) {

            $validator = $app['validator.factory']->build('content_filter');
            return new ContentPaginatedManager($app['neo4j.graph_manager'], $app['links.model'], $validator);
        };

        $app['users.content.compare.model'] = function ($app) {
            $validator = $app['validator.factory']->build('content_filter');
            return new ContentComparePaginatedManager($app['neo4j.graph_manager'], $app['links.model'], $validator);
        };

        $app['users.content.tag.model'] = function ($app) {

            return new ContentTagManager($app['neo4j.graph_manager']);
        };

        $app['users.rate.model'] = function ($app) {

            return new RateManager($app['dispatcher'], $app['neo4j.graph_manager']);
        };

        $app['users.content.report.model'] = function ($app) {

            $validator = $app['validator.factory']->build('content_filter');
            return new ContentReportManager($app['neo4j.graph_manager'], $app['links.model'], $validator);
        };

        $app['users.matching.model'] = function ($app) {

            return new MatchingManager($app['dispatcher'], $app['neo4j.graph_manager']);
        };

        $app['users.similarity.model'] = function ($app) {

            return new SimilarityManager($app['dispatcher'], $app['neo4j.graph_manager'], $app['users.questions.model'], $app['users.content.model'], $app['users.profile.model'], $app['users.groups.model']);
        };

        $app['links.popularity.paginated.model'] = function ($app) {

            return new PopularityPaginatedManager($app['neo4j.graph_manager'], $app['popularity.manager']);
        };

        $app['users.paginated.model'] = function($app) {

            return new UserPaginatedManager($app['neo4j.graph_manager'], $app['users.manager']);
        };

        $app['users.recommendation.users.model'] = function ($app) {

            return new UserRecommendationPaginatedManager($app['neo4j.graph_manager'], $app['metadata.utilities'], $app['users.userFilterMetadata.manager'], $app['users.photo.manager'], $app['users.profile.model'], $app['users.languageText.manager']);
        };

        $app['users.recommendation.popularusers.model'] = function ($app) {

            return new UserPopularRecommendationPaginatedManager($app['neo4j.graph_manager'], $app['metadata.utilities'], $app['users.userFilterMetadata.manager'], $app['users.photo.manager'], $app['users.profile.model'], $app['users.languageText.manager']);
        };

        $app['users.affinity.model'] = function ($app) {

            return new AffinityManager($app['neo4j.graph_manager'], $app['links.model']);
        };

        $app['users.recommendation.content.model'] = function ($app) {

            $validator = $app['validator.factory']->build('content_filter');
            return new ContentRecommendationPaginatedManager($app['neo4j.graph_manager'], $app['users.affinity.model'], $app['links.model'], $validator, $app['imageTransformations.service']);
        };

        $app['users.recommendation.popularcontent.model'] = function ($app) {

            $validator = $app['validator.factory']->build('content_filter');

            return new ContentPopularRecommendationPaginatedManager($app['neo4j.graph_manager'], $app['links.model'], $validator, $app['imageTransformations.service']);
        };

        $app['users.group.content.model'] = function ($app) {

            return new GroupContentPaginatedManager($app['neo4j.graph_manager'], $app['links.model'], $app['validator.service'], $app['imageTransformations.service']);
        };

        $app['users.recommendation.content.tag.model'] = function ($app) {

            return new ContentRecommendationTagModel($app['neo4j.graph_manager']);
        };

        $app['users.ghostuser.manager'] = function ($app) {

            return new GhostUserManager($app['neo4j.graph_manager'], $app['users.manager']);
        };

        $app['users.socialprofile.manager'] = function ($app) {

            return new SocialProfileManager($app['neo4j.graph_manager'], $app['users.tokens.model'], $app['users.lookup.model']);
        };

        $app['users.stats.manager'] = function ($app) {

            return new UserStatsCalculator($app['neo4j.graph_manager'], $app['api_consumer.link_processor.image_analyzer']);
        };

        $app['users.shares.manager'] = function ($app) {
            return new SharesManager($app['neo4j.graph_manager']);
        };

        $app['users.device.model'] = function ($app) {

            $validator = $app['validator.factory']->build('device');
            return new DeviceManager($app['neo4j.graph_manager'], $app['push_private_key'], $validator);
        };

        $app['users.lookup.model'] = function ($app) {

            return new LookUpManager($app['neo4j.graph_manager'], $app['orm.ems']['mysql_brain'], $app['users.tokens.model'], $app['lookUp.fullContact.service'], $app['lookUp.peopleGraph.service'], $app['dispatcher']);
        };

        $app['users.socialNetwork.linkedin.model'] = function ($app) {

            return new LinkedinSocialNetworkManager($app['neo4j.graph_manager'], $app['parser.linkedin']);
        };

        $app['questionnaire.questions.model'] = function ($app) {

            $validator = $app['validator.factory']->build('questions');
            return new QuestionManager($app['neo4j.graph_manager'], $validator);
        };

        $app['questionnaire.admin.questions.model'] = function ($app) {

            $validator = $app['validator.factory']->build('questions_admin');
            return new QuestionAdminManager($app['neo4j.graph_manager'], $app['questionnaire.questions.admin.builder'], $validator);
        };

        $app['questionnaire.questions.admin.builder'] = function () {

            return new QuestionAdminBuilder();
        };

        $app['admin.questions.paginated.model'] = function ($app) {

            $validator = $app['validator.factory']->build('questions_admin');
            return new QuestionsAdminPaginatedManager($app['neo4j.graph_manager'], $app['questionnaire.questions.admin.builder'], $validator);
        };

        $app['questionnaire.questions.next.selector'] = function ($app) {

            return new QuestionNextSelector($app['neo4j.graph_manager']);
        };

        $app['questionnaire.questions.category.manager'] = function ($app) {
            return new QuestionCategoryManager($app['neo4j.graph_manager']);
        };

        $app['links.model'] = function ($app) {

            return new LinkManager($app['neo4j.graph_manager']);
        };

        $app['popularity.manager'] = function ($app) {

            return new PopularityManager($app['neo4j.graph_manager']);
        };

        $app['users.filterusers.manager'] = function ($app) {

            $validator = new FilterUsersValidator($app['neo4j.graph_manager'], $app['metadata.service'], $app['fields']);
            return new FilterUsersManager($app['neo4j.graph_manager'], $app['users.userFilterMetadata.manager'], $app['users.profileOption.manager'], $app['users.location.manager'],  $app['metadata.utilities'], $validator);
        };

        $app['users.filtercontent.manager'] = function ($app) {

            $validator = $app['validator.factory']->build('content_filter');
            return new FilterContentManager($app['neo4j.graph_manager'], $validator);
        };

        $app['users.groups.model'] = function ($app) {

            return new GroupManager($app['neo4j.graph_manager'], $app['dispatcher'], $app['users.photo.manager'],$app['admin_domain_plus_post']);
        };

        $app['users.threads.manager'] = function ($app) {

            $validator = $app['validator.factory']->build('threads');

            return new ThreadManager(
                $app['neo4j.graph_manager'], $validator
            );
        };

        $app['users.threadData.manager'] = function ($app) {

            return new ThreadDataManager($app['users.profile.model'], $app['translator']);
        };

        $app['users.threadCached.manager'] = function ($app) {

            return new ThreadCachedManager($app['neo4j.graph_manager'], $app['users.recommendation.users.model'], $app['users.recommendation.content.model'], $app['links.model']);
        };

        $app['users.threads.paginated.model'] = function ($app) {

            return new ThreadPaginatedManager($app['neo4j.graph_manager']);
        };

        $app['users.invitations.model'] = function ($app) {

            $invitationValidator = $app['validator.factory']->build('invitations');

            return new InvitationManager($app['tokenGenerator.service'], $app['neo4j.graph_manager'], $invitationValidator, $app['admin_domain_plus_post']);
        };

        $app['users.relations.model'] = function ($app) {

            return new RelationsManager($app['neo4j.graph_manager'], $app['dispatcher']);
        };

        $app['users.relations.paginated.model'] = function ($app) {

            return new RelationsPaginatedManager($app['neo4j.graph_manager'], $app['dispatcher']);
        };

        $app['users.disabled.paginated.model'] =  function ($app) {

            return new UserDisabledPaginatedManager($app['neo4j.graph_manager'], $app['users.manager']);
        };

        $app['users.contact.model'] = function ($app) {

            return new ContactManager($app['neo4j.graph_manager'], $app['dbs']['mysql_brain'], $app['users.manager'], $app['users.relations.model']);
        };

        $app['enterpriseUsers.model'] = function ($app) {

            return new EnterpriseUserManager($app['neo4j.graph_manager']);
        };

        $app['enterpriseUsers.communities.model'] = function ($app) {

            return new CommunityManager($app['neo4j.graph_manager'], $app['users.manager'], $app['users.photo.manager']);
        };

        $app['users.photo.manager'] = function ($app) {

            return new PhotoManager($app['neo4j.graph_manager'], $app['users.gallery.manager'], $app['images_web_dir'], $app['params']['social.host']);
        };

        $app['users.gallery.manager'] = function($app) {

            return new GalleryManager($app['images_web_dir']);
        };

        $app['users.tracking.model'] = function ($app) {

            return new UserTrackingManager($app['neo4j.graph_manager'], $app['orm.ems']['mysql_brain'], $app['dispatcher']);
        };
    }

}
