<?php

declare(strict_types=1);

namespace Panulat\Tests\Unit;

use Panulat\Foundation\Exception\ValidationException;
use Panulat\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    public function testValidationFailure(): void
    {
        $this->expectException(ValidationException::class);

        Validator::make(['email' => 'bad'], ['email' => 'required|email'])->validate();
    }

    public function testValidationReturnsOnlyValidatedFields(): void
    {
        $validated = Validator::make([
            'name' => 'Avelino',
            'email' => 'avelino@example.test',
            'admin' => true,
        ], [
            'name' => 'required|string|min:2',
            'email' => 'required|email',
        ])->validate();

        self::assertSame([
            'name' => 'Avelino',
            'email' => 'avelino@example.test',
        ], $validated);
    }

    public function testAdditionalRules(): void
    {
        $validator = Validator::make([
            'age' => 18,
            'active' => true,
            'roles' => ['admin'],
            'status' => 'published',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ], [
            'age' => 'required|integer|min:18',
            'active' => 'required|boolean',
            'roles' => 'required|array|min:1',
            'status' => 'required|in:draft,published',
            'password' => 'required|string|min:8|confirmed',
        ]);

        self::assertTrue($validator->passes());
    }

    public function testNullableAndSometimesSkipOptionalValues(): void
    {
        $validator = Validator::make([
            'bio' => null,
        ], [
            'bio' => 'nullable|string|max:120',
            'nickname' => 'sometimes|string|max:40',
        ]);

        self::assertSame([], $validator->errors());
    }

    public function testCustomMessages(): void
    {
        $errors = Validator::make([], [
            'name' => 'required',
        ], [
            'name.required' => 'Pangalan ay kailangan.',
        ])->errors();

        self::assertSame(['Pangalan ay kailangan.'], $errors['name']);
    }
}
