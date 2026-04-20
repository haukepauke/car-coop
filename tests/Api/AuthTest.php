<?php

namespace App\Tests\Api;

class AuthTest extends ApiTestCase
{
    public function testLoginReturnsAccessAndRefreshTokens(): void
    {
        $client = static::createClient();

        $response = $client->request('POST', '/api/login', [
            'json' => [
                'email'    => static::testEmail(),
                'password' => static::testPassword(),
                'device_name' => 'Pixel 9',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
        $this->assertSame('Bearer', $data['token_type']);
        $this->assertSame(3600, $data['expires_in']);
        $this->assertNotEmpty($data['token']);
        $this->assertNotEmpty($data['refresh_token']);
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

    public function testRefreshReturnsNewAccessAndRefreshTokens(): void
    {
        $login = static::login(static::testEmail(), static::testPassword(), 'Pixel 9');

        $response = static::createClient()->request('POST', '/api/token/refresh', [
            'json' => ['refresh_token' => $login['refresh_token']],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
        $this->assertNotSame($login['refresh_token'], $data['refresh_token']);

        static::authClient($data['token'])->request('GET', '/api/cars');
        $this->assertResponseIsSuccessful();
    }

    public function testRefreshRejectsInvalidToken(): void
    {
        static::createClient()->request('POST', '/api/token/refresh', [
            'json' => ['refresh_token' => 'not-a-real-token'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRefreshRejectsReusedRotatedToken(): void
    {
        $login = static::login(static::testEmail(), static::testPassword(), 'Pixel 9');

        static::refresh($login['refresh_token']);

        static::createClient()->request('POST', '/api/token/refresh', [
            'json' => ['refresh_token' => $login['refresh_token']],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLogoutRevokesRefreshToken(): void
    {
        $login = static::login(static::testEmail(), static::testPassword(), 'Pixel 9');

        static::authClient($login['token'])->request('POST', '/api/logout', [
            'json' => ['refresh_token' => $login['refresh_token']],
        ]);

        $this->assertResponseStatusCodeSame(204);

        static::createClient()->request('POST', '/api/token/refresh', [
            'json' => ['refresh_token' => $login['refresh_token']],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }
}
