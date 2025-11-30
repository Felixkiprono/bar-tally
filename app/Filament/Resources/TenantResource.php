<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Filament\Resources\TenantResource\RelationManagers;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
        ->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('domain')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            Forms\Components\TextInput::make('telephone')
                ->tel()
                ->maxLength(20),
            Forms\Components\TextInput::make('location')
                ->maxLength(255),
                Forms\Components\TextInput::make('database')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->default(fn () => 'tenant_' . Str::random(8))
                ->helperText('Database name will be auto-generated, but you can customize it.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->columns([
            Tables\Columns\TextColumn::make('name')
                ->searchable(),
            Tables\Columns\TextColumn::make('domain')
                ->searchable(),
            Tables\Columns\TextColumn::make('telephone')
                ->searchable(),
            Tables\Columns\TextColumn::make('location')
                ->searchable(),
            Tables\Columns\TextColumn::make('database'),

            Tables\Columns\TextColumn::make('created_at')
                ->dateTime(),
        ])
        ->filters([
            //
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make()
                ->before(function (Tenant $record) {
                    // Add logic to drop tenant database if needed
                }),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
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
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
