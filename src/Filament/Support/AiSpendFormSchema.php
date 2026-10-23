<?php

namespace Spiggle\VisualDashboards\Filament\Support;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Spiggle\VisualDashboards\Models\AiService;

class AiSpendFormSchema
{
    /**
     * @return array<int, mixed>
     */
    public static function serviceFields(): array
    {
        return [
            TextInput::make('name')->label('Service')->required()->maxLength(80),
            TextInput::make('slug')->required()->maxLength(80)->unique(ignoreRecord: true),
            Select::make('vendor_key')
                ->label('Vendor')
                ->options([
                    'openai' => 'OpenAI',
                    'google' => 'Google AI',
                    'azure' => 'Azure',
                    'bedrock' => 'Amazon Bedrock',
                    'replicate' => 'Replicate',
                    'other' => 'Other',
                ])
                ->required()
                ->native(false),
            TextInput::make('color')->label('Chart color')->default('#22c55e')->maxLength(16),
            TextInput::make('cost_per_million')->label('$/1M tokens')->numeric()->minValue(0),
            TextInput::make('initials')->maxLength(4),
            TextInput::make('sort_order')->numeric()->default(0),
            Toggle::make('is_active')->default(true),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function usageFields(): array
    {
        return [
            Select::make('service_id')
                ->label('Service')
                ->options(fn (): array => AiService::query()->orderBy('name')->pluck('name', 'id')->all())
                ->required()
                ->searchable()
                ->native(false),
            DatePicker::make('reported_on')->label('Usage date')->required()->native(false)->default(now()),
            TextInput::make('tokens')->numeric()->required()->minValue(0),
            TextInput::make('spend_usd')->label('Spend (USD)')->numeric()->required()->minValue(0),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function anomalyFields(): array
    {
        return [
            Select::make('service_id')
                ->label('Service')
                ->options(fn (): array => AiService::query()->orderBy('name')->pluck('name', 'id')->all())
                ->required()
                ->searchable()
                ->native(false),
            DatePicker::make('detected_on')->required()->native(false)->default(now()),
            TextInput::make('amount_usd')->label('Spike amount')->numeric()->required(),
            TextInput::make('title')->required()->maxLength(160),
            Textarea::make('note')->rows(2),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function recommendationFields(): array
    {
        return [
            Select::make('kind')
                ->options([
                    'switch' => 'Switch model',
                    'pause' => 'Pause idle',
                    'fix' => 'Fix errors',
                ])
                ->required()
                ->native(false),
            TextInput::make('title')->required()->maxLength(160),
            Textarea::make('body')->required()->rows(3),
            TextInput::make('action_label')->default('View details')->maxLength(40),
            TextInput::make('savings_label')->maxLength(80),
            TextInput::make('sort_order')->numeric()->default(0),
            Toggle::make('is_active')->default(true),
        ];
    }
}
