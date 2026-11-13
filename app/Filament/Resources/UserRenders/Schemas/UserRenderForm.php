<?php

namespace App\Filament\Resources\UserRenders\Schemas;

use App\Models\User;
use App\Models\UserRender;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserRenderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),

                FileUpload::make('image')
                    ->label('Image')
                    ->image()
                    // the model moves the file to the disk matching the status on save
                    ->disk(fn (?UserRender $record): string => $record?->imageDisk() ?? UserRender::PRIVATE_DISK)
                    ->directory('user-renders')
                    ->getUploadedFileUsing(function (FileUpload $component, string $file, ?UserRender $record): ?array {
                        $disk = $component->getDisk();
                        if (! $disk->exists($file)) {
                            return null;
                        }

                        return [
                            'name' => basename($file),
                            'size' => $disk->size($file),
                            'type' => $disk->mimeType($file),
                            'url' => $record?->image === $file ? route('user-renders.image', $record->slug) : null,
                        ];
                    })
                    // images of renders rejected long ago are removed by the daily cleanup
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxSize(10240),

                Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->maxLength(5000)
                    ->columnSpanFull(),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->required()
                    ->default('pending'),

                Toggle::make('is_public')
                    ->label('Public')
                    ->default(false),

                Select::make('user_id')
                    ->label('Author')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }
}
