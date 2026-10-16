<?php

namespace App\Filament\Admin\Resources\ReceivedWebhooks\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReceivedWebhookInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id'),
                        TextEntry::make('endpoint.name'),
                        TextEntry::make('method')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('ip'),
                        TextEntry::make('content_type'),
                        TextEntry::make('created_at')->dateTime(),
                    ]),
                Section::make('Headers')
                    ->collapsed()
                    ->schema([
                        KeyValueEntry::make('headers')
                            ->keyLabel('Header')
                            ->valueLabel('Value'),
                    ]),
                Section::make('Query')
                    ->collapsed()
                    ->visible(fn ($record) => ! empty($record->query))
                    ->schema([
                        KeyValueEntry::make('query'),
                    ]),
                Section::make('Body')
                    ->schema([
                        TextEntry::make('body')
                            ->copyable()
                            ->copyableState(fn ($record) => $record->body ?? '')
                            ->copyMessage('Body copied')
                            ->html()
                            ->formatStateUsing(function (?string $state): string {
                                if (blank($state)) {
                                    return '<span class="text-gray-500 dark:text-gray-400">—</span>';
                                }

                                $decoded = json_decode($state, true);
                                $formatted = json_last_error() === JSON_ERROR_NONE
                                    ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                                    : $state;

                                return '<pre class="overflow-x-auto whitespace-pre-wrap break-words rounded-md bg-gray-50 p-3 text-sm dark:bg-white/5">'.e($formatted).'</pre>';
                            })
                            ->columnSpanFull(),
                    ]),
                Section::make('Delivery attempts')
                    ->schema([
                        RepeatableEntry::make('attempts')
                            ->schema([
                                TextEntry::make('target.name')->label('Target'),
                                TextEntry::make('attempt')->label('#'),
                                TextEntry::make('status')->badge(),
                                TextEntry::make('response_status')->label('HTTP'),
                                TextEntry::make('duration_ms')->suffix(' ms'),
                                TextEntry::make('error')->placeholder('—'),
                                TextEntry::make('delivered_at')->dateTime()->placeholder('—'),
                            ])
                            ->columns(7)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
