<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpertRuleCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'expert_rule_id',
        'symptom_id',
        'operator',
        'expected_value',
        'condition_group',
    ];

    protected function casts(): array
    {
        return [
            'expected_value' => 'boolean',
            'condition_group' => 'integer',
        ];
    }

    public function expertRule(): BelongsTo
    {
        return $this->belongsTo(ExpertRule::class);
    }

    public function symptom(): BelongsTo
    {
        return $this->belongsTo(Symptom::class);
    }
}
