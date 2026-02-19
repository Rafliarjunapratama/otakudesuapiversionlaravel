<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class OtakotakuController extends Controller
{
    private array $headers = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    ];

    /**
     * GET /api/anime/otakotaku/search?q=...&q_filter=anime
     * Cari anime di OtakOtaku beserta karakter & seiyuu
     */
    public function search(Request $request)
    {
        $query     = $request->query('q', 'watanare');
        $filter    = $request->query('q_filter', 'anime');
        $searchUrl = 'https://otakotaku.com/anime/search?q=' . urlencode($query) . '&q_filter=' . $filter;

        try {
            // 1. Ambil hasil search
            $searchHtml = Http::withHeaders($this->headers)->get($searchUrl)->body();
            $crawler    = new Crawler($searchHtml);

            $firstAnime = $crawler->filter('.anime-list')->first();
            if (!$firstAnime->count()) {
                return response()->json(['error' => 'Anime tidak ditemukan'], 404);
            }

            $title = trim($firstAnime->filter('.anime-title a')->text(''));
            $link  = $firstAnime->filter('.anime-title a')->attr('href');

            if (!$link) {
                return response()->json(['error' => 'Link anime kosong'], 404);
            }

            $imgRaw  = $firstAnime->filter('.anime-img img')->count()
                ? ($firstAnime->filter('.anime-img img')->attr('src') ?: $firstAnime->filter('.anime-img img')->attr('data-src'))
                : null;
            $img     = $imgRaw ? preg_replace('/\/thumb\/\d+x\d+\//', '/', $imgRaw) : null;
            $sinopsis = trim($firstAnime->filter('.sinopsis-anime')->text(''));
            $tipe    = $firstAnime->filter('table tr:nth-child(1) td:nth-child(2) a')->count()
                ? trim($firstAnime->filter('table tr:nth-child(1) td:nth-child(2) a')->text(''))
                : '';
            $eps     = $firstAnime->filter('table tr:nth-child(2) td:nth-child(2)')->count()
                ? trim($firstAnime->filter('table tr:nth-child(2) td:nth-child(2)')->text(''))
                : '';
            $musim   = $firstAnime->filter('table tr:nth-child(3) td:nth-child(2) a')->count()
                ? trim($firstAnime->filter('table tr:nth-child(3) td:nth-child(2) a')->text(''))
                : '';

            // 2. Ambil skor anime dari halaman detail
            $skor = null;
            try {
                $animePageHtml = Http::withHeaders($this->headers)->get($link)->body();
                $animeCrawler  = new Crawler($animePageHtml);
                if ($animeCrawler->filter('.skor_anime')->count()) {
                    $skor = trim($animeCrawler->filter('.skor_anime')->first()->text(''));
                }
            } catch (\Exception $e) {
                // Gagal fetch skor, lanjutkan
            }

            // 3. Ambil karakter & seiyuu
            $characterLink = str_replace('/view/', '/character/', $link);
            $characters    = [];

            try {
                $charHtml    = Http::withHeaders($this->headers)->get($characterLink)->body();
                $charCrawler = new Crawler($charHtml);

                $charCrawler->filter('.anime-char-list')->each(function (Crawler $el) use (&$characters) {
                    $charName  = trim($el->filter('.char-name a')->text(''));
                    $charLink  = $el->filter('.char-name a')->attr('href');

                    $charImgRaw = $el->filter('.char-img img')->count()
                        ? ($el->filter('.char-img img')->attr('src') ?: $el->filter('.char-img img')->attr('data-src'))
                        : null;
                    $charImg   = $charImgRaw ? preg_replace('/\/thumb\/\d+x\d+\//', '/', $charImgRaw) : null;

                    $charType  = $el->filter('.char-jenis-karakter small')->count()
                        ? trim($el->filter('.char-jenis-karakter small')->text(''))
                        : '';

                    $seiyuuName   = $el->filter('.char-seiyuu-list a')->count()
                        ? trim($el->filter('.char-seiyuu-list a')->text(''))
                        : '';
                    $seiyuuLink   = $el->filter('.char-seiyuu-list a')->count()
                        ? $el->filter('.char-seiyuu-list a')->attr('href')
                        : null;
                    $seiyuuImgRaw = $el->filter('.seiyuu-img img')->count()
                        ? ($el->filter('.seiyuu-img img')->attr('src') ?: $el->filter('.seiyuu-img img')->attr('data-src'))
                        : null;
                    $seiyuuImg    = $seiyuuImgRaw ? preg_replace('/\/thumb\/\d+x\d+\//', '/', $seiyuuImgRaw) : null;

                    $characters[] = compact('charName', 'charLink', 'charImg', 'charType', 'seiyuuName', 'seiyuuLink', 'seiyuuImg');
                });
            } catch (\Exception $e) {
                // Gagal fetch karakter, lanjutkan
            }

            return response()->json([
                'query'  => $query,
                'filter' => $filter,
                'anime'  => compact('title', 'link', 'img', 'sinopsis', 'tipe', 'eps', 'musim', 'skor', 'characterLink', 'characters'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal mengambil data', 'detail' => $e->getMessage()], 500);
        }
    }
}