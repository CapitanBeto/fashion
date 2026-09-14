<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PromptVersion extends Model
{
    protected $fillable = [
        'prompt_key', 'version', 'task', 'system_prompt',
        'user_template', 'output_schema', 'active',
    ];

    protected $casts = [
        'output_schema' => 'array',
        'active'        => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeForTask(Builder $query, string $task): Builder
    {
        return $query->where('task', $task);
    }

    public static function getActive(string $key): ?self
    {
        return static::where('prompt_key', $key)->where('active', true)->first();
    }

    public function render(array $variables = []): string
    {
        $template = $this->user_template ?? '';
        foreach ($variables as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        return $template;
    }
}
