<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['evaluation_form_id', 'name_ar', 'name_en', 'description', 'weight', 'sort_order'])]
class EvaluationCriterion extends Model
{
    use HasLocalizedName;

    protected $table = 'evaluation_criteria';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['weight' => 'integer', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<EvaluationForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class, 'evaluation_form_id');
    }
}
