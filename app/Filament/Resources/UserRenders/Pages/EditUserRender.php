<?php

namespace App\Filament\Resources\UserRenders\Pages;

use App\Filament\Resources\UserRenders\UserRenderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUserRender extends EditRecord
{
    protected static string $resource = UserRenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
