<?php

namespace Tests;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Views reference Vite assets; tests must not depend on a built manifest.
        $this->withoutVite();

        // The application enforces a morph map; register the test-only models too.
        Relation::morphMap([
            'approvable_document' => Fixtures\ApprovableDocument::class,
            'scoped_record' => Fixtures\ScopedRecord::class,
            'scoped_record_note' => Fixtures\ScopedRecordNote::class,
        ]);
    }

    /**
     * Migrate the application schema plus the tables of the test-only models
     * in tests/Fixtures.
     *
     * @return array<string, mixed>
     */
    protected function migrateUsing(): array
    {
        return [
            '--seed' => $this->shouldSeed(),
            '--seeder' => $this->seeder(),
            ...$this->fixtureMigrationPaths(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing(): array
    {
        return [
            '--drop-views' => $this->shouldDropViews(),
            '--drop-types' => $this->shouldDropTypes(),
            '--seed' => $this->shouldSeed(),
            '--seeder' => $this->seeder(),
            ...$this->fixtureMigrationPaths(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fixtureMigrationPaths(): array
    {
        return [
            '--path' => [database_path('migrations'), base_path('tests/Fixtures/migrations')],
            '--realpath' => true,
        ];
    }
}
