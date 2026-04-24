<?php

namespace App\Controller;

use App\CalDAV\AuthBackend;
use App\CalDAV\CalendarBackend;
use App\CalDAV\PrincipalBackend;
use Psr\Log\LoggerInterface;
use Sabre\CalDAV\CalendarRoot;
use Sabre\CalDAV\Plugin as CalDAVPlugin;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAV\Exception as SabreException;
use Sabre\DAV\Server;
use Sabre\DAV\Sync\Plugin as SyncPlugin;
use Sabre\DAVACL\Plugin as ACLPlugin;
use Sabre\DAVACL\PrincipalCollection;
use Sabre\HTTP\Request as SabreRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CalDavController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    #[Route('/.well-known/caldav', name: 'app_caldav_wellknown')]
    public function wellKnown(): Response
    {
        return $this->redirect('/caldav/', 301);
    }

    #[Route(
        '/caldav/{path}',
        name: 'app_caldav',
        requirements: ['path' => '.*'],
        methods: ['GET', 'HEAD', 'OPTIONS', 'POST', 'PUT', 'DELETE', 'PROPFIND', 'PROPPATCH', 'MKCALENDAR', 'REPORT'],
        defaults: ['path' => ''],
    )]
    public function caldav(
        Request $request,
        AuthBackend $authBackend,
        CalendarBackend $calendarBackend,
        PrincipalBackend $principalBackend,
    ): Response {
        $nodes = [
            new PrincipalCollection($principalBackend),
            new CalendarRoot($principalBackend, $calendarBackend),
        ];

        $server = new Server($nodes);
        $server->setBaseUri('/caldav/');

        // Authentication — sabre/dav handles HTTP Basic Auth
        $server->addPlugin(new AuthPlugin($authBackend));

        // CalDAV protocol support
        $server->addPlugin(new CalDAVPlugin());

        // WebDAV sync (RFC 6578) — required by DAVx5 for incremental calendar sync
        $server->addPlugin(new SyncPlugin());

        // ACL — enforces that users only see their own calendars
        $aclPlugin = new ACLPlugin();
        $aclPlugin->allowUnauthenticatedAccess = false;
        $server->addPlugin($aclPlugin);

        // Bridge Symfony request → Sabre request
        $body = fopen('php://temp', 'r+');
        fwrite($body, $request->getContent());
        rewind($body);

        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        // Apache + PHP-FPM strips the Authorization header before PHP sees it.
        // The .htaccess workaround stores it in $_SERVER as REDIRECT_HTTP_AUTHORIZATION.
        if (!isset($headers['authorization'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
            if ($auth) {
                $headers['authorization'] = $auth;
            }
        }

        $sabreRequest = new SabreRequest(
            $request->getMethod(),
            $request->getRequestUri(),  // path only — Sabre expects REQUEST_URI, not the full URL
            $headers,
            $body,
        );
        $sabreRequest->setBaseUrl($server->getBaseUri());

        // Use the server's own httpResponse so that plugins which write directly to
        // $this->server->httpResponse (e.g. SyncPlugin::sendSyncCollectionResponse) also
        // write to the object we read back afterwards.
        $server->httpRequest = $sabreRequest;
        $sabreResponse = $server->httpResponse;

        try {
            $server->invokeMethod($sabreRequest, $sabreResponse, false);
        } catch (SabreException $e) {
            // Sabre's start() handles exceptions; invokeMethod() does not.
            // Convert DAV exceptions (401, 403, 404 …) to proper HTTP responses.
            $sabreResponse->setStatus($e->getHTTPCode());
            foreach ($e->getHTTPHeaders($server) as $name => $value) {
                $sabreResponse->setHeader($name, $value);
            }
            $sabreResponse->setBody($e->getMessage());
        } catch (\Throwable $e) {
            // Log internal failures, but do not leak implementation details to DAV clients.
            $this->logger->error('Unexpected CalDAV error', [
                'exception' => $e,
                'method' => $request->getMethod(),
                'path' => $request->getRequestUri(),
            ]);
            $sabreResponse->setStatus(500);
            $sabreResponse->setBody(Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR]);
        }

        // Bridge Sabre response → Symfony response
        $responseHeaders = [];
        foreach ($sabreResponse->getHeaders() as $name => $values) {
            $responseHeaders[$name] = implode(', ', $values);
        }

        return new Response(
            $sabreResponse->getBodyAsString(),
            $sabreResponse->getStatus(),
            $responseHeaders,
        );
    }
}
