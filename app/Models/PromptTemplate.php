<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromptTemplate extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'content',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Lädt einen Prompt anhand seines Keys.
     * Gibt den content zurück oder den $fallback falls kein Eintrag existiert.
     */
    public static function getContent(string $key, string $fallback = ''): string
    {
        $template = static::where('key', $key)->where('is_active', true)->first();
        return $template?->content ?? $fallback;
    }

    /**
     * Lädt einen Prompt und ersetzt Platzhalter {key} mit den übergebenen Werten.
     */
    public static function render(string $key, array $vars = [], string $fallback = ''): string
    {
        $content = static::getContent($key, $fallback);

        foreach ($vars as $placeholder => $value) {
            $content = str_replace('{' . $placeholder . '}', $value, $content);
        }

        return $content;
    }
}
