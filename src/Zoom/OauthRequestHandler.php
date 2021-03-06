<?php

namespace Teamleader\Zoomroulette\Zoom;

use Exception;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Views\Twig;
use SlimSession\Helper;
use Teamleader\Zoomroulette\Zoomroulette\UserNotFoundException;
use Teamleader\Zoomroulette\Zoomroulette\UserRepository;

class OauthRequestHandler
{
    /**
     * @var OauthProvider
     */
    private $oauthProvider;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private UserRepository $userRepository;
    /**
     * @var Twig
     */
    private Twig $templateEngine;

    public function __construct(
        OauthProvider $oauthProvider,
        UserRepository $userRepository,
        LoggerInterface $logger,
        Twig $templateEngine

    ) {
        $this->oauthProvider = $oauthProvider;
        $this->logger = $logger;
        $this->userRepository = $userRepository;
        $this->templateEngine = $templateEngine;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        if (!isset($_GET['code'])) {
            // Fetch the authorization URL from the provider; this returns the
            // urlAuthorize option and generates and applies any necessary parameters
            // (e.g. state).
            $authorizationUrl = $this->oauthProvider->getAuthorizationUrl();

            // Get the state generated for you and store it to the session.
            $_SESSION['oauth2state'] = $this->oauthProvider->getState();

            return $response->withHeader('Location', $authorizationUrl);
        }

        if (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {
            if (isset($_SESSION['oauth2state'])) {
                unset($_SESSION['oauth2state']);
            }

            $response->getBody()->write(
                $this->templateEngine->getEnvironment()->render('zoomauth.html', ['error' => 'Something went wrong, please try again'])
            );

            return $response->withStatus(400, 'Invalid state');
        }

        try {
            // Try to get an access token using the authorization code grant.
            $accessToken = $this->oauthProvider->getAccessToken('authorization_code', [
                'code' => $_GET['code'],
            ]);
            //$owner = $this->oauthProvider->getResourceOwner($accessToken);
            /** @var Helper $session */
            $session = $request->getAttribute('session');
            $user = $this->userRepository->findById($session->get('userid'));
            $user->setZoomUserid(uniqid("zm_"));
            $user->setZoomAccessToken($accessToken);
            $this->userRepository->update($user);

            $response->getBody()->write(
                $this->templateEngine->getEnvironment()->render('goodtogo.html')
            );
            return $response->withStatus(200);
        } catch (UserNotFoundException $e) {

            $response->getBody()->write(
                $this->templateEngine->getEnvironment()->render('slackauth.html', ['error' => 'Please authorize via slack first and then link your zoom account.'])
            );
            return $response->withStatus(400, 'Please autohrize via Slack first');
        } catch (IdentityProviderException $e) {
            $this->logger->error('Failed to get access token or user details', $e->getTrace());

            $response->getBody()->write(
                $this->templateEngine->getEnvironment()->render('zoomauth.html', ['error' => 'Something went wrong, please try again'])
            );
            return $response->withStatus(400);
        } catch (PDOException $e) {
            $this->logger->error('Database unreachable', ['exception' => $e]);

            $response->getBody()->write(
                $this->templateEngine->getEnvironment()->render('zoomauth.html', ['error' => 'Something went wrong, please try again'])
            );
            return $response->withStatus(500);
        } catch (Exception $e) {
            $this->logger->error('Failed to get access token or user details', $e->getTrace());

            $response->getBody()->write(
                $this->templateEngine->getEnvironment()->render('zoomauth.html', ['error' => 'Something went wrong, please try again'])
            );
            return $response->withStatus(500);
        }
    }
}
