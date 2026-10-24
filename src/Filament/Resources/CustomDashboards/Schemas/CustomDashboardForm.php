<?php

namespace Spiggle\VisualDashboards\Filament\Resources\CustomDashboards\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Spiggle\VisualDashboards\Models\CustomDashboard;
use Spiggle\VisualDashboards\Support\DashletAccess;

class CustomDashboardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('General')
                ->description('Name and identity for this dashboard.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                            if (blank($get('slug')) && filled($state)) {
                                $userId = (int) ($get('user_id') ?: Auth::id());
                                $set('slug', CustomDashboard::uniqueSlugForUser($userId, $state));
                            }

                            if (blank($get('heading')) && filled($state)) {
                                $set('heading', $state);
                            }
                        })
                        ->placeholder('e.g. Sales overview'),
                    TextInput::make('slug')
                        ->required()
                        ->maxLength(120)
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->helperText('Used in the share URL. Signed-in allowed viewers can open it anytime; enable Public link for anonymous access.'),
                    TextInput::make('heading')
                        ->maxLength(160)
                        ->placeholder('Heading shown on the canvas'),
                    Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull()
                        ->placeholder('Optional description for this dashboard'),
                    Select::make('user_id')
                        ->label('Owner')
                        ->relationship('user', 'name')
                        ->default(fn (): ?int => Auth::id())
                        ->required()
                        ->searchable(),
                ]),
            Section::make('Visibility')
                ->description('Share URL access. Signed-in allowed viewers can always open the link; Public link only adds anonymous access.')
                ->schema([
                    Toggle::make('is_published')
                        ->label('Public link')
                        ->default(false)
                        ->inline(false)
                        ->helperText('Off (default): guests are sent to login; signed-in users who can view this dashboard still open the share URL. On: anyone with the link can view without signing in. Separate from viewer roles below.'),
                ]),
            Section::make('Access')
                ->description('Who can open this dashboard in the admin panel and via the share URL when signed in.')
                ->schema([
                    Select::make('viewer_roles')
                        ->label('Viewer roles')
                        ->multiple()
                        ->searchable()
                        ->options(fn (): array => DashletAccess::roleOptions())
                        ->helperText('Leave empty = any authenticated user with Custom Dashboards view permission. If roles are selected, the user must have at least one of them. Owners and users who can manage dashboards always retain access. Does not restrict anonymous Public link viewers.'),
                ]),
        ]);
    }
}
