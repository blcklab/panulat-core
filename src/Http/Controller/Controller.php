<?php

declare(strict_types=1);

namespace Panulat\Http\Controller;

use Panulat\Http\Request;
use Panulat\Http\Response;
use Panulat\Validation\Validator;

abstract readonly class Controller
{
    protected function json(mixed $payload, int $status = 200): Response
    {
        return Response::json($payload, $status);
    }

    protected function data(mixed $data, int $status = 200): Response
    {
        return Response::json(['data' => $data], $status);
    }

    protected function created(mixed $data): Response
    {
        return $this->data($data, 201);
    }

    protected function deleted(): Response
    {
        return Response::json(['data' => ['deleted' => true]], 200);
    }

    /**
     * Validate the JSON request body and return only validated fields.
     *
     * @param array<string, string|list<string|callable>> $rules
     * @param array<string, string> $messages
     * @return array<string, mixed>
     */
    protected function validate(Request $request, array $rules, array $messages = []): array
    {
        return Validator::make($request->json(), $rules, $messages)->validate();
    }

    /**
     * Validate any array payload and return only validated fields.
     *
     * @param array<string, mixed> $data
     * @param array<string, string|list<string|callable>> $rules
     * @param array<string, string> $messages
     * @return array<string, mixed>
     */
    protected function validateData(array $data, array $rules, array $messages = []): array
    {
        return Validator::make($data, $rules, $messages)->validate();
    }
}
