<?php

namespace Tests\Fixtures;

use App\Models\Concerns\HasApprovals;
use Illuminate\Database\Eloquent\Model;

/**
 * Test-only stand-in for anything that needs approval (leave request, loan, ...).
 */
class ApprovableDocument extends Model
{
    use HasApprovals;

    protected $guarded = [];

    public $timestamps = false;
}
