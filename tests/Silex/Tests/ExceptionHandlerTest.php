<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silex\Tests;

use PHPUnit\Framework\TestCase;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Error handler test cases.
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class ExceptionHandlerTest extends TestCase
{
    public function testExceptionHandlerExceptionNoDebug(): void
    {
        $app = new Application();
        $app['debug'] = false;

        $app->match('/foo', function () {
            throw new \RuntimeException('foo exception');
        });

        $request = Request::create('/foo');
        $response = $app->handle($request);
        self::assertStringContainsString('Oops! An Error Occurred', $response->getContent());
        self::assertEquals(500, $response->getStatusCode());
    }

    public function testExceptionHandlerExceptionDebug(): void
    {
        $app = new Application();
        $app['debug'] = true;

        $app->match('/foo', function () {
            throw new \RuntimeException('foo exception');
        });

        $request = Request::create('/foo');
        $response = $app->handle($request);

        self::assertStringContainsString('foo exception', $response->getContent());
        self::assertEquals(500, $response->getStatusCode());
    }

    public function testExceptionHandlerNotFoundNoDebug(): void
    {
        $app = new Application();
        $app['debug'] = false;

        $request = Request::create('/foo');
        $response = $app->handle($request);
        $this->assertStringContainsString('The server returned a "404 Not Found".', $response->getContent());
        self::assertEquals(404, $response->getStatusCode());
    }

    public function testExceptionHandlerNotFoundDebug(): void
    {
        $app = new Application();
        $app['debug'] = true;

        $request = Request::create('/foo');
        $response = $app->handle($request);
        self::assertStringContainsString('No route found for "GET /foo"', html_entity_decode($response->getContent()));
        self::assertEquals(404, $response->getStatusCode());
    }

    public function testExceptionHandlerMethodNotAllowedNoDebug(): void
    {
        $app = new Application();
        $app['debug'] = false;

        $app->get('/foo', function () { return 'foo'; });

        $request = Request::create('/foo', 'POST');
        $response = $app->handle($request);
        self::assertStringContainsString('Oops! An Error Occurred', $response->getContent());
        self::assertEquals(405, $response->getStatusCode());
        self::assertEquals('GET', $response->headers->get('Allow'));
    }

    public function testExceptionHandlerMethodNotAllowedDebug(): void
    {
        $app = new Application();
        $app['debug'] = true;

        $app->get('/foo', function () { return 'foo'; });

        $request = Request::create('/foo', 'POST');
        $response = $app->handle($request);
        self::assertStringContainsString('No route found for "POST /foo": Method Not Allowed (Allow: GET)', html_entity_decode($response->getContent()));
        self::assertEquals(405, $response->getStatusCode());
        self::assertEquals('GET', $response->headers->get('Allow'));
    }

    public function testNoExceptionHandler(): void
    {
        $app = new Application();
        unset($app['exception_handler']);

        $app->match('/foo', function () {
            throw new \RuntimeException('foo exception');
        });

        try {
            $request = Request::create('/foo');
            $app->handle($request);
            self::fail('->handle() should not catch exceptions where no error handler was supplied');
        } catch (\RuntimeException $e) {
            self::assertEquals('foo exception', $e->getMessage());
        }
    }

    public function testOneExceptionHandler(): void
    {
        $app = new Application();

        $app->match('/500', function () {
            throw new \RuntimeException('foo exception');
        });

        $app->match('/404', function () {
            throw new NotFoundHttpException('foo exception');
        });

        $app->get('/405', function () { return 'foo'; });

        $app->error(function ($e, $code) {
            return new Response('foo exception handler');
        });

        $response = $this->checkRouteResponse($app, '/500', 'foo exception handler');
        self::assertEquals(500, $response->getStatusCode());

        $response = $app->handle(Request::create('/404'));
        self::assertEquals(404, $response->getStatusCode());

        $response = $app->handle(Request::create('/405', 'POST'));
        self::assertEquals(405, $response->getStatusCode());
        self::assertEquals('GET', $response->headers->get('Allow'));
    }

    public function testMultipleExceptionHandlers()
    {
        $app = new Application();

        $app->match('/foo', function () {
            throw new \RuntimeException('foo exception');
        });

        $errors = 0;

        $app->error(function ($e) use (&$errors) {
            ++$errors;
        });

        $app->error(function ($e) use (&$errors) {
            ++$errors;
        });

        $app->error(function ($e) use (&$errors) {
            ++$errors;

            return new Response('foo exception handler');
        });

        $app->error(function ($e) use (&$errors) {
            // should not execute
            ++$errors;
        });

        $request = Request::create('/foo');
        $this->checkRouteResponse($app, '/foo', 'foo exception handler', 'should return the first response returned by an exception handler');

        self::assertEquals(3, $errors, 'should execute error handlers until a response is returned');
    }

    public function testNoResponseExceptionHandler()
    {
        $app = new Application();
        unset($app['exception_handler']);

        $app->match('/foo', function () {
            throw new \RuntimeException('foo exception');
        });

        $errors = 0;

        $app->error(function ($e) use (&$errors) {
            ++$errors;
        });

        try {
            $request = Request::create('/foo');
            $app->handle($request);
            $this->fail('->handle() should not catch exceptions where an empty error handler was supplied');
        } catch (\RuntimeException $e) {
            self::assertEquals('foo exception', $e->getMessage());
        } catch (\LogicException $e) {
            self::assertEquals('foo exception', $e->getPrevious()->getMessage());
        }

        self::assertEquals(1, $errors, 'should execute the error handler');
    }

    public function testStringResponseExceptionHandler()
    {
        $app = new Application();

        $app->match('/foo', function () {
            throw new \RuntimeException('foo exception');
        });

        $app->error(function ($e) {
            return 'foo exception handler';
        });

        $request = Request::create('/foo');
        $this->checkRouteResponse($app, '/foo', 'foo exception handler', 'should accept a string response from the error handler');
    }

    public function testExceptionHandlerException()
    {
        $app = new Application();

        $app->match('/foo', function () {
            throw new \RuntimeException('foo exception');
        });

        $app->error(function ($e) {
            throw new \RuntimeException('foo exception handler exception');
        });

        try {
            $request = Request::create('/foo');
            $app->handle($request);
            $this->fail('->handle() should not catch exceptions thrown from an error handler');
        } catch (\RuntimeException $e) {
            self::assertEquals('foo exception handler exception', $e->getMessage());
        }
    }

    public function testRemoveExceptionHandlerAfterDispatcherAccess()
    {
        $app = new Application();

        $app->match('/foo', function () {
            throw new \RuntimeException('foo exception');
        });

        $app->before(function () {
            // just making sure the dispatcher gets created
        });

        unset($app['exception_handler']);

        try {
            $request = Request::create('/foo');
            $app->handle($request);
            $this->fail('default exception handler should have been removed');
        } catch (\RuntimeException $e) {
            self::assertEquals('foo exception', $e->getMessage());
        }
    }

    public function testExceptionHandlerWithDefaultException()
    {
        $app = new Application();
        $app['debug'] = false;

        $app->match('/foo', function () {
            throw new \Exception();
        });

        $app->error(function (\Exception $e) {
            return new Response('Exception thrown', 500);
        });

        $request = Request::create('/foo');
        $response = $app->handle($request);
        self::assertStringContainsString('Exception thrown', $response->getContent());
        self::assertEquals(500, $response->getStatusCode());
    }

    public function testExceptionHandlerWithStandardException()
    {
        $app = new Application();
        $app['debug'] = false;

        $app->match('/foo', function () {
            // Throw a normal exception
            throw new \Exception();
        });

        // Register 2 error handlers, each with a specified Exception class
        // Since we throw a standard Exception above only
        // the second error handler should fire
        $app->error(function (\LogicException $e) { // Extends \Exception
            return 'Caught LogicException';
        });
        $app->error(function (\Exception $e) {
            return 'Caught Exception';
        });

        $request = Request::create('/foo');
        $response = $app->handle($request);
        self::assertStringContainsString('Caught Exception', $response->getContent());
    }

    public function testExceptionHandlerWithSpecifiedException()
    {
        $app = new Application();
        $app['debug'] = false;

        $app->match('/foo', function () {
            // Throw a specified exception
            throw new \LogicException();
        });

        // Register 2 error handlers, each with a specified Exception class
        // Since we throw a LogicException above
        // the first error handler should fire
        $app->error(function (\LogicException $e) { // Extends \Exception
            return 'Caught LogicException';
        });
        $app->error(function (\Exception $e) {
            return 'Caught Exception';
        });

        $request = Request::create('/foo');
        $response = $app->handle($request);
        self::assertStringContainsString('Caught LogicException', $response->getContent());
    }

    public function testExceptionHandlerWithSpecifiedExceptionInReverseOrder()
    {
        $app = new Application();
        $app['debug'] = false;

        $app->match('/foo', function () {
            // Throw a specified exception
            throw new \LogicException();
        });

        // Register the \Exception error handler first, since the
        // error handler works with an instanceof mechanism the
        // second more specific error handler should not fire since
        // the \Exception error handler is registered first and also
        // captures all exceptions that extend it
        $app->error(function (\Exception $e) {
            return 'Caught Exception';
        });
        $app->error(function (\LogicException $e) { // Extends \Exception
            return 'Caught LogicException';
        });

        $request = Request::create('/foo');
        $response = $app->handle($request);
        self::assertStringContainsString('Caught Exception', $response->getContent());
    }

    public function testExceptionHandlerWithArrayStyleCallback()
    {
        $app = new Application();
        $app['debug'] = false;

        $app->match('/foo', function () {
            throw new \Exception();
        });

        // Array style callback for error handler
        $app->error([$this, 'exceptionHandler']);

        $request = Request::create('/foo');
        $response = $app->handle($request);
        self::assertStringContainsString('Caught Exception', $response->getContent());
    }

    protected function checkRouteResponse($app, $path, $expectedContent, $method = 'get', $message = '')
    {
        $request = Request::create($path, $method);
        $response = $app->handle($request);
        self::assertEquals($expectedContent, $response->getContent(), $message);

        return $response;
    }

    public function exceptionHandler()
    {
        return 'Caught Exception';
    }
}
