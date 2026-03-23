<?php

namespace App\Controller;

use App\CalDAV\AuthBackend;
use App\CalDAV\CalendarBackend;
use App\CalDAV\PrincipalBackend;
use Sabre\CalDAV\CalendarRoot;
use Sabre\CalDAV\Plugin as CalDAVPlugin;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAV\Server;
use Sabre\DAVACL\Plugin as ACLPlugin;
use Sabre\DAVACL\PrincipalCollection;
use Sabre\HTTP\Request as SabreRequest;
use Sabre\HTTP\Response as SabreResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CalDavController extends AbstractController
{
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

        $sabreResponse = new SabreResponse();

        $server->invokeMethod($sabreRequest, $sabreResponse, false);

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
