<?php

namespace App\Filament\Resources\RenderPages\Pages;

use App\Filament\Resources\RenderPages\RenderPageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRenderPage extends EditRecord
{
    protected static string $resource = RenderPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
