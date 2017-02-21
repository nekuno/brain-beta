<?php
namespace ApiConsumer\EventListener;

use ApiConsumer\Event\OAuthTokenEvent;
use Model\User\Token\TokensModel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Psr\Log\LoggerInterface;
use Swift_Mailer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class OAuthTokenSubscriber implements EventSubscriberInterface
{

    /**
     * @var AMQPStreamConnection
     */
    protected $amqp;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Swift_Mailer
     */
    protected $mailer;

    /**
     * @var TokensModel
     */
    protected $tm;

    /**
     * @param TokensModel $tm
     * @param Swift_Mailer $mailer
     * @param LoggerInterface $logger
     * @param AMQPStreamConnection $amqp
     */
    public function __construct(TokensModel $tm, Swift_Mailer $mailer, LoggerInterface $logger, AMQPStreamConnection $amqp)
    {
        $this->tm = $tm;
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->amqp = $amqp;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            \AppEvents::TOKEN_EXPIRED => array('onTokenExpired', 0),
            \AppEvents::TOKEN_REFRESHED => array('onTokenRefreshed', 0),
        );
    }

    /**
     * @param OAuthTokenEvent $event
     * @throws \Exception
     */
    public function onTokenExpired(OAuthTokenEvent $event)
    {
    }

    /**
     * @param OAuthTokenEvent $event
     */
    public function onTokenRefreshed(OAuthTokenEvent $event)
    {
        $token = $event->getToken();

        $this->tm->update(
            $token->getUserId(),
            $token->getResourceOwner(),
            array(
                'oauthToken' => $token->getOauthToken(),
                'expireTime' => $token->getExpireTime(),
                'refreshToken' => $token->getRefreshToken(),
            )
        );
    }

    /**
     * @param array $user
     * @return int
     */
    protected function sendMail(array $user)
    {
//TODO: When this is used, pick username and email from user, resourceOwner from token
        $loginUrl = 'http://qnoow.dev.com/app_dev.php/connect/' . $user['resourceOwner'];

        $message = \Swift_Message::newInstance('Action required');
        $body = "
            <!DOCTYPE html>
            <html>
            <head>
            </head>
            <body>
                <h2>Hello! {$user['username']}</h2>
                <p>We need that you grant access to your {$user['resourceOwner']} account again. Please click the link below.</p>
                <a href='{$loginUrl}'>Grant access</a>
            </body>
            </html>";

        $message->setFrom('noreply@qnoow.com');
        $message->setTo(array($user['email']));
        $message->setBody($body, 'text/html');

        return $this->mailer->send($message);
    }
}
