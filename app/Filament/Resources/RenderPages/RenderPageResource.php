<?php

namespace App\Filament\Resources\RenderPages;

use App\Filament\Resources\RenderPages\Pages\CreateRenderPage;
use App\Filament\Resources\RenderPages\Pages\EditRenderPage;
use App\Filament\Resources\RenderPages\Pages\ListRenderPages;
use App\Filament\Resources\RenderPages\Schemas\RenderPageForm;
use App\Filament\Resources\RenderPages\Tables\RenderPagesTable;
use App\Models\RenderPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RenderPageResource extends Resource
{
    protected static ?string $model = RenderPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return RenderPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RenderPagesTable::configure($table);
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
            'index' => ListRenderPages::route('/'),
            'create' => CreateRenderPage::route('/create'),
            'edit' => EditRenderPage::route('/{record}/edit'),
        ];
    }
}
