<?php

namespace Chargefield\Store\Studio;

use Illuminate\Database\Eloquent\Builder;

/**
 * A record linked to a Studio PM client by `studio_client_id`: a chargefield
 * client, a johngodfrey Work, anything that was made for someone. The id is
 * Studio's, so the same client reads the same on every site.
 *
 * The model names the column holding its own free-text client name with
 * `studioClientNameColumn()` (default `client`); `studio:link-clients` matches
 * that text to Studio's list for rows that aren't linked yet.
 *
 * @property int|null $studio_client_id
 */
trait LinksStudioClient
{
    public static function studioClientNameColumn(): string
    {
        return 'client';
    }

    public function studioClientName(): ?string
    {
        return StudioClients::name($this->studio_client_id ? (int) $this->studio_client_id : null);
    }

    public function studioClientUrl(): ?string
    {
        return $this->studio_client_id ? StudioClients::url((int) $this->studio_client_id) : null;
    }

    /** @param  Builder<static>  $query */
    public function scopeForStudioClient(Builder $query, int $studioClientId): void
    {
        $query->where($this->qualifyColumn('studio_client_id'), $studioClientId);
    }
}
