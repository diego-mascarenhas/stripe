<?php

namespace App\Filament\Resources\Subscriptions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'changes';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Historial de cambios')
            ->defaultSort('detected_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('detected_at')
                    ->label('Detectado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('changed_fields')
                    ->label('Campos')
                    ->formatStateUsing(fn ($state): string => $this->formatArray($state))
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('previous_values')
                    ->label('Antes')
                    ->formatStateUsing(fn ($state): string => $this->formatArray($state))
                    ->toggleable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('current_values')
                    ->label('Ahora')
                    ->formatStateUsing(fn ($state): string => $this->formatArray($state))
                    ->toggleable()
                    ->wrap(),
            ]);
    }

    private function formatArray($state): string
    {
        // Handle null or empty
        if ($state === null || $state === '' || $state === []) {
            return '—';
        }

        // If it's a string, try to decode it as JSON
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $state = $decoded;
            } else {
                // If it's not JSON, just return the string
                return $state ?: '—';
            }
        }

        // At this point, state should be an array
        if (! is_array($state)) {
            // Fallback: convert to string if possible
            return is_scalar($state) ? (string) $state : '—';
        }

        if (empty($state)) {
            return '—';
        }

        $stringify = function ($value) use (&$stringify): string {
            if (is_array($value)) {
                if (empty($value)) {
                    return '[]';
                }

                return collect($value)
                    ->map(fn ($nestedValue, $nestedKey) => is_string($nestedKey)
                        ? "{$nestedKey}: ".$stringify($nestedValue)
                        : $stringify($nestedValue))
                    ->implode(', ');
            }

            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            if ($value === null) {
                return 'null';
            }

            return (string) $value;
        };

        if (array_is_list($state)) {
            return collect($state)
                ->map(fn ($value) => '• '.$stringify($value))
                ->implode(PHP_EOL);
        }

        return collect($state)
            ->map(fn ($value, $key) => "{$key}: ".$stringify($value))
            ->implode(PHP_EOL);
    }
}

