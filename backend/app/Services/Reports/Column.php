<?php

namespace App\Services\Reports;

/**
 * One column of a report: label, value type (drives screen formatting, export cell type and
 * totals) and an optional permission guard.
 *
 * Types: text, code (LTR identifiers), number (integer), decimal, money (DECIMAL string,
 * bcmath), percent, date, datetime, duration (pre-formatted "h:mm").
 * Guards: "financial" (employee_financials.view, logged) and "disciplinary"
 * (disciplinary.view, logged) — guarded columns are dropped for admins without the permission,
 * on screen and in every export.
 */
final class Column
{
    public const GUARDS = [
        'financial' => 'employee_financials.view',
        'disciplinary' => 'disciplinary.view',
    ];

    public function __construct(
        public readonly string $label,
        public readonly string $type = 'text',
        public readonly bool $total = false,
        public readonly int $decimals = 0,
        public readonly ?string $guard = null,
    ) {}

    public static function text(string $label): self
    {
        return new self($label);
    }

    public static function code(string $label): self
    {
        return new self($label, 'code');
    }

    public static function number(string $label, bool $total = true): self
    {
        return new self($label, 'number', $total);
    }

    public static function decimal(string $label, int $decimals = 2, bool $total = true): self
    {
        return new self($label, 'decimal', $total, $decimals);
    }

    public static function money(string $label, bool $total = true): self
    {
        return new self($label, 'money', $total, guard: 'financial');
    }

    /**
     * An amount that is not personal pay (asset value, training cost): money formatting, no guard.
     */
    public static function amount(string $label, bool $total = true): self
    {
        return new self($label, 'money', $total);
    }

    public static function percent(string $label, int $decimals = 1): self
    {
        return new self($label, 'percent', false, $decimals);
    }

    public static function date(string $label): self
    {
        return new self($label, 'date');
    }

    public static function dateTime(string $label): self
    {
        return new self($label, 'datetime');
    }

    public static function duration(string $label): self
    {
        return new self($label, 'duration');
    }

    public function guardedBy(?string $guard): self
    {
        return new self($this->label, $this->type, $this->total, $this->decimals, $guard);
    }

    public function withoutTotal(): self
    {
        return new self($this->label, $this->type, false, $this->decimals, $this->guard);
    }

    public function isNumeric(): bool
    {
        return in_array($this->type, ['number', 'decimal', 'money', 'percent'], true);
    }

    /**
     * Left-to-right content (numbers, dates, codes) inside RTL pages.
     */
    public function isLtr(): bool
    {
        return $this->type !== 'text';
    }

    public function permission(): ?string
    {
        return $this->guard === null ? null : self::GUARDS[$this->guard];
    }
}
