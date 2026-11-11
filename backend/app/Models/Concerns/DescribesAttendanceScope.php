<?php

namespace App\Models\Concerns;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared by schedule and attendance-policy assignments: a scope_type
 * (App\Enums\AttendanceScope) + scope_id pair with an effective date range.
 *
 * @mixin Model
 */
trait DescribesAttendanceScope
{
    /**
     * Rows in force on a date (Y-m-d).
     *
     * @param  Builder<static>  $query
     */
    public function scopeEffectiveOn(Builder $query, string $date): void
    {
        $query->where($query->qualifyColumn('effective_from'), '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull($query->qualifyColumn('effective_to'))
                ->orWhere($query->qualifyColumn('effective_to'), '>=', $date));
    }

    /**
     * The record the assignment targets (null for the company), outside data scopes.
     */
    public function scopeRecord(): ?Model
    {
        $class = $this->scope_type->modelClass();

        return $class === null || $this->scope_id === null ? null : $class::query()->withoutGlobalScopes()->find($this->scope_id);
    }

    /**
     * "Department: IT · Information technology", "Company".
     */
    public function scopeLabel(): string
    {
        $record = $this->scopeRecord();

        if ($record === null) {
            return $this->scope_type->label().($this->scope_id ? ' #'.$this->scope_id : '');
        }

        $name = $record instanceof Employee ? $record->label() : trim(($record->code ?? '').' · '.$record->name, ' ·');

        return $this->scope_type->label().': '.$name;
    }
}
