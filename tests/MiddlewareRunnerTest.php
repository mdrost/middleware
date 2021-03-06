<?php declare(strict_types=1);

namespace ApiClients\Tests\Foundation\Middleware;

use ApiClients\Foundation\Middleware\MiddlewareInterface;
use ApiClients\Foundation\Middleware\MiddlewareRunner;
use ApiClients\Tools\TestUtilities\TestCase;
use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Phake;
use React\EventLoop\Factory;
use Throwable;
use function Clue\React\Block\await;
use function React\Promise\reject;
use function React\Promise\resolve;

class MiddlewareRunnerTest extends TestCase
{
    public function testAll()
    {
        $loop = Factory::create();
        $request = new Request('GET', 'https://example.com/');
        $response = new Response(200);
        $exception = new Exception();
        $options = [];

        $middlewareOne = Phake::mock(MiddlewareInterface::class);
        Phake::when($middlewareOne)->priority()->thenReturn(1000);
        Phake::when($middlewareOne)->pre($request, $options)->thenReturn(resolve($request));
        Phake::when($middlewareOne)->post($response, $options)->thenReturn(resolve($response));
        Phake::when($middlewareOne)->error($exception, $options)->thenReturn(reject($exception));

        $middlewareTwo = Phake::mock(MiddlewareInterface::class);
        Phake::when($middlewareTwo)->priority()->thenReturn(500);
        Phake::when($middlewareTwo)->pre($request, $options)->thenReturn(resolve($request));
        Phake::when($middlewareTwo)->post($response, $options)->thenReturn(resolve($response));
        Phake::when($middlewareTwo)->error($exception, $options)->thenReturn(reject($exception));

        $middlewareThree = Phake::mock(MiddlewareInterface::class);
        Phake::when($middlewareThree)->priority()->thenReturn(0);
        Phake::when($middlewareThree)->pre($request, $options)->thenReturn(resolve($request));
        Phake::when($middlewareThree)->post($response, $options)->thenReturn(resolve($response));
        Phake::when($middlewareThree)->error($exception, $options)->thenReturn(reject($exception));

        $args = [
            $options,
            $middlewareThree,
            $middlewareOne,
            $middlewareTwo,
        ];

        $executioner = new MiddlewareRunner(...$args);
        self::assertSame($request, await($executioner->pre($request), $loop));
        self::assertSame($response, await($executioner->post($response), $loop));
        try {
            await($executioner->error($exception), $loop);
        } catch (Throwable $throwable) {
            self::assertSame($exception, $throwable);
        }

        Phake::inOrder(
            Phake::verify($middlewareOne)->pre($request, $options),
            Phake::verify($middlewareTwo)->pre($request, $options),
            Phake::verify($middlewareThree)->pre($request, $options),
            Phake::verify($middlewareThree)->post($response, $options),
            Phake::verify($middlewareTwo)->post($response, $options),
            Phake::verify($middlewareOne)->post($response, $options),
            Phake::verify($middlewareOne)->error($exception, $options),
            Phake::verify($middlewareTwo)->error($exception, $options),
            Phake::verify($middlewareThree)->error($exception, $options)
        );
    }
}
