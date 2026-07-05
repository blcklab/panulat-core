<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Config\ConfigRepository;
use Panulat\Foundation\ErrorHandler;
use Panulat\Foundation\Exception\NotFoundException;
use Panulat\Support\Translator;
use PHPUnit\Framework\TestCase;

final class LocaleTest extends TestCase
{
    public function testTranslatorReadsFilipinoLinesWithFallback(): void
    {
        $translator = Translator::fromConfig(dirname(__DIR__, 2), new ConfigRepository([
            'locale' => [
                'default' => 'fil',
                'fallback' => 'en',
                'supported' => ['en', 'fil'],
            ],
        ]));

        self::assertSame('Maligayang pagdating sa Panulat.', $translator->get('app.welcome'));
        self::assertSame('fallback', $translator->get('app.missing', default: 'fallback'));
    }

    public function testErrorHandlerLocalizesFrameworkProblemDetails(): void
    {
        $translator = Translator::fromConfig(dirname(__DIR__, 2), new ConfigRepository([
            'locale' => [
                'default' => 'fil',
                'fallback' => 'en',
                'supported' => ['en', 'fil'],
            ],
        ]));

        $response = (new ErrorHandler(debug: false, translator: $translator))->render(new NotFoundException());
        $body = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('Hindi Natagpuan', $body['title']);
        self::assertSame('Walang tugmang ruta o tala ang nahanap.', $body['detail']);
    }
}
