<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use Filament\Forms\Components\MarkdownEditor;

/**
 * Markdown editor without file attachments. Filament stores attachments on the public disk,
 * so a pasted screenshot would be reachable without login; links go to the Links tab instead.
 */
final class MarkdownField
{
    public static function make(string $name): MarkdownEditor
    {
        return MarkdownEditor::make($name)
            ->toolbarButtons([['bold', 'italic', 'link'], ['heading', 'bulletList', 'orderedList'], ['undo', 'redo']]);
    }
}
