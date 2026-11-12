<?php

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Every concrete Eloquent model in app/Models.
 *
 * @return list<class-string<Model>>
 */
function applicationModels(): array
{
    $models = [];

    foreach (glob(dirname(__DIR__, 3).'/app/Models/*.php') as $file) {
        $class = 'App\\Models\\'.basename($file, '.php');

        if (class_exists($class) && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract()) {
            $models[] = $class;
        }
    }

    return $models;
}

dataset('models', fn (): array => array_combine(
    array_map(fn (string $class): string => class_basename($class), applicationModels()),
    array_map(fn (string $class): array => [$class], applicationModels()),
));

it('has a factory for every model', function () {
    $missing = array_values(array_filter(applicationModels(), fn (string $class): bool => ! in_array(HasFactory::class, class_uses_recursive($class), true)
        || ! class_exists('Database\\Factories\\'.class_basename($class).'Factory')));

    expect($missing)->toBe([]);
});

it('creates a valid record with the factory', function (string $class) {
    // JobApplicationFactory places applications in the default pipeline's entry stage.
    seedRecruitment();

    /** @var Model $model */
    $model = $class::factory()->create();

    $this->assertModelExists($model);
})->with('models');
