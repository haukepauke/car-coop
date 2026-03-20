<?php

namespace App\Tests\Api;

class AuthTest extends ApiTestCase
{
    public function testLoginReturnsToken(): void
    {
        $client = static::createClient();

        $response = $client->request('POST', '/api/login', [
            'json' => [
                'email'    => static::testEmail(),
                'password' => static::testPassword(),
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        static::createClient()->request('POST', '/api/login', [
            'json' => [
                'email'    => static::testEmail(),
                'password' => 'WrongPassword!',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginWithUnknownEmailReturns401(): void
    {
        static::createClient()->request('POST', '/api/login', [
            'json' => [
                'email'    => 'nobody@nowhere.test',
                'password' => 'Whatever1!',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testProtectedEndpointWithoutTokenReturns401(): void
    {
        static::createClient()->request('GET', '/api/cars');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testProtectedEndpointWithValidTokenReturns200(): void
    {
        static::authClient()->request('GET', '/api/cars');
        $this->assertResponseIsSuccessful();
    }
}
