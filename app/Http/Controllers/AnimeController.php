<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class AnimeController extends Controller
{
    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36';

    /**
     * GET /api/anime
     * Ambil daftar anime ongoing (5 halaman)
     */
    public function ongoing()
    {
        try {
            $data = [];
            $totalPages = 5;

            for ($page = 1; $page <= $totalPages; $page++) {
                $url = "https://otakudesu.best/ongoing-anime/page/{$page}/";
                $html = Http::withHeaders(['User-Agent' => $this->userAgent])
                    ->get($url)
                    ->body();

                $crawler = new Crawler($html);

                $crawler->filter('li .detpost')->each(function (Crawler $el) use (&$data) {
                    $rawJudul = trim($el->filter('.thumb h2.jdlflm')->text(''));
                    $judul = preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-Z0-9\s]/', '', $rawJudul));

                    $data[] = [
                        'episode'   => trim($el->filter('.epz')->text('')),
                        'hari'      => trim($el->filter('.epztipe')->text('')),
                        'tanggal'   => trim($el->filter('.newnime')->text('')),
                        'link'      => $el->filter('.thumb a')->attr('href'),
                        'thumbnail' => $el->filter('.thumb img')->attr('src'),
                        'judul'     => trim($judul),
                    ];
                });
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal scraping', 'detail' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/anime/complete
     * Ambil daftar anime complete (halaman 1)
     */
    public function complete()
    {
        try {
            $url  = 'https://otakudesu.best/complete-anime/';
            $html = Http::withHeaders(['User-Agent' => $this->userAgent])
                ->get($url)
                ->body();

            $crawler = new Crawler($html);
            $data    = [];

            $crawler->filter('li .detpost')->each(function (Crawler $el) use (&$data) {
                $rawJudul = trim($el->filter('.thumb h2.jdlflm')->text(''));
                $judul = preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-Z0-9\s]/', '', $rawJudul));

                $data[] = [
                    'episode'   => trim($el->filter('.epz')->text('')),
                    'rating'    => trim($el->filter('.epztipe')->text('')),
                    'tanggal'   => trim($el->filter('.newnime')->text('')),
                    'link'      => $el->filter('.thumb a')->attr('href'),
                    'thumbnail' => $el->filter('.thumb img')->attr('src'),
                    'judul'     => trim($judul),
                ];
            });

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal scraping complete anime', 'detail' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/anime/complete/page/{page}
     * Ambil daftar anime complete berdasarkan halaman
     */
    public function completePage(int $page)
    {
        try {
            $url      = "https://otakudesu.best/complete-anime/page/{$page}/";
            $response = Http::withHeaders(['User-Agent' => $this->userAgent])->get($url);

            if (!$response->successful()) {
                throw new \Exception("Fetch gagal, status {$response->status()}");
            }

            $crawler = new Crawler($response->body());
            $data    = [];

            $crawler->filter('li .detpost')->each(function (Crawler $el) use (&$data) {
                $rawTitle = trim($el->filter('.thumb h2.jdlflm')->text(''));
                $judul = preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-Z0-9\s]/', '', $rawTitle));

                $data[] = [
                    'episode'   => trim($el->filter('.epz')->text('')),
                    'hari'      => trim($el->filter('.epztipe')->text('')),
                    'tanggal'   => trim($el->filter('.newnime')->text('')),
                    'link'      => $el->filter('.thumb a')->attr('href'),
                    'thumbnail' => $el->filter('.thumb img')->attr('src'),
                    'judul'     => trim($judul),
                ];
            });

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal scraping complete anime', 'detail' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/anime/detail?link=...
     * Ambil detail anime berdasarkan link
     */
    public function detail(Request $request)
    {
        $link = $request->query('link');
        if (!$link) {
            return response()->json(['error' => 'Missing link'], 400);
        }

        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent])->get($link);
            if (!$response->successful()) {
                throw new \Exception("Fetch gagal {$response->status()}");
            }

            $crawler = new Crawler($response->body());

            // Judul
            $judul = $crawler->filter('.jdlrx')->count()
                ? trim($crawler->filter('.jdlrx')->text(''))
                : '';

            // Thumbnail
            $thumbnail = '';
            foreach (['.fotoanime img' => ['src', 'data-src'], '.thumb img' => ['src', 'data-src']] as $sel => $attrs) {
                if ($crawler->filter($sel)->count()) {
                    foreach ($attrs as $attr) {
                        $val = $crawler->filter($sel)->attr($attr);
                        if ($val) { $thumbnail = $val; break 2; }
                    }
                }
            }
            // Hapus dimensi dari URL thumbnail
            $thumbnail = preg_replace('/-\d+x\d+(?=\.(jpg|jpeg|png))/i', '', $thumbnail);

            // Sinopsis
            $sinopsis = $crawler->filter('.sinopc')->count()
                ? trim($crawler->filter('.sinopc')->text(''))
                : '';

            // Info tambahan
            $infoEpisode = '';
            $hari        = '';
            $tanggal     = '';

            $crawler->filter('.infozingle p')->each(function (Crawler $p) use (&$infoEpisode, &$hari, &$tanggal) {
                $text = $p->text('');
                if (str_contains($text, 'Episode'))  $infoEpisode = trim(str_replace('Episode:', '', $text));
                if (str_contains($text, 'Hari'))     $hari        = trim(str_replace('Hari:', '', $text));
                if (str_contains($text, 'Tanggal'))  $tanggal     = trim(str_replace('Tanggal:', '', $text));
            });

            // Daftar episode
            $episodeList = [];
            $crawler->filter('.episodelist ul li')->each(function (Crawler $el) use (&$episodeList, $judul) {
                if (!$el->filter('a')->count()) return;

                $aTag   = $el->filter('a');
                $linkEp = $aTag->attr('href');

                if (!$linkEp || str_contains($linkEp, '/batch/') || str_contains($linkEp, '/lengkap/')) return;

                $title = trim($aTag->text(''));
                $tglEp = $el->filter('.zeebr')->count() ? trim($el->filter('.zeebr')->text('')) : '';

                // Bersihkan judul dari nama anime
                if ($judul) {
                    $title = trim(preg_replace('/' . preg_quote($judul, '/') . '/i', '', $title));
                }
                $title = trim(preg_replace('/Subtitle Indonesia/i', '', $title));

                // Ambil "Episode X" saja
                if (preg_match('/Episode\s*\d+/i', $title, $m)) {
                    $title = $m[0];
                }

                $episodeList[] = ['title' => $title, 'tanggal' => $tglEp, 'link' => $linkEp];
            });

            return response()->json([
                'judul'     => $judul,
                'thumbnail' => $thumbnail,
                'sinopsis'  => $sinopsis,
                'episode'   => $episodeList,
                'info'      => ['episode' => $infoEpisode, 'hari' => $hari, 'tanggal' => $tanggal, 'link' => $link],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal mengambil data anime', 'detail' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/anime/detail/video?link=...
     * Ambil URL video (iframe src) dari halaman episode
     */
    public function detailVideo(Request $request)
    {
        $link = $request->query('link');
        if (!$link) {
            return response()->json(['error' => 'Missing link'], 400);
        }

        try {
            $html    = Http::withHeaders(['User-Agent' => $this->userAgent])->get($link)->body();
            $crawler = new Crawler($html);

            $iframeSrc = $crawler->filter('iframe')->count()
                ? $crawler->filter('iframe')->attr('src')
                : null;

            if (!$iframeSrc) {
                return response()->json(['error' => 'Video iframe not found'], 404);
            }

            return response()->json(['video' => $iframeSrc]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong', 'detail' => $e->getMessage()], 500);
        }
    }
}