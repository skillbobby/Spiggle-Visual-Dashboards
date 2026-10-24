<?php

namespace Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\CustomDashboardResource;

class CreateCustomDashboard extends CreateRecord
{
    protected static string $resource = CustomDashboardResource::class;

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->compact()
                ->schema([
                    TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(120)
                        ->autofocus()
                        ->placeholder('Sales overview'),
                ])
                ->footerActions([
                    Action::make('openCanvas')
                        ->label('Open canvas')
                        ->submit('create'),
                ]),
        ]);
    }

    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = (int) Auth::id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
