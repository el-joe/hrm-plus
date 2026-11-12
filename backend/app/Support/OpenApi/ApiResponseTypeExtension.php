<?php

namespace App\Support\OpenApi;

use App\Support\ApiResponse;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\GenericClassStringType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Teaches dedoc/scramble the /api/v1 envelope (App\Support\ApiResponse), so the OpenAPI document
 * shows the real shape of every answer: ApiResponse::success($data, $message, $status, $meta) →
 * {success, message, data: <type of $data, e.g. the API Resource schema>, meta?};
 * ApiResponse::paginated($paginator, Resource::class) → {success, message, data: Resource[], meta.pagination};
 * ApiResponse::error(...) → {success: false, message, code, errors?}.
 */
class ApiResponseTypeExtension implements StaticMethodReturnTypeExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === ApiResponse::class;
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'success' => $this->response([
                new ArrayItemType_('success', new LiteralBooleanType(true)),
                new ArrayItemType_('message', $this->message($event->getArg('message', 1, new NullType))),
                new ArrayItemType_('data', $this->data($event->getArg('data', 0, new NullType))),
                ...$this->meta($event->getArg('meta', 3, new UnknownType)),
            ], $event->getArg('status', 2, new LiteralIntegerType(200))),
            'paginated' => $this->response([
                new ArrayItemType_('success', new LiteralBooleanType(true)),
                new ArrayItemType_('message', $this->message($event->getArg('message', 2, new NullType))),
                new ArrayItemType_('data', new ArrayType($this->resourceType($event->getArg('resourceClass', 1)))),
                new ArrayItemType_('meta', new KeyedArrayType([
                    new ArrayItemType_('pagination', new KeyedArrayType([
                        new ArrayItemType_('current_page', new IntegerType),
                        new ArrayItemType_('per_page', new IntegerType),
                        new ArrayItemType_('total', new IntegerType),
                        new ArrayItemType_('last_page', new IntegerType),
                    ])),
                ])),
            ], new LiteralIntegerType(200)),
            'error' => $this->response([
                new ArrayItemType_('success', new LiteralBooleanType(false)),
                new ArrayItemType_('message', new StringType),
                new ArrayItemType_('code', $event->getArg('code', 2, new StringType)),
                new ArrayItemType_('errors', new ArrayType(new ArrayType(new StringType), new StringType), isOptional: true),
            ], $event->getArg('status', 1, new LiteralIntegerType(400))),
            default => null,
        };
    }

    /**
     * @param  list<ArrayItemType_>  $items
     */
    private function response(array $items, Type $status): Generic
    {
        return new Generic(JsonResponse::class, [new KeyedArrayType($items), $status, new ArrayType]);
    }

    private function message(Type $type): Type
    {
        return $type instanceof NullType || $type instanceof UnknownType ? new NullType : new StringType;
    }

    private function data(Type $type): Type
    {
        return $type instanceof UnknownType ? new NullType : $type;
    }

    /**
     * @return list<ArrayItemType_>
     */
    private function meta(Type $type): array
    {
        if ($type instanceof KeyedArrayType && $type->items !== []) {
            return [new ArrayItemType_('meta', $type)];
        }

        return [];
    }

    private function resourceType(Type $type): Type
    {
        if ($type instanceof GenericClassStringType && is_a($type->getValue(), JsonResource::class, true)) {
            return new ObjectType($type->getValue());
        }

        if ($type instanceof LiteralStringType && is_a($type->value, JsonResource::class, true)) {
            return new ObjectType($type->value);
        }

        return new UnknownType;
    }
}
