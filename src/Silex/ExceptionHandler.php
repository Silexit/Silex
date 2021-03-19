<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silex;

use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Default exception handler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExceptionHandler implements EventSubscriberInterface
{
    protected $debug;

    public function __construct($debug)
    {
        $this->debug = $debug;
    }

    public function onSilexError(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof FlattenException) {
            $exception = FlattenException::createFromThrowable($exception);
        }

        if ($this->debug) {
            Debug::enable();
        }
        ErrorHandler::register();

        $charset = ini_get('default_charset');

        $renderer = new HtmlErrorRenderer($this->debug, $charset);
        $render = $renderer->getBody($exception);

        $response = Response::create($render, $exception->getStatusCode(), $exception->getHeaders())->setCharset($charset);

        $event->setResponse($response);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [KernelEvents::EXCEPTION => ['onSilexError', -255]];
    }
}
