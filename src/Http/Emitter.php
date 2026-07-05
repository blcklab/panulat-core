<?php

declare(strict_types=1);

namespace Panulat\Http;

final class Emitter
{
    public function __construct(private bool $emitContentLength = true)
    {
    }

    public function emit(Response $response): void
    {
        if (! headers_sent()) {
            http_response_code($response->getStatusCode());

            $headers = $response->getHeaders();
            if (! isset($headers['content-type'])) {
                $headers['content-type'] = ['application/json; charset=utf-8'];
            }

            if ($this->emitContentLength && ! $this->shouldSuppressBody($response) && ! isset($headers['content-length'])) {
                $headers['content-length'] = [(string) $response->getBody()->size()];
            }

            foreach ($headers as $name => $values) {
                foreach ($values as $value) {
                    header($name . ': ' . $value, false);
                }
            }
        }

        if (! $this->shouldSuppressBody($response)) {
            echo $response->getBody()->getContents();
        }
    }

    private function shouldSuppressBody(Response $response): bool
    {
        return in_array($response->getStatusCode(), [204, 304], true);
    }
}
