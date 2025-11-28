<?php

namespace App\Filament\Resources\RenderPages\Schemas;

use App\Models\RenderPage;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RenderPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, callable $set, $get, $record) {
                        $newSlug = Str::slug($state);

                        if ($operation === 'create') {
                            $set('slug', $newSlug);
                        } elseif ($record && $operation === 'edit') {
                            $oldSlug = Str::slug($record->name);
                            if ($get('slug') === $oldSlug) {
                                $set('slug', $newSlug);
                            }
                        }
                    }),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(RenderPage::class, 'slug', ignoreRecord: true)
                    ->alphaDash(),

                TextInput::make('json_path')
                    ->required()
                    ->maxLength(255)
                    ->label('JSON Path')
                    ->helperText('Path to JSON file relative to storage/app/data/pages/')
                    ->placeholder('shuttles.json'),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),

                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->label('Sort Order'),

                Toggle::make('is_active')
                    ->default(true)
                    ->label('Is Active'),

                TextInput::make('meta_title')
                    ->maxLength(255)
                    ->label('Meta Title'),

                Textarea::make('meta_description')
                    ->rows(2)
                    ->label('Meta Description')
                    ->columnSpanFull(),
            ]);
    }
}
