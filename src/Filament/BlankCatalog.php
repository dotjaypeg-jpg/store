<?php

namespace Chargefield\Store\Filament;

use Chargefield\Store\Catalog\BlankSync;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * The bits a site's own Blanks admin wires in once the shared catalogue is on:
 * blanks that came from Studio are read-only here (Studio's copy wins on every
 * sync), with a link to edit them on Studio, and the list gets a "Sync now".
 */
class BlankCatalog
{
    /** True when this blank is Studio's and must be edited there. */
    public static function locked(?Model $record): bool
    {
        return $record !== null && filled($record->getAttribute('catalog_id')) && BlankSync::enabled();
    }

    public static function syncAction(): Action
    {
        return Action::make('syncFromStudio')
            ->label('Sync from Studio')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn () => BlankSync::enabled())
            ->action(function () {
                try {
                    $s = app(BlankSync::class)->sync();
                    Notification::make()->success()->title('Blanks synced')
                        ->body("{$s['created']} new, {$s['updated']} updated from Studio.")->send();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('Couldn\'t reach Studio')->body($e->getMessage())->send();
                }
            });
    }

    public static function editOnStudioAction(): Action
    {
        return Action::make('editOnStudio')
            ->label('Edit in Studio')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->visible(fn ($record) => self::locked($record))
            ->url(fn ($record) => BlankSync::editUrl((int) $record->getAttribute('catalog_id')), shouldOpenInNewTab: true);
    }

    public static function lockedNotice(): string
    {
        return 'This blank is shared from Studio. Edit it there and every store picks up the change within the hour.';
    }
}
