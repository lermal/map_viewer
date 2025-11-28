<?php

namespace App\Filament\Resources\UserRenders;

use App\Filament\Resources\UserRenders\Pages\CreateUserRender;
use App\Filament\Resources\UserRenders\Pages\EditUserRender;
use App\Filament\Resources\UserRenders\Pages\ListUserRenders;
use App\Filament\Resources\UserRenders\Schemas\UserRenderForm;
use App\Filament\Resources\UserRenders\Tables\UserRendersTable;
use App\Models\UserRender;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserRenderResource extends Resource
{
    protected static ?string $model = UserRender::class;

    protected static ?string $navigationLabel = 'User Renders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    public static function form(Schema $schema): Schema
    {
        return UserRenderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserRendersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserRenders::route('/'),
            'create' => CreateUserRender::route('/create'),
            'edit' => EditUserRender::route('/{record}/edit'),
        ];
    }
}
