<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\UserResource\Pages;
use App\Filament\Tenant\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Gate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-m-user-group';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'User Management';

    protected static ?string $pluralNavigationLabel = 'User Management';

    protected static ?string $pluralModelLabel = 'Users';

    protected static ?string $modelLabel = 'User';

    protected static ?int $navigationGroupSort = 6;
    protected static ?int $navigationSort = 8;


    public static function canAccess(): bool
    {
        $user = auth()->user();
        // \Log::info('Filament resource access check', [
        //     'user' => $user->email,
        //     'role' => $user->role,
        //     'allowed_roles' => [User::ROLE_TENANT_ADMIN, User::ROLE_SUPER_ADMIN],
        //     'check_result' => Gate::allows('viewAny', \App\Models\User::class)
        // ]);

        return Gate::allows('viewAny', \App\Models\User::class);
    }
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(auth()->check(), function ($query) {
                return $query->where('tenant_id', auth()->user()->tenant_id);
            });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('role')
                    ->options(User::getRoles())
                    ->required(),
                Hidden::make('tenant_id')
                    ->default(auth()->user()->tenant_id),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-envelope'),
                TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'tenant_admin' => 'success',
                        'meter_reader' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'super_admin' => 'Super Admin',
                        'tenant_admin' => 'Tenant Admin',
                        'meter_reader' => 'Meter Reader',
                        default => ucfirst($state),
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options(User::getRoles())
                    ->label('Filter by Role'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn(Builder $query) => $query->where('role', '!=', 'customer'));
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
