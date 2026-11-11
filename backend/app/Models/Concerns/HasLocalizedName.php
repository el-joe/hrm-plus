<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Bilingual naming convention of HRM Plus.
 *
 * A translatable attribute is stored as one column per supported locale with
 * the locale as suffix: `name_ar` and `name_en` (likewise `address_ar` /
 * `address_en`, `legal_name_ar` / `legal_name_en`). Plain columns keep the
 * values searchable, sortable and indexable, which a JSON column would not.
 *
 *     $branch->name;                     // in the current locale, falling back to the other one
 *     $branch->localized('address');     // any "{attribute}_{locale}" pair
 *     Branch::localizedColumn();         // "name_ar" or "name_en", for orderBy()
 *     Branch::query()->search('cai');    // code, name_ar or name_en contains the term
 *
 * @mixin Model
 */
trait HasLocalizedName
{
    /**
     * The name in the current locale (falls back to the other language).
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->localized('name'));
    }

    /**
     * Value of "{attribute}_{locale}", falling back to the other supported locale when empty.
     */
    public function localized(string $attribute, ?string $locale = null): ?string
    {
        $locales = static::translationLocales();
        $locale = in_array($locale ?? app()->getLocale(), $locales, true) ? ($locale ?? app()->getLocale()) : $locales[0];

        foreach ([$locale, ...array_diff($locales, [$locale])] as $candidate) {
            $value = $this->getAttribute($attribute.'_'.$candidate);

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * The column holding the attribute in the current locale.
     */
    public static function localizedColumn(string $attribute = 'name'): string
    {
        $locale = app()->getLocale();

        return $attribute.'_'.(in_array($locale, static::translationLocales(), true) ? $locale : static::translationLocales()[0]);
    }

    /**
     * Rows whose code or name (either language) contains the term.
     *
     * @param  Builder<static>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where(function (Builder $query) use ($term): void {
            $query->whereLike($query->qualifyColumn('name_ar'), "%{$term}%")
                ->orWhereLike($query->qualifyColumn('name_en'), "%{$term}%");

            if ($this->hasCodeColumn()) {
                $query->orWhereLike($query->qualifyColumn('code'), "%{$term}%");
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrderByName(Builder $query, string $direction = 'asc'): void
    {
        $query->orderBy($query->qualifyColumn(static::localizedColumn()), $direction);
    }

    /**
     * Locales with their own column, primary language first.
     *
     * @return list<string>
     */
    public static function translationLocales(): array
    {
        return ['ar', 'en'];
    }

    protected function hasCodeColumn(): bool
    {
        return true;
    }
}
