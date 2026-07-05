<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Foundation\Exception\BadRequestException;
use Panulat\Http\Request;
use Panulat\Http\Response;
use PHPUnit\Framework\TestCase;

final class HttpTest extends TestCase
{
    public function testJsonRequestAndResponse(): void
    {
        $request = Request::fromServer([
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST' => 'example.test',
            'REQUEST_URI' => '/v1/users?active=1',
            'CONTENT_TYPE' => 'application/json',
        ], '{"email":"a@example.test"}');

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/v1/users', $request->getUri()->getPath());
        self::assertSame('1', $request->query('active'));
        self::assertSame('a@example.test', $request->json()['email']);

        $response = Response::json(['data' => ['ok' => true]], 201);
        self::assertSame(201, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('content-type'));

        $text = Response::text('hello', 202);
        self::assertSame(202, $text->getStatusCode());
        self::assertSame('text/plain; charset=utf-8', $text->getHeaderLine('content-type'));
        self::assertSame('hello', $text->getBody()->getContents());

        $empty = Response::noContent();
        self::assertSame(204, $empty->getStatusCode());
        self::assertTrue($empty->getBody()->isEmpty());
    }

    public function testRequestBodyIsReadLazily(): void
    {
        $reads = 0;
        $request = Request::fromServer([
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST' => 'example.test',
            'REQUEST_URI' => '/v1/users',
            'CONTENT_TYPE' => 'application/json',
        ], body: null, bodyFactory: static function () use (&$reads): string {
            $reads++;
            return '{"email":"lazy@example.test"}';
        });

        self::assertSame(0, $reads);
        self::assertSame('POST', $request->getMethod());
        self::assertSame(0, $reads);
        self::assertSame('lazy@example.test', $request->json()['email']);
        self::assertSame(1, $reads);
        self::assertSame('lazy@example.test', $request->json()['email']);
        self::assertSame(1, $reads);
    }

    public function testRequestBodyMaxSizeGuardUsesContentLengthBeforeReading(): void
    {
        $reads = 0;
        $request = Request::fromServer([
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST' => 'example.test',
            'REQUEST_URI' => '/v1/users',
            'CONTENT_LENGTH' => '20',
        ], body: null, maxBodyBytes: 10, bodyFactory: static function () use (&$reads): string {
            $reads++;
            return 'this should not be read';
        });

        $this->expectException(\Panulat\Foundation\Exception\PayloadTooLargeException::class);

        try {
            $request->body();
        } finally {
            self::assertSame(0, $reads);
        }
    }
    public function testClientIpUsesRemoteAddressInsteadOfUntrustedForwardedHeader(): void
    {
        $request = Request::fromServer([
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'example.test',
            'REQUEST_URI' => '/v1/health',
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
        ]);

        self::assertSame('10.0.0.5', $request->getClientIp());
    }

    public function testUrlEncodedBodyIsParsedLazily(): void
    {
        $reads = 0;
        $request = Request::fromServer([
            'REQUEST_METHOD' => 'PUT',
            'HTTP_HOST' => 'example.test',
            'REQUEST_URI' => '/v1/users/1',
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ], body: null, bodyFactory: static function () use (&$reads): string {
            $reads++;
            return 'name=Billy&email=billy%40example.test';
        });

        self::assertSame(0, $reads);
        self::assertSame('Billy', $request->post('name'));
        self::assertSame('billy@example.test', $request->input('email'));
        self::assertSame(1, $reads);
        self::assertSame('Billy', $request->post('name'));
        self::assertSame(1, $reads);
    }

    public function testMalformedJsonRequestBodyReturnsBadRequestException(): void
    {
        $request = Request::fromServer([
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST' => 'example.test',
            'REQUEST_URI' => '/v1/users',
            'CONTENT_TYPE' => 'application/json',
        ], '{bad json');

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Malformed JSON request body.');

        $request->json();
    }

    public function testParsedFormBodyAndUploadedFileAreAvailable(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'panulat_upload_');
        self::assertIsString($tmp);
        file_put_contents($tmp, 'avatar contents');

        try {
            $request = Request::fromServer([
                'REQUEST_METHOD' => 'POST',
                'HTTP_HOST' => 'example.test',
                'REQUEST_URI' => '/v1/uploads?source=test',
                'CONTENT_TYPE' => 'multipart/form-data; boundary=example',
            ], body: '', parsedBody: [
                'name' => 'Billy',
            ], files: [
                'avatar' => [
                    'name' => 'avatar.png',
                    'type' => 'image/png',
                    'tmp_name' => $tmp,
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($tmp),
                ],
            ]);

            self::assertSame('Billy', $request->post('name'));
            self::assertSame('Billy', $request->input('name'));
            self::assertSame('test', $request->input('source'));
            self::assertTrue($request->hasFile('avatar'));

            $file = $request->file('avatar');
            self::assertNotNull($file);
            self::assertSame('avatar.png', $file->clientFilename());
            self::assertSame('png', $file->extension());
            self::assertStringEndsWith('.png', $file->safeName());
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }

    public function testMultipleUploadedFilesCanBeListed(): void
    {
        $first = tempnam(sys_get_temp_dir(), 'panulat_upload_');
        $second = tempnam(sys_get_temp_dir(), 'panulat_upload_');
        self::assertIsString($first);
        self::assertIsString($second);
        file_put_contents($first, 'one');
        file_put_contents($second, 'two');

        try {
            $request = Request::fromServer([
                'REQUEST_METHOD' => 'POST',
                'HTTP_HOST' => 'example.test',
                'REQUEST_URI' => '/v1/uploads',
                'CONTENT_TYPE' => 'multipart/form-data; boundary=example',
            ], body: '', files: [
                'photos' => [
                    'name' => ['one.jpg', 'two.jpg'],
                    'type' => ['image/jpeg', 'image/jpeg'],
                    'tmp_name' => [$first, $second],
                    'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                    'size' => [filesize($first), filesize($second)],
                ],
            ]);

            self::assertCount(2, $request->fileList('photos'));
            self::assertSame('one.jpg', $request->file('photos')?->clientFilename());
        } finally {
            foreach ([$first, $second] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}
