<?php

use League\Container\Container;
use League\Container\ReflectionContainer;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Slim\Middleware\Session;
use Slim\Views\Twig;
use SlimSession\Helper;
use Teamleader\Zoomroulette\Slack\OauthProvider as SlackOauthProvider;
use Teamleader\Zoomroulette\Slack\SlackOauthStorage;
use Teamleader\Zoomroulette\Zoom\OauthProvider as ZoomOauthProviderAlias;
use Teamleader\Zoomroulette\Zoom\ZoomOauthStorage;
use Teamleader\Zoomroulette\Zoomroulette\AuthenticationMiddleware;
use Teamleader\Zoomroulette\Zoomroulette\SessionMiddleware;

require_once __DIR__ . '/../vendor/autoload.php';

$container = new Container();

$container->share('settings', fn () => [
    'displayErrorDetails' => getenv('DISPLAY_ERROR_DETAILS') !== 'true',
]);

$container->share(SessionMiddleware::class, fn () => new SessionMiddleware([
    'name' => 'zoomroulette',
    'autorefresh' => true,
    'lifetime' => '20 minutes',
]));
// register the reflection container as a delegate to enable auto wiring
$container->delegate(new ReflectionContainer());

$container->share(ZoomOauthProviderAlias::class, fn () => new ZoomOauthProviderAlias([
    'clientId' => getenv('ZOOM_CLIENTID'),
    'clientSecret' => getenv('ZOOM_CLIENTSECRET'),
    'redirectUri' => getenv('ROOT_URL') . '/auth/zoom',
    'urlAuthorize' => 'https://zoom.us/oauth/authorize',
    'urlAccessToken' => 'https://zoom.us/oauth/token',
    'urlResourceOwnerDetails' => 'https://api.zoom.us/v2/users/me',
]));

$container->share(Helper::class, fn () => new Helper());

$container->share(SlackOauthProvider::class, function () use ($container) {
    return new SlackOauthProvider([
        'clientId' => getenv('SLACK_CLIENTID'),
        'clientSecret' => getenv('SLACK_CLIENTSECRET'),
        'redirectUri' => getenv('ROOT_URL') . '/auth/slack',
        'urlAuthorize' => 'https://slack.com/oauth/v2/authorize',
        'urlAccessToken' => 'https://slack.com/api/oauth.v2.access',
        'urlResourceOwnerDetails' => 'https://slack.com/api/users.identity',
    ]);
});

// Set view in Container
$container->share(Twig::class, fn () => Twig::create(
    __DIR__ . '/../templates',
    [
        'cache' => __DIR__ . '/../templates/cache',
    ]
));

$container->share(LoggerInterface::class, function () {
    $log = new Logger('zoomroulette');
    $log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

    return $log;
});

$container->share(ZoomOauthStorage::class, fn () => new ZoomOauthStorage(__DIR__ . '/../storage/'));

$container->share(SlackOauthStorage::class, fn () => new SlackOauthStorage(__DIR__ . '/../storage/'));
