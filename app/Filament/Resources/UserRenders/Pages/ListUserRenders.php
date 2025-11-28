<?php

namespace App\Filament\Resources\UserRenders\Pages;

use App\Filament\Resources\UserRenders\UserRenderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserRenders extends ListRecords
{
    protected static string $resource = UserRenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
