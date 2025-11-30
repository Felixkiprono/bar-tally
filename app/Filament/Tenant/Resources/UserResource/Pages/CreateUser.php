<?php

namespace App\Filament\Tenant\Resources\UserResource\Pages;

use App\Filament\Tenant\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Log the data before modification
        Log::info('User creation data before modification:', $data);

        // Set tenant_id from authenticated user
        $data['tenant_id'] = Auth::user()->tenant_id;

        // Hash the password
        $data['password'] = bcrypt($data['password']);

        // Log the data after modification
        Log::info('User creation data after modification:', $data);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Log successful creation
        Log::info('User created successfully:', ['user_id' => $this->record->id]);

        // Show a notification
        Notification::make()
            ->title('User created successfully')
            ->success()
            ->send();
    }

    protected function onValidationError(ValidationException $exception): void
    {
        // Log validation errors
        Log::error('User creation validation errors:', ['errors' => $exception->errors()]);

        // Show a notification with the errors
        Notification::make()
            ->title('Validation error')
            ->body(implode(', ', array_map(fn($field, $messages) => "$field: " . implode(', ', $messages), array_keys($exception->errors()), $exception->errors())))
            ->danger()
            ->send();

        parent::onValidationError($exception);
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            // Log the attempt to create the record
            Log::info('Attempting to create user record');

            $record = parent::handleRecordCreation($data);

            // Log successful record creation
            Log::info('User record created successfully', ['user_id' => $record->id]);

            return $record;
        } catch (\Exception $e) {
            // Log the exception
            Log::error('Error creating user record: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            // Show a notification with the error
            Notification::make()
                ->title('Error creating user')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }
}
