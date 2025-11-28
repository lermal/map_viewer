<?php

namespace App\Filament\Resources\RenderPages\Pages;

use App\Filament\Resources\RenderPages\RenderPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRenderPages extends ListRecords
{
    protected static string $resource = RenderPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
