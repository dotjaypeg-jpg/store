<?php

namespace Chargefield\Store\Masters;

use Illuminate\Support\Str;

/**
 * The one set of categories every site files its masters under, so the library
 * in Studio and the offline drive index can be browsed by kind of work as well
 * as by client. An owner sets `master_category` to override the guess.
 */
class MasterCategories
{
    public const LIST = [
        'movie_poster' => 'Movie posters',
        'album_art' => 'Album art',
        'music_poster' => 'Music & gig posters',
        'game_art' => 'Game art',
        'event_poster' => 'Event posters',
        'editorial' => 'Editorial & covers',
        'title_motion' => 'Titles & motion',
        'pitch_epk' => 'Pitch decks & EPKs',
        'apparel' => 'Apparel',
        'illustration' => 'Illustration & personal',
        'other' => 'Other',
    ];

    /**
     * Keyword => category, checked in order against free text such as a work's
     * medium ("Music Poster", "EPK Cover", "Screenprint"). First hit wins.
     */
    private const KEYWORDS = [
        'album' => 'album_art',
        'vinyl' => 'album_art',
        'single cover' => 'album_art',
        'music' => 'music_poster',
        'gig' => 'music_poster',
        'tour' => 'music_poster',
        'concert' => 'music_poster',
        'game' => 'game_art',
        'event' => 'event_poster',
        'festival' => 'event_poster',
        'epk' => 'pitch_epk',
        'pitch' => 'pitch_epk',
        'deck' => 'pitch_epk',
        'cover' => 'editorial',
        'editorial' => 'editorial',
        'magazine' => 'editorial',
        'book' => 'editorial',
        'title' => 'title_motion',
        'motion' => 'title_motion',
        'apparel' => 'apparel',
        't-shirt' => 'apparel',
        'tee' => 'apparel',
        'hoodie' => 'apparel',
        'film' => 'movie_poster',
        'movie' => 'movie_poster',
        'key art' => 'movie_poster',
        'one-sheet' => 'movie_poster',
        'illustration' => 'illustration',
        'screenprint' => 'illustration',
        'personal' => 'illustration',
    ];

    /** @return array<string, string> key => label, for a Select. */
    public static function options(): array
    {
        return self::LIST;
    }

    public static function label(?string $key): ?string
    {
        return $key === null ? null : (self::LIST[$key] ?? Str::headline($key));
    }

    public static function valid(?string $key): bool
    {
        return $key !== null && isset(self::LIST[$key]);
    }

    /** Best guess from free text (a medium, a service type), or null. */
    public static function guess(?string $text): ?string
    {
        $text = ' '.Str::lower(trim((string) $text)).' ';
        if (trim($text) === '') {
            return null;
        }

        foreach (self::KEYWORDS as $word => $category) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/', $text)) {
                return $category;
            }
        }

        return null;
    }
}
