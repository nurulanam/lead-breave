<?php

namespace App\Filament\Resources\Searches;

use App\Enums\SearchStatus;
use App\Models\Search;
use App\Services\Search\SearchRunner;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Re-run and stop, shared by the searches table and the search page.
 */
class SearchActions
{
    public static function rerun(): Action
    {
        return Action::make('rerun')
            ->label('Re-run')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->authorize('update')
            ->visible(fn (Search $record): bool => $record->status->isFinished())
            ->requiresConfirmation()
            ->modalHeading('Run this search again?')
            ->modalDescription('Every cell is searched again and billed again. Leads found before stay linked to this search.')
            ->modalSubmitActionLabel('Re-run')
            ->action(function (Search $record, SearchRunner $runner): void {
                $runner->rerun($record)
                    ? Notification::make()->title('Search queued')->success()->send()
                    : Notification::make()->title('This search is already running.')->warning()->send();
            });
    }

    public static function stop(): Action
    {
        return Action::make('stop')
            ->label('Stop')
            ->icon(Heroicon::OutlinedStopCircle)
            ->color('danger')
            ->authorize('update')
            ->visible(fn (Search $record): bool => in_array($record->status, [SearchStatus::Pending, SearchStatus::Running], true))
            ->requiresConfirmation()
            ->modalHeading('Stop this search?')
            ->modalDescription('Cells already searched keep their leads. You can re-run it later.')
            ->modalSubmitActionLabel('Stop search')
            ->action(function (Search $record, SearchRunner $runner): void {
                $runner->stop($record, 'Stopped by '.(auth()->user()->name ?? 'a user').'.');

                Notification::make()->title('Search stopped')->success()->send();
            });
    }
}
