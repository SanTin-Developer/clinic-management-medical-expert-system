<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpertEvaluationAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'symptom_ids',
        'matched_rule_count',
        'matched_rule_codes',
        'engine_version',
        'is_clinically_validated',
    ];

    protected function casts(): array
    {
        return [
            'matched_rule_count' => 'integer',
            'is_clinically_validated' => 'boolean',
        ];
    }
}
