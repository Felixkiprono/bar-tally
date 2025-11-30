<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\TestJobResource\Pages;
use App\Jobs\TestJob;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class TestJobResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Test Job';
    protected static ?string $navigationGroup = 'Testing';
    protected static ?int $navigationSort = 1;
    protected static bool $shouldRegisterNavigation = false; // This hides it from the menu

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn () => (new class extends Model {
                protected $table = 'users';
                public function newQuery()
                {
                    return parent::newQuery()->whereRaw('1 = 1')->limit(1);
                }
            })->newQuery())
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('')
                    ->formatStateUsing(fn () => 'Test Job Trigger')
            ])
            ->actions([
                Tables\Actions\Action::make('trigger')
                    ->label('Trigger Test Job')
                    ->icon('heroicon-o-play')
                    ->action(function () {
                        $tenantId = app('currentTenant')->id;
                        TestJob::dispatch($tenantId);
                        Log::info('TestJob triggered from Filament resource');
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Trigger Test Job')
                    ->modalDescription('Are you sure you want to trigger the test job?')
                    ->modalSubmitActionLabel('Yes, trigger it')
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTestJobs::route('/'),
        ];
    }
}