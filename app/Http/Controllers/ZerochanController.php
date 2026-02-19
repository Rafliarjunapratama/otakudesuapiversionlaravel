<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class ZerochanController extends Controller
{
    private string $userAgent = 'Mozilla/5.0';

    /**
     * GET /api/zerochan/search?q=...
     * Cari gambar di Zerochan berdasarkan query
     */
    public function search(Request $request)
    {
        $query = $request->query('q', 'anime');
        $url   = 'https://www.zerochan.net/search?q=' . urlencode($query);

        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent])
                ->timeout(15)
                ->get($url);

            $crawler = new Crawler($response->body());
            $data    = [];

            $crawler->filter('#thumbs2 li')->each(function (Crawler $el) use (&$data) {
                $titleEl = $el->filter('p a');
                $imgEl   = $el->filter('.thumb img');
                $favEl   = $el->filter('.fav b');

                $title     = $titleEl->count() ? trim($titleEl->text('')) : '';
                $href      = $titleEl->count() ? $titleEl->attr('href') : '';
                $link      = $href ? 'https://www.zerochan.net' . $href : '';
                $thumbnail = $imgEl->count()
                    ? ($imgEl->attr('data-src') ?: $imgEl->attr('src'))
                    : '';
                $fav = $favEl->count() ? (int) $favEl->text('0') : 0;

                $data[] = compact('title', 'link', 'thumbnail', 'fav');
            });

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal scraping Zerochan', 'detail' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/zerochan/characters?q=...
     * Ambil daftar karakter dari halaman Zerochan
     *
     * CATATAN: Endpoint ini di Node.js menggunakan Puppeteer (headless browser).
     * Di Laravel, kita menggunakan HTTP biasa. Jika halaman memerlukan JavaScript
     * untuk me-render konten, gunakan package seperti "spatie/browsershot" sebagai
     * pengganti Puppeteer, atau pakai API resmi Zerochan bila tersedia.
     */
    public function characters(Request $request)
    {
        $query = $request->query('q', 'Kijin Gentoushou');
        $url   = 'https://www.zerochan.net/' . urlencode($query);

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            ])->get($url);

            $crawler = new Crawler($response->body());
            $data    = [];

            $crawler->filter('.carousel.thumbs li')->each(function (Crawler $el) use (&$data) {
                $aEl    = $el->filter('a');
                $nameEl = $el->filter('p.character');
                $countEl = $el->filter('i');

                $link  = $aEl->count() ? 'https://www.zerochan.net' . $aEl->attr('href') : '';
                $name  = $nameEl->count() ? trim($nameEl->text('')) : '';

                // Ambil thumbnail dari style background-image atau data-src
                $thumbnail = '';
                $imgEl     = $el->filter('.thumb');
                if ($imgEl->count()) {
                    $thumbnail = $imgEl->attr('data-src') ?? '';
                    if (!$thumbnail) {
                        $style = $imgEl->attr('style') ?? '';
                        preg_match('/url\(["\']?(.*?)["\']?\)/', $style, $m);
                        $thumbnail = $m[1] ?? '';
                    }
                }

                $entries = $countEl->count() ? (int) $countEl->text('0') : 0;

                $data[] = compact('name', 'link', 'thumbnail', 'entries');
            });

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal scraping characters', 'detail' => $e->getMessage()], 500);
        }
    }
}