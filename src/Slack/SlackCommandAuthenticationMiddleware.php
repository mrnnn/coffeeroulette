<?php


namespace Teamleader\Zoomroulette\Slack;


use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpUnauthorizedException;
use SlimSession\Helper;

class SlackCommandAuthenticationMiddleware
{

    private string $secret;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(string $secret, LoggerInterface $logger)
    {
        $this->secret = $secret;
        $this->logger = $logger;
    }


    /**
     * Called when middleware needs to be executed.
     *
     * @param Request $request PSR7 request
     * @param RequestHandler $handler PSR7 handler
     *
     * @return Response
     * @throws HttpBadRequestException
     * @throws HttpUnauthorizedException
     */
    public function __invoke(
        Request $request,
        RequestHandler $handler
    ): Response {
        /*if (!isset($request->getParsedBody()['token'])) {
            throw new HttpUnauthorizedException($request, "No token found");
        }*/
        $body = $request->getBody()->getContents();
        if (empty($timestamp = $request->getHeader('X-Slack-Request-Timestamp')) || empty($signature = $request->getHeader('X-Slack-Signature'))) {
            throw New HttpBadRequestException($request, "No timestap or signature header passed");
        }
        if ($timestamp[0] < time() - 120) {
            throw new HttpBadRequestException($request, sprintf('Timestap seems off: %s vs server time of %s', [$timestamp[0], time()]));
        }
        if (!hash_equals(
            'v0=' . hash_hmac('sha256','v0:' . $timestamp[0] . ':' . $body, $this->secret),
            $signature[0]
        )) {
            $this->logger->error("bad signature", [
                'body' => $body,
                'prehash' => 'sha256','v0:' . $timestamp[0] . ':' . $body,

                'headers' => $request->getHeaders(),
                'secret' => $this->secret,
                'signature' => $signature[0],
                'calcualedSignature' => 'v0=' . hash_hmac('sha256','v0:' . $timestamp[0] . ':' . $body, $this->secret),
            ]);
            throw new HttpUnauthorizedException($request, "signature seems invalid");
        }

        return $handler->handle($request->withAttribute('session', new Helper()));
    }

}