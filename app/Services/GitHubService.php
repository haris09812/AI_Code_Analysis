<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\PendingRequest;
use Firebase\JWT\JWT;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class GitHubService
{
    protected string $baseUrl = 'https://api.github.com';
    protected string $appId;
    protected string $installationId;
    protected string $privateKeyPath;

    public function __construct()
    {
        $this->appId = config('services.github.app_id');
        $this->installationId = config('services.github.installation_id');
        $this->privateKeyPath = storage_path('app/github-private-key.pem');
    }

    /**
     * GitHub App ke liye temporary Installation Access Token mangwana ya Cache se nikalna.
     */
    protected function getInstallationToken(): string
    {
        return Cache::remember('github_app_token', 3500, function () {
            if (!file_exists($this->privateKeyPath)) {
                throw new Exception("GitHub Private Key file missing at: {$this->privateKeyPath}");
            }

            $payload = [
                'iat' => time() - 10,
                'exp' => time() + 60,
                'iss' => $this->appId,
            ];

            $privateKey = file_get_contents($this->privateKeyPath);
            $jwt = JWT::encode($payload, $privateKey, 'RS256');

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$jwt}",
                'Accept'        => 'application/vnd.github+json',
                'User-Agent'    => 'Laravel-App',
            ])->post("{$this->baseUrl}/app/installations/{$this->installationId}/access_tokens");

            if ($response->failed()) {
                throw new Exception("GitHub Auth Failed: " . $response->body());
            }

            return $response->json()['token'];
        });
    }

    /**
     * Shared headers using GitHub App Token.
     */
    protected function clientHeaders(): array
    {
        $headers = [
            'Accept'        => 'application/vnd.github+json',
            'User-Agent'    => 'Laravel-App',
        ];

        try {
            $token = $this->getInstallationToken();
            if ($token) {
                $headers['Authorization'] = "token " . $token;
            }
        } catch (\Exception $e) {
            // Agar App install nahi hai ya private key missing hai,
            // toh hum anonymous request bhejenge (Rate limit low hogi: 60/hr)
            Log::info("GitHub App Fallback: " . $e->getMessage());
        }

        return $headers;
    }

    /**
     * URL se Owner aur Repo nikalna.
     */
    public function parseRepoUrl(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        $segments = explode('/', trim($path, '/'));

        if (count($segments) < 2 || empty($segments[0]) || empty($segments[1])) {
            throw new Exception("Invalid GitHub URL. Owner or Repository name is missing.");
        }

        return [$segments[0], $segments[1]];
    }


    /**
     * Complete Metadata fetch karna (Parallel requests ke sath).
     */
    public function getRepoMetadata(string $url): array
    {
        [$owner, $repo] = $this->parseRepoUrl($url);
        $cacheKey = "repo_full_meta_{$owner}_{$repo}";

        return Cache::remember($cacheKey, 3600, function () use ($owner, $repo) {
            $headers = $this->clientHeaders();

            // HTTP Pool: Saari API calls aik sath jayengi
            $responses = Http::pool(fn ($pool) => [
                $pool->as('main')->withHeaders($headers)->get("{$this->baseUrl}/repos/{$owner}/{$repo}"),
                $pool->as('langs')->withHeaders($headers)->get("{$this->baseUrl}/repos/{$owner}/{$repo}/languages"),
                $pool->as('commits')->withHeaders($headers)->get("{$this->baseUrl}/repos/{$owner}/{$repo}/commits", ['per_page' => 5]),
                $pool->as('contributors')->withHeaders($headers)->get("{$this->baseUrl}/repos/{$owner}/{$repo}/contributors", ['per_page' => 10]),
                $pool->as('pr_open')->withHeaders($headers)->get("{$this->baseUrl}/search/issues", ['q' => "repo:{$owner}/{$repo} type:pr state:open"]),
                $pool->as('pr_merged')->withHeaders($headers)->get("{$this->baseUrl}/search/issues", ['q' => "repo:{$owner}/{$repo} type:pr is:merged"]),
            ]);


            if ($responses['main']->status() === 404) {
                throw new Exception("Repository nahi mila. Agar ye private hai toh App install karna zaroori hai.");
            }

            if ($responses['main']->failed()) {
                // throw new Exception("GitHub API failed for {$owner}/{$repo}");
                throw new Exception("GitHub API failed for {$owner}/{$repo}: " . $responses['main']->status());
            }

            $data = $responses['main']->json();

            return [
                'identity' => [
                    'name'           => $data['name'],
                    'full_name'      => $data['full_name'],
                    'description'    => $data['description'],
                    'url'            => $data['html_url'],
                ],
                'stats' => [
                    'stars'          => $data['stargazers_count'],
                    'forks'          => $data['forks_count'],
                    'size_kb'        => $data['size'],
                    'primary_lang'   => $data['language'],
                    'languages'      => $responses['langs']->json() ?? [],
                ],
                'history' => [
                    'created_at'     => $data['created_at'],
                    'repo_age'       => Carbon::parse($data['created_at'])->diffForHumans(),
                    'default_branch' => $data['default_branch'],
                ],
                'pull_requests' => [
                    'open'   => $responses['pr_open']->successful() ? ($responses['pr_open']->json()['total_count'] ?? 0) : 0,
                    'merged' => $responses['pr_merged']->successful() ? ($responses['pr_merged']->json()['total_count'] ?? 0) : 0,
                ],
                'contributors' => collect($responses['contributors']->json() ?? [])->map(fn($u) => [
                    'name'       => $u['login'] ?? 'Unknown',
                    'avatar'     => $u['avatar_url'] ?? '',
                    'profile'    => $u['html_url'] ?? '',
                    'commits'    => $u['contributions'] ?? 0
                ])->toArray(),
                'recent_commits' => collect($responses['commits']->json() ?? [])->map(fn($c) => [
                    'message'    => $c['commit']['message'],
                    'author'     => $c['commit']['author']['name'],
                    'date'       => Carbon::parse($c['commit']['author']['date'])->diffForHumans(),
                    'url'        => $c['html_url']
                ])->toArray(),
            ];
        });
    }
}
