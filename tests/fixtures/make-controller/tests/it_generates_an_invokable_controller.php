<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class GeneratedControllerTest extends WebTestCase
{
    public function testControllerValidity()
    {
        $client = self::createClient();
        $client->request('GET', '/foo/invokable');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

    public function testControllerInvokability()
    {
        self::bootKernel();
        $container = static::getContainer();

        $controller = $container->get('App\Controller\FooInvokableController');
        $this->assertIsCallable($controller);

        $request = Request::create('/foo/invokable');
        $container->get('request_stack')->push($request);

        $response = $controller();
        $this->assertInstanceOf(Response::class, $response);
    }
}
