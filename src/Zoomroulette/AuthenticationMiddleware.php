<?php

namespace Teamleader\Zoomroulette\Zoomroulette;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Views\Twig;
use SlimSession\Helper;

class AuthenticationMiddleware
{

    /**
     * @var ResponseFactoryInterface
     */
    private ResponseFactoryInterface $responseFactory;
    /**
     * @var Twig
     */
    private Twig $templateEngine;

    public function __construct(ResponseFactoryInterface  $responseFactory, Twig $templateEngine) {

        $this->responseFactory = $responseFactory;
        $this->templateEngine = $templateEngine;
    }
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Helper $session */
        $session = $request->getAttribute('session');
        if (!$session->exists('userid')) {

            $response = $this->responseFactory->createResponse();

            $response->getBody()->write(
                $this->templateEngine->getEnvironment()->render('slackauth.html', ['error' => 'Please authorize your slack account first and then setup your zoom account.'])
            );
            return $response->withStatus(500);
        }

        return $handler->handle($request);
    }
}
