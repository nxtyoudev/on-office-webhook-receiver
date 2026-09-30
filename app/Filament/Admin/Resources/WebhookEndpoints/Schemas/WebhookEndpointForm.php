<?php

namespace App\Filament\Admin\Resources\WebhookEndpoints\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class WebhookEndpointForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->helperText('Leave blank to auto-generate. Inbound URL: /api/webhooks/{slug}')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->placeholder(fn () => Str::random(24)),
                TextInput::make('secret')
                    ->password()
                    ->revealable()
                    ->helperText('If set, inbound requests must carry a valid HMAC-SHA256 signature of the raw body.')
                    ->maxLength(255),
                TextInput::make('signature_header')
                    ->helperText('Header carrying the signature. Defaults to X-Webhook-Signature.')
                    ->placeholder('X-Webhook-Signature')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
