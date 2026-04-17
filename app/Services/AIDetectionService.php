<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use App\Exceptions\RateLimitException;
use Exception;

class AIDetectionService
{
    const PROMPT_VERSION = 'v2';

    const MODEL_GEMINI = 'gemini-2.5-flash';

    const PROVIDERS = ['xai', 'groq', 'deepseek'];

    protected int $maxRetries;
    protected int $maxTokens;
    protected int $contentCap;

    protected array $providerConfig = [
        'xai' => [
            'base_url' => 'https://api.x.ai/v1/chat/completions',
            'model'    => 'grok-3-mini',
            'config_key' => 'analyzer.xai_api_key',
        ],
        'groq' => [
            'base_url' => 'https://api.groq.com/openai/v1/chat/completions',
            'model'    => 'llama-3.3-70b-versatile',
            'config_key' => 'analyzer.groq_api_key',
        ],
        'deepseek' => [
            'base_url' => 'https://api.deepseek.com/v1/chat/completions',
            'model'    => 'deepseek-chat',
            'config_key' => 'analyzer.deepseek_api_key',
        ],
    ];

    public function __construct()
    {
        $this->contentCap = config('analyzer.content_cap_chars', 3000);
        $this->maxRetries = config('analyzer.max_retries', 3);
        $this->maxTokens  = config('analyzer.max_token', 800);
    }
    public function analyzeBatch(array $files): array
    {
        if (empty($files)) return [];

        foreach (self::PROVIDERS as $provider) {
            $apiKey = config($this->providerConfig[$provider]['config_key']);

            if (empty($apiKey)) {
                Log::warning("AIDetectionService: {$provider} key missing, skipping");
                continue;
            }

            if (!$this->checkRateLimit($provider)) {
                Log::warning("AIDetectionService: {$provider} rate limited, trying next");
                continue;
            }

            try {
                $results = $this->callProviderBatch($provider, $files);

                if (!empty($results)) {
                    Log::info("AIDetectionService: analyzeBatch success", [
                        'provider' => $provider,
                        'returned' => count($results),
                        'expected' => count($files),
                    ]);
                    return $results;
                }

                Log::warning("AIDetectionService: {$provider} returned empty, trying next");

            } catch (RateLimitException $e) {
                Log::warning("AIDetectionService: {$provider} 429, trying next provider", [
                    'error' => $e->getMessage(),
                ]);
                continue;

            } catch (Exception $e) {
                Log::error("AIDetectionService: {$provider} failed", [
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        // Sab providers fail — heuristic fallback
        Log::warning('AIDetectionService: All providers failed, returning empty for heuristic fallback', [
            'files_count' => count($files),
        ]);

        return [];
    }

    protected function callProviderBatch(string $provider, array $files): array
    {
        $config  = $this->providerConfig[$provider];
        $apiKey  = config($config['config_key']);
        $model   = $config['model'];
        $baseUrl = $config['base_url'];

        $prompt = $this->buildBatchPrompt($files);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ])
        ->timeout(90)
        ->post($baseUrl, [
            'model'    => $model,
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => 'You are a code forensics expert. Always respond with valid JSON only. No markdown, no explanation outside JSON.',
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens'  => $this->maxTokens * count($files),
            'temperature' => 0.1,
        ]);

        if ($response->status() === 429) {
            throw new RateLimitException("{$provider} rate limit: 429");
        }

        if ($response->failed()) {
            throw new Exception("{$provider} API failed: " . $response->status() . ' ' . substr($response->body(), 0, 200));
        }

        $raw = $response->json()['choices'][0]['message']['content'] ?? '[]';

        Log::info("AIDetectionService: {$provider} raw response received", [
            'length' => strlen($raw),
        ]);

        return $this->parseBatchResponse($raw, $files);
    }

    protected function buildBatchPrompt(array $files): string
    {
        $prompt  = "Analyze these code files. Return ONLY a JSON array — no markdown, no extra text.\n\n";
        $prompt .= "Required format:\n";
        $prompt .= '[{"file_path":"...","classification":"ai"|"human"|"uncertain","confidence_score":0-100,"explanation":"2-3 sentences","signals":{"uniform_naming":bool,"excessive_comments":bool,"has_debug_artifacts":bool,"has_todos_or_wip":bool,"boilerplate_heavy":bool,"inconsistent_style":bool}}]' . "\n\n";
        $prompt .= "Rules:\n";
        $prompt .= "- classification 'ai': uniform style, excessive docblocks, boilerplate phrases\n";
        $prompt .= "- classification 'human': TODO/FIXME, debug lines, commented-out code, informal naming\n";
        $prompt .= "- classification 'uncertain': file under 15 lines, pure config/migration/route file\n\n";
        $prompt .= "FILES TO ANALYZE:\n";
        $prompt .= "---\n";

        foreach ($files as $file) {
            $prompt .= "FILE: " . $file['path'] . "\n";
            $prompt .= substr($file['content'], 0, 1500) . "\n";
            $prompt .= "---\n";
        }

        return $prompt;
    }

    protected function parseBatchResponse(string $raw, array $files): array
    {
        // Markdown code blocks hata do agar provider ne wrap kiya
        $cleaned = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $raw);
        $cleaned = trim($cleaned);

        // JSON array extract karo
        preg_match('/\[.*\]/s', $cleaned, $matches);
        $jsonString = $matches[0] ?? '[]';

        $decoded = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            Log::warning('AIDetectionService: Batch JSON parse failed', [
                'raw_preview' => substr($raw, 0, 300),
                'json_error'  => json_last_error_msg(),
            ]);
            return [];
        }

        // Validate har result — incomplete results filter karo
        $valid = [];
        foreach ($decoded as $item) {
            if (
                isset($item['file_path'], $item['classification'], $item['confidence_score']) &&
                in_array($item['classification'], ['ai', 'human', 'uncertain'])
            ) {
                $valid[] = $item;
            }
        }

        if (count($valid) < count($decoded)) {
            Log::warning('AIDetectionService: Some results filtered (invalid format)', [
                'total'   => count($decoded),
                'valid'   => count($valid),
                'invalid' => count($decoded) - count($valid),
            ]);
        }

        return $valid;
    }

    public function analyzeFile(string $filePath, string $content, string $mode = 'bulk'): array
    {
        if ($mode === 'heuristic_only') {
            return $this->buildHeuristicResult('uncertain', $this->getHeuristicScore($content), $content);
        }

        $cacheKey = 'ai_result_' . self::PROMPT_VERSION . '_' . md5($filePath . substr($content, 0, 500));

        return Cache::remember($cacheKey, 7200, function () use ($filePath, $content, $mode) {

            $heuristicScore = $this->getHeuristicScore($content);

            if ($heuristicScore <= 15) return $this->buildHeuristicResult('human', $heuristicScore, $content);
            if ($heuristicScore >= 85) return $this->buildHeuristicResult('ai', $heuristicScore, $content);

            if (!$this->checkRateLimit('gemini')) {
                Log::warning('AIDetectionService: Gemini rate limit hit, using heuristic fallback');
                return $this->buildHeuristicResult('uncertain', $heuristicScore, $content);
            }

            return $this->callWithRetry($filePath, $content, $heuristicScore, self::MODEL_GEMINI);
        });
    }

    protected function checkRateLimit(string $provider): bool
    {
        $limits = [
            'xai'      => config('analyzer.xai_rpm', 30),
            'groq'     => config('analyzer.groq_rpm', 30),
            'deepseek' => config('analyzer.deepseek_rpm', 30),
            'gemini'   => config('services.gemini_rpm', 10),
        ];

        $key      = "llm_rpm_{$provider}";
        $maxCalls = $limits[$provider] ?? 20;

        if (RateLimiter::tooManyAttempts($key, $maxCalls)) {
            return false;
        }

        RateLimiter::hit($key, 60);
        return true;
    }

    protected function callWithRetry(string $filePath, string $content, int $heuristicScore, string $model): array
    {
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            $attempt++;
            try {
                $result = $this->callLLM($filePath, $content, $heuristicScore, $model);
                $this->trackUsage($model, $content);
                return $result;
            } catch (RateLimitException $e) {
                $wait = pow(2, $attempt);
                Log::warning("Gemini rate limited. Attempt {$attempt}. Waiting {$wait}s");
                sleep($wait);
            } catch (Exception $e) {
                Log::error("LLM call failed attempt {$attempt}", ['error' => $e->getMessage()]);
                if ($attempt >= $this->maxRetries) {
                    return $this->buildHeuristicResult('uncertain', $heuristicScore, $content);
                }
                sleep(2);
            }
        }

        return $this->buildHeuristicResult('uncertain', $heuristicScore, $content);
    }

    protected function callLLM(string $filePath, string $content, int $heuristicScore, string $model): array
    {
        $prompt  = $this->buildPrompt($filePath, $content, $heuristicScore);
        $apiKey  = config('services.gemini.key');
        $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
        $url     = "{$baseUrl}{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(90)->post($url, [
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'maxOutputTokens'  => $this->maxTokens,
                'temperature'      => 0.1,
            ],
        ]);

        if (in_array($response->status(), [429, 529])) {
            throw new RateLimitException("Gemini rate limit: " . $response->status());
        }

        if ($response->failed()) {
            throw new Exception("Gemini API failed: " . $response->status());
        }

        $rawText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

        Log::info('Gemini API Call Successful', ['file' => $filePath, 'model' => $model]);

        return $this->parseResponse($rawText, $heuristicScore);
    }

    public function getHeuristicScore(string $content): int
    {
        $score = 50;

        if (preg_match('/\b(TODO|FIXME|HACK|WTF|XXX|TEMP)\b/i', $content))   $score -= 20;
        if (preg_match('/console\.log\(|var_dump\(|dd\(|dump\(/i', $content)) $score -= 15;
        if (preg_match('/\/\/\s*(old|remove|temp|debug)/i', $content))        $score -= 10;
        if (preg_match('/^\s*\/\/.*[;{}]\s*$/m', $content))                   $score -= 8;

        $lines        = max(substr_count($content, "\n"), 1);
        $comments     = substr_count($content, '//') + substr_count($content, '*');
        $commentRatio = $comments / $lines;

        if ($commentRatio > 0.5)                                                           $score += 20;
        if (preg_match_all('/@param|@return|@throws/', $content, $m) && count($m[0]) > 3) $score += 15;
        if (preg_match('/This (method|function|class) (is responsible|handles)/i', $content)) $score += 12;
        if (preg_match('/Initializes (the|this)|Retrieves (the|all)/i', $content))        $score += 10;

        return max(0, min(100, $score));
    }

    protected function buildHeuristicResult(string $classification, int $score, string $content): array
    {
        $explanations = [
            'ai'        => 'Heuristic detected AI patterns: excessive comments or boilerplate structure.',
            'human'     => 'Heuristic detected human patterns: debug artifacts or TODO markers found.',
            'uncertain' => 'File too short or generic to determine origin confidently.',
        ];

        return [
            'classification'   => $classification,
            'confidence_score' => $classification === 'uncertain' ? 50 : $score,
            'explanation'      => $explanations[$classification],
            'signals'          => $this->extractSignals($content),
            'suggestion'       => '',
            'prompt_version'   => self::PROMPT_VERSION,
            'source'           => 'heuristic',
            'raw_llm_response' => null,
        ];
    }

    protected function buildFallbackResult(int $heuristicScore): array
    {
        return [
            'classification'   => 'uncertain',
            'confidence_score' => $heuristicScore,
            'explanation'      => 'Analysis incomplete. Heuristic score used as fallback.',
            'signals'          => $this->emptySignals(),
            'suggestion'       => '',
            'prompt_version'   => self::PROMPT_VERSION,
            'source'           => 'fallback',
            'raw_llm_response' => null,
        ];
    }

    protected function buildPrompt(string $filePath, string $content, int $heuristicScore): string
    {
        $snippet   = substr($content, 0, $this->contentCap);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $lineCount = substr_count($snippet, "\n");
        $version   = self::PROMPT_VERSION;

        return <<<PROMPT
You are an expert code forensics analyst. Determine if this code was written by a human developer or generated by an AI tool (ChatGPT, Copilot, Claude, etc).

File: {$filePath}
Language: {$extension}
Lines shown: {$lineCount}
Heuristic pre-score (0=human, 100=AI): {$heuristicScore}
Prompt version: {$version}

```{$extension}
{$snippet}
```

Respond ONLY with valid JSON. No markdown, no extra text, do not include any preamble or postamble.

{
  "classification": "ai" | "human" | "uncertain",
  "confidence": <integer 0-100>,
  "explanation": "<2-3 sentences>",
  "signals": {
    "uniform_naming": true|false,
    "excessive_comments": true|false,
    "has_debug_artifacts": true|false,
    "has_todos_or_wip": true|false,
    "boilerplate_heavy": true|false,
    "inconsistent_style": true|false
  },
  "suggestion": "<1 sentence if AI-generated, else empty string>"
}
PROMPT;
    }

    protected function parseResponse(string $rawText, int $heuristicScore): array
    {
        preg_match('/\{.*\}/s', $rawText, $matches);
        $jsonString = $matches[0] ?? '{}';
        $decoded    = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($decoded)) {
            Log::warning('LLM JSON parse failed', ['raw' => substr($rawText, 0, 200)]);
            return $this->buildFallbackResult($heuristicScore);
        }

        $classification = $decoded['classification'] ?? 'uncertain';
        if (!in_array($classification, ['ai', 'human', 'uncertain'])) {
            $classification = 'uncertain';
        }

        $confidence = max(0, min(100, (int)($decoded['confidence'] ?? $heuristicScore)));

        return [
            'classification'   => $classification,
            'confidence_score' => $confidence,
            'explanation'      => $decoded['explanation'] ?? 'Analysis completed.',
            'signals'          => $decoded['signals'] ?? $this->emptySignals(),
            'suggestion'       => $decoded['suggestion'] ?? '',
            'prompt_version'   => self::PROMPT_VERSION,
            'source'           => 'llm',
            'raw_llm_response' => $rawText,
        ];
    }

    protected function trackUsage(string $model, string $content): void
    {
        $today   = now()->format('Y-m-d');
        $key     = "llm_usage_{$today}";
        $current = Cache::get($key, ['calls' => 0, 'estimated_chars' => 0]);
        $current['calls']++;
        $current['estimated_chars'] += strlen($content);
        Cache::put($key, $current, 86400);
    }

    protected function extractSignals(string $content): array
    {
        return [
            'uniform_naming'      => false,
            'excessive_comments'  => substr_count($content, '//') / max(substr_count($content, "\n"), 1) > 0.5,
            'has_debug_artifacts' => (bool) preg_match('/console\.log|var_dump|dd\(/i', $content),
            'has_todos_or_wip'    => (bool) preg_match('/TODO|FIXME|HACK/i', $content),
            'boilerplate_heavy'   => (bool) preg_match('/is responsible for|Retrieves all/i', $content),
            'inconsistent_style'  => false,
        ];
    }

    protected function emptySignals(): array
    {
        return [
            'uniform_naming'      => false,
            'excessive_comments'  => false,
            'has_debug_artifacts' => false,
            'has_todos_or_wip'    => false,
            'boilerplate_heavy'   => false,
            'inconsistent_style'  => false,
        ];
    }
}
