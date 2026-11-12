<?php

namespace App\Enums;

/**
 * Kind of investigation record on a disciplinary case.
 */
enum DisciplinaryNoteType: string
{
    case Note = 'note';
    case Interview = 'interview';
    case Finding = 'finding';

    public function label(): string
    {
        return __('disciplinary.enums.note_types.'.$this->value);
    }
}
