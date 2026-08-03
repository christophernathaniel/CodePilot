<?php

namespace Database\Seeders;

use App\Models\Folder;
use App\Models\Framework;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\Tag;
use App\Models\User;
use App\Support\Snippets\FrameworkCatalog;
use App\Support\Snippets\SnippetLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequestsBundleSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()
            ->where('email', 'dev@dev.dev')
            ->firstOrFail();

        DB::transaction(function () use ($user): void {
            FrameworkCatalog::seedFor($user);

            $project = Project::query()->withTrashed()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => 'Requests',
                ],
                [
                    'kind' => Project::KIND_BUNDLE,
                    'description' => 'Copy-ready, defensive HTTP request recipes for PHP, Fetch, WordPress, AJAX, JSON, and XML.',
                    'position' => 9,
                ],
            );
            $project->restore();
            $project->update([
                'kind' => Project::KIND_BUNDLE,
                'description' => 'Copy-ready, defensive HTTP request recipes for PHP, Fetch, WordPress, AJAX, JSON, and XML.',
            ]);

            $tags = $this->tags($user);
            $frameworks = $user->frameworks()
                ->whereIn('slug', ['wordpress'])
                ->get()
                ->keyBy('slug')
                ->all();

            $project->frameworks()->sync([$frameworks['wordpress']->id]);

            $folders = collect([
                'php-curl' => ['name' => 'PHP cURL', 'position' => 0],
                'fetch' => ['name' => 'JavaScript & TypeScript Fetch', 'position' => 1],
                'wordpress' => ['name' => 'WordPress HTTP & AJAX', 'position' => 2],
                'xml' => ['name' => 'XML', 'position' => 3],
            ])->mapWithKeys(function (array $definition, string $key) use ($project): array {
                $folder = Folder::query()->updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'parent_id' => null,
                        'name' => $definition['name'],
                    ],
                    ['position' => $definition['position']],
                );

                return [$key => $folder];
            })->all();

            foreach ($this->files() as $file) {
                $this->seedSnippet(
                    project: $project,
                    folder: $folders[$file['folder']],
                    user: $user,
                    file: $file,
                    tags: $tags,
                    frameworks: $frameworks,
                );
            }
        });
    }

    /** @return array<string, Tag> */
    private function tags(User $user): array
    {
        $definitions = [
            'requests' => ['Requests', '#60a5fa'],
            'http' => ['HTTP', '#38bdf8'],
            'api' => ['API', '#0ea5e9'],
            'php' => ['PHP', '#818cf8'],
            'curl' => ['cURL', '#64748b'],
            'javascript' => ['JavaScript', '#7dd3fc'],
            'typescript' => ['TypeScript', '#93c5fd'],
            'fetch-api' => ['Fetch API', '#22d3ee'],
            'wordpress' => ['WordPress', '#60a5fa'],
            'ajax' => ['AJAX', '#38bdf8'],
            'json' => ['JSON', '#67e8f9'],
            'xml' => ['XML', '#a5b4fc'],
            'security' => ['Security', '#94a3b8'],
            'error-handling' => ['Error handling', '#64748b'],
            'retries' => ['Retries', '#93c5fd'],
            'template-variables' => ['Template Variables', '#c4b5fd'],
        ];

        return collect($definitions)->mapWithKeys(function (array $definition, string $slug) use ($user): array {
            $tag = $user->tags()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $definition[0], 'color' => $definition[1]],
            );

            return [$slug => $tag];
        })->all();
    }

    /**
     * @param  array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string}  $file
     * @param  array<string, Tag>  $tags
     * @param  array<string, Framework>  $frameworks
     */
    private function seedSnippet(
        Project $project,
        Folder $folder,
        User $user,
        array $file,
        array $tags,
        array $frameworks,
    ): void {
        $snippet = Snippet::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'location_key' => SnippetLocation::key($project->id, $folder->id),
                'filename' => $file['filename'],
            ],
            [
                'project_id' => $project->id,
                'folder_id' => $folder->id,
                'content_type' => Snippet::CONTENT_TYPE_SNIPPET,
                'title' => $file['title'],
                'language' => $file['language'],
                'description' => $file['description'],
                'position' => $file['position'],
            ],
        );

        $variation = $snippet->variations()->updateOrCreate(
            ['name' => 'Default'],
            [
                'created_by_id' => $user->id,
                'content' => $file['content'],
                'position' => 0,
                'is_default' => true,
            ],
        );

        $snippet->variations()
            ->where('id', '!=', $variation->id)
            ->update(['is_default' => false]);
        $snippet->tags()->sync(collect($file['tags'])
            ->map(fn (string $slug): int => $tags[$slug]->id)
            ->all());
        $snippet->frameworks()->sync(collect($file['frameworks'])
            ->map(fn (string $slug): int => $frameworks[$slug]->id)
            ->all());
    }

    /**
     * @return list<array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string}>
     */
    private function files(): array
    {
        return [
            $this->phpCurlJson(),
            $this->javascriptFetch(),
            $this->typescriptFetch(),
            $this->wordpressHttp(),
            $this->wordpressAjaxHandler(),
            $this->wordpressAjaxClient(),
            $this->xmlRequests(),
            $this->javascriptXml(),
        ];
    }

    /** @return array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string} */
    private function phpCurlJson(): array
    {
        return [
            'folder' => 'php-curl',
            'title' => 'PHP cURL JSON Requests',
            'filename' => 'curl-json.php',
            'language' => 'php',
            'description' => 'JSON GET and POST requests with bearer authentication, timeouts, status checks, and retry rules for idempotent reads.',
            'position' => 0,
            'tags' => ['requests', 'http', 'api', 'php', 'curl', 'json', 'security', 'error-handling', 'retries', 'template-variables'],
            'frameworks' => [],
            'content' => <<<'PHP'
<?php

declare(strict_types=1);

// {!# snippet: curl-http-exception #!}
final class CurlHttpException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $statusCode = null)
    {
        parent::__construct($message);
    }
}

// {!# snippet: curl-json-request-core #!}
/** @return array<string, mixed> */
function curlJsonRequest(string $method, string $url, ?array $payload, string $token): array
{
    $handle = curl_init();

    if ($handle === false) {
        throw new RuntimeException('Unable to initialise cURL.');
    }

    $headers = ['Accept: application/json'];

    if ($token !== '') {
        $headers[] = 'Authorization: Bearer '.$token;
    }

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
    ];

    if ($payload !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }

    curl_setopt_array($handle, $options);

    try {
        $body = curl_exec($handle);

        if ($body === false) {
            throw new CurlHttpException(curl_error($handle));
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new CurlHttpException("Request failed with HTTP {$statusCode}.", $statusCode);
        }

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new UnexpectedValueException('Expected a JSON object or array.');
        }

        return $decoded;
    } finally {
        curl_close($handle);
    }
}

// {!# snippet: curl-json-get #!}
$query = http_build_query(['page' => 1, 'limit' => 20], '', '&', PHP_QUERY_RFC3986);
$items = curlJsonRequest(
    'GET',
    '{{{api_base_url:https://api.example.com/v1}}}/items?'.$query,
    null,
    '{{{api_token:replace-at-runtime}}}',
);

// {!# snippet: curl-json-post #!}
$created = curlJsonRequest(
    'POST',
    '{{{api_base_url:https://api.example.com/v1}}}/items',
    ['name' => 'Example item', 'enabled' => true],
    '{{{api_token:replace-at-runtime}}}',
);

// {!# snippet: retry-idempotent-curl-get #!}
/** @return array<string, mixed> */
function curlJsonGetWithRetry(string $url, string $token, int $maximumAttempts = 3): array
{
    for ($attempt = 1; $attempt <= $maximumAttempts; $attempt++) {
        try {
            return curlJsonRequest('GET', $url, null, $token);
        } catch (CurlHttpException $exception) {
            $retryable = $exception->statusCode === null
                || $exception->statusCode === 429
                || $exception->statusCode >= 500;

            if (! $retryable || $attempt === $maximumAttempts) {
                throw $exception;
            }

            usleep((200_000 * (2 ** ($attempt - 1))) + random_int(0, 100_000));
        }
    }

    throw new LogicException('Retry loop exited unexpectedly.');
}
PHP,
        ];
    }

    /** @return array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string} */
    private function javascriptFetch(): array
    {
        return [
            'folder' => 'fetch',
            'title' => 'JavaScript Fetch JSON Requests',
            'filename' => 'fetch-json.js',
            'language' => 'javascript',
            'description' => 'Fetch JSON with query parameters, POST bodies, bearer headers, response validation, AbortController, and guarded GET retries.',
            'position' => 0,
            'tags' => ['requests', 'http', 'api', 'javascript', 'fetch-api', 'json', 'security', 'error-handling', 'retries', 'template-variables'],
            'frameworks' => [],
            'content' => <<<'JAVASCRIPT'
// {!# snippet: fetch-json-response #!}
async function readJsonResponse(response) {
    const contentType = response.headers.get('content-type') ?? '';
    const payload = contentType.includes('application/json')
        ? await response.json()
        : await response.text();

    if (!response.ok) {
        const error = new Error(`Request failed with HTTP ${response.status}.`);
        error.status = response.status;
        error.payload = payload;
        throw error;
    }

    return payload;
}

// {!# snippet: fetch-json-get #!}
export async function fetchItems({ signal } = {}) {
    const url = new URL('{{{api_base_url:https://api.example.com/v1}}}/items');
    url.search = new URLSearchParams({ page: '1', limit: '20' }).toString();

    const response = await fetch(url, {
        method: 'GET',
        headers: {
            Accept: 'application/json',
            Authorization: 'Bearer {{{api_token:replace-at-runtime}}}',
        },
        signal,
    });

    return readJsonResponse(response);
}

// {!# snippet: fetch-json-post #!}
export async function createItem(item, { signal } = {}) {
    const response = await fetch('{{{api_base_url:https://api.example.com/v1}}}/items', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            Authorization: 'Bearer {{{api_token:replace-at-runtime}}}',
        },
        body: JSON.stringify(item),
        signal,
    });

    return readJsonResponse(response);
}

// {!# snippet: fetch-timeout-abort-controller #!}
export async function fetchItemsWithTimeout(timeoutMilliseconds = 10_000) {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), timeoutMilliseconds);

    try {
        return await fetchItems({ signal: controller.signal });
    } finally {
        window.clearTimeout(timeout);
    }
}

// {!# snippet: retry-idempotent-fetch-get #!}
export async function fetchItemsWithRetry(maximumAttempts = 3) {
    for (let attempt = 1; attempt <= maximumAttempts; attempt += 1) {
        try {
            return await fetchItemsWithTimeout();
        } catch (error) {
            const retryable =
                error.name === 'TypeError' ||
                error.name === 'AbortError' ||
                error.status === 429 ||
                error.status >= 500;

            if (!retryable || attempt === maximumAttempts) {
                throw error;
            }

            const delay = Math.min(250 * 2 ** (attempt - 1), 2_000);
            await new Promise((resolve) => window.setTimeout(resolve, delay));
        }
    }

    throw new Error('Retry loop exited unexpectedly.');
}
JAVASCRIPT,
        ];
    }

    /** @return array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string} */
    private function typescriptFetch(): array
    {
        return [
            'folder' => 'fetch',
            'title' => 'Typed Fetch Client',
            'filename' => 'fetch-client.ts',
            'language' => 'typescript',
            'description' => 'A typed Fetch wrapper with runtime status handling, JSON decoding, authentication, and caller-controlled cancellation.',
            'position' => 1,
            'tags' => ['requests', 'http', 'api', 'typescript', 'fetch-api', 'json', 'security', 'error-handling', 'template-variables'],
            'frameworks' => [],
            'content' => <<<'TYPESCRIPT'
// {!# snippet: typed-api-error #!}
export class ApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly payload: unknown,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

// {!# snippet: typed-json-request #!}
export async function requestJson<T>(
    path: string,
    init: RequestInit = {},
): Promise<T> {
    const response = await fetch(
        new URL(path, '{{{api_base_url:https://api.example.com/v1/}}}'),
        {
            ...init,
            headers: {
                Accept: 'application/json',
                Authorization: 'Bearer {{{api_token:replace-at-runtime}}}',
                ...init.headers,
            },
        },
    );
    const payload: unknown = await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(
            `Request failed with HTTP ${response.status}.`,
            response.status,
            payload,
        );
    }

    return payload as T;
}

// {!# snippet: typed-fetch-get #!}
type Item = { id: number; name: string; enabled: boolean };

export function getItem(id: number, signal?: AbortSignal): Promise<Item> {
    return requestJson<Item>(`items/${encodeURIComponent(String(id))}`, { signal });
}

// {!# snippet: typed-fetch-post #!}
export function createItem(
    input: Pick<Item, 'name' | 'enabled'>,
    signal?: AbortSignal,
): Promise<Item> {
    return requestJson<Item>('items', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(input),
        signal,
    });
}
TYPESCRIPT,
        ];
    }

    /** @return array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string} */
    private function wordpressHttp(): array
    {
        return [
            'folder' => 'wordpress',
            'title' => 'WordPress HTTP API Requests',
            'filename' => 'wordpress-http.php',
            'language' => 'php',
            'description' => 'WordPress HTTP API GET and POST examples with authentication, trusted URL boundaries, WP_Error handling, and status validation.',
            'position' => 0,
            'tags' => ['requests', 'http', 'api', 'php', 'wordpress', 'json', 'security', 'error-handling', 'template-variables'],
            'frameworks' => ['wordpress'],
            'content' => <<<'PHP'
<?php

declare(strict_types=1);

// {!# snippet: wordpress-http-json-response #!}
/** @return array<string, mixed>|WP_Error */
function requests_decode_wp_response(array|WP_Error $response): array|WP_Error
{
    if (is_wp_error($response)) {
        return $response;
    }

    $statusCode = wp_remote_retrieve_response_code($response);

    if ($statusCode < 200 || $statusCode >= 300) {
        return new WP_Error(
            'requests_http_status',
            sprintf('Remote request failed with HTTP %d.', $statusCode),
            ['status' => $statusCode],
        );
    }

    try {
        $decoded = json_decode(wp_remote_retrieve_body($response), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        return new WP_Error('requests_invalid_json', $exception->getMessage());
    }

    return is_array($decoded)
        ? $decoded
        : new WP_Error('requests_unexpected_json', 'Expected a JSON object or array.');
}

// {!# snippet: wordpress-wp-remote-get #!}
$response = wp_remote_get(
    '{{{api_base_url:https://api.example.com/v1}}}/items?'.http_build_query(['limit' => 20]),
    [
        'timeout' => 15,
        'redirection' => 3,
        'headers' => [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer {{{api_token:replace-at-runtime}}}',
        ],
    ],
);
$items = requests_decode_wp_response($response);

// {!# snippet: wordpress-wp-remote-post #!}
$response = wp_remote_post(
    '{{{api_base_url:https://api.example.com/v1}}}/items',
    [
        'timeout' => 15,
        'redirection' => 0,
        'headers' => [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer {{{api_token:replace-at-runtime}}}',
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode(['name' => 'Example item', 'enabled' => true]),
        'data_format' => 'body',
    ],
);
$created = requests_decode_wp_response($response);

// {!# snippet: wordpress-safe-user-supplied-url #!}
$trustedHosts = ['api.example.com'];
$candidateUrl = esc_url_raw((string) $candidate_url, ['https']);
$candidateHost = wp_parse_url($candidateUrl, PHP_URL_HOST);

if (! is_string($candidateHost) || ! in_array(strtolower($candidateHost), $trustedHosts, true)) {
    return new WP_Error('requests_untrusted_host', 'The remote host is not allowed.');
}

$response = wp_safe_remote_get($candidateUrl, ['timeout' => 10, 'redirection' => 0]);
PHP,
        ];
    }

    /** @return array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string} */
    private function wordpressAjaxHandler(): array
    {
        return [
            'folder' => 'wordpress',
            'title' => 'Nonce-safe WordPress Admin AJAX Handler',
            'filename' => 'admin-ajax.php',
            'language' => 'php',
            'description' => 'Enqueue an admin client and handle authenticated AJAX with a purpose-bound nonce, capability checks, sanitisation, and structured JSON responses.',
            'position' => 1,
            'tags' => ['requests', 'http', 'php', 'wordpress', 'ajax', 'json', 'security', 'error-handling'],
            'frameworks' => ['wordpress'],
            'content' => <<<'PHP'
<?php

declare(strict_types=1);

// {!# snippet: enqueue-wordpress-admin-ajax-client #!}
add_action('admin_enqueue_scripts', static function (string $hookSuffix): void {
    if ($hookSuffix !== 'settings_page_requests-example') {
        return;
    }

    wp_enqueue_script(
        'requests-admin',
        plugins_url('assets/admin-ajax.js', __FILE__),
        [],
        '1.0.0',
        true,
    );
    wp_add_inline_script(
        'requests-admin',
        'window.RequestsAdmin = '.wp_json_encode([
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('requests_save_setting'),
        ]).';',
        'before',
    );
});

// {!# snippet: register-authenticated-admin-ajax-action #!}
add_action('wp_ajax_requests_save_setting', 'requests_save_setting');

// {!# snippet: verify-nonce-capability-and-input #!}
function requests_save_setting(): void
{
    check_ajax_referer('requests_save_setting', 'nonce');

    if (! current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'You are not allowed to change this setting.'], 403);
    }

    $value = isset($_POST['value'])
        ? sanitize_text_field(wp_unslash($_POST['value']))
        : '';

    if ($value === '') {
        wp_send_json_error(['message' => 'A value is required.'], 422);
    }

    update_option('requests_example_value', $value, false);

    wp_send_json_success([
        'message' => 'Setting saved.',
        'value' => $value,
    ]);
}
PHP,
        ];
    }

    /** @return array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string} */
    private function wordpressAjaxClient(): array
    {
        return [
            'folder' => 'wordpress',
            'title' => 'WordPress Admin AJAX Fetch Client',
            'filename' => 'admin-ajax.js',
            'language' => 'javascript',
            'description' => 'Post to authenticated WordPress admin-ajax.php with the generated nonce, same-origin credentials, timeout cancellation, and JSON errors.',
            'position' => 2,
            'tags' => ['requests', 'http', 'javascript', 'fetch-api', 'wordpress', 'ajax', 'json', 'security', 'error-handling'],
            'frameworks' => ['wordpress'],
            'content' => <<<'JAVASCRIPT'
// {!# snippet: wordpress-admin-ajax-config #!}
const { ajaxUrl, nonce } = window.RequestsAdmin;

// {!# snippet: wordpress-admin-ajax-request #!}
export async function saveRequestSetting(value, { signal } = {}) {
    const body = new URLSearchParams({
        action: 'requests_save_setting',
        nonce,
        value,
    });
    const response = await fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: body.toString(),
        signal,
    });
    const payload = await response.json().catch(() => null);

    if (!response.ok || payload?.success !== true) {
        throw new Error(payload?.data?.message ?? `Request failed with HTTP ${response.status}.`);
    }

    return payload.data;
}

// {!# snippet: wordpress-admin-ajax-abort-timeout #!}
export async function saveRequestSettingWithTimeout(value, timeoutMilliseconds = 10_000) {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), timeoutMilliseconds);

    try {
        return await saveRequestSetting(value, { signal: controller.signal });
    } finally {
        window.clearTimeout(timeout);
    }
}

// {!# snippet: bind-accessible-ajax-form #!}
document.querySelector('[data-requests-form]')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const status = form.querySelector('[role="status"]');
    const value = new FormData(form).get('value');

    try {
        const result = await saveRequestSettingWithTimeout(String(value ?? ''));
        status.textContent = result.message;
    } catch (error) {
        status.textContent = error instanceof Error ? error.message : 'The request failed.';
    }
});
JAVASCRIPT,
        ];
    }

    /** @return array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string} */
    private function xmlRequests(): array
    {
        return [
            'folder' => 'xml',
            'title' => 'Safe XML GET, POST and Parsing',
            'filename' => 'xml-requests.php',
            'language' => 'php',
            'description' => 'Build, send, and safely parse XML with cURL, authentication, bounded payloads, status checks, and external-network loading disabled.',
            'position' => 0,
            'tags' => ['requests', 'http', 'api', 'php', 'curl', 'xml', 'security', 'error-handling', 'template-variables'],
            'frameworks' => [],
            'content' => <<<'PHP'
<?php

declare(strict_types=1);

// {!# snippet: build-xml-request-body #!}
function buildItemXml(string $name, bool $enabled): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $item = $document->appendChild($document->createElement('item'));
    $item->appendChild($document->createElement('name'))->appendChild($document->createTextNode($name));
    $item->appendChild($document->createElement('enabled', $enabled ? 'true' : 'false'));

    return $document->saveXML() ?: throw new RuntimeException('Unable to serialise XML.');
}

// {!# snippet: curl-xml-request-core #!}
function curlXmlRequest(string $method, string $url, ?string $body, string $token): string
{
    $handle = curl_init($url);

    if ($handle === false) {
        throw new RuntimeException('Unable to initialise cURL.');
    }

    $headers = ['Accept: application/xml'];

    if ($token !== '') {
        $headers[] = 'Authorization: Bearer '.$token;
    }

    if ($body !== null) {
        $headers[] = 'Content-Type: application/xml; charset=UTF-8';
    }

    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
    ]);

    try {
        $responseBody = curl_exec($handle);

        if ($responseBody === false) {
            throw new RuntimeException(curl_error($handle));
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException("XML request failed with HTTP {$statusCode}.");
        }

        return $responseBody;
    } finally {
        curl_close($handle);
    }
}

// {!# snippet: curl-xml-get #!}
$xml = curlXmlRequest(
    'GET',
    '{{{xml_api_url:https://api.example.com/v1/items.xml}}}',
    null,
    '{{{api_token:replace-at-runtime}}}',
);

// {!# snippet: curl-xml-post #!}
$xml = curlXmlRequest(
    'POST',
    '{{{xml_api_url:https://api.example.com/v1/items.xml}}}',
    buildItemXml('Example item', true),
    '{{{api_token:replace-at-runtime}}}',
);

// {!# snippet: parse-xml-without-external-network-access #!}
function parseXmlSafely(string $xml, int $maximumBytes = 1_000_000): DOMDocument
{
    if (strlen($xml) > $maximumBytes) {
        throw new LengthException('XML response exceeds the allowed size.');
    }

    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument();

    try {
        if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            $message = libxml_get_last_error()?->message ?? 'Invalid XML response.';
            throw new UnexpectedValueException(trim($message));
        }

        return $document;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

$document = parseXmlSafely($xml);
$names = (new DOMXPath($document))->query('/items/item/name');
PHP,
        ];
    }

    /** @return array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string} */
    private function javascriptXml(): array
    {
        return [
            'folder' => 'xml',
            'title' => 'JavaScript Fetch XML Requests',
            'filename' => 'fetch-xml.js',
            'language' => 'javascript',
            'description' => 'Fetch XML with GET and POST, safe DOM construction, HTTP and parser-error checks, authentication, and AbortController timeouts.',
            'position' => 1,
            'tags' => ['requests', 'http', 'api', 'javascript', 'fetch-api', 'xml', 'security', 'error-handling', 'template-variables'],
            'frameworks' => [],
            'content' => <<<'JAVASCRIPT'
// {!# snippet: parse-fetch-xml-response #!}
function parseXml(xml) {
    const document = new DOMParser().parseFromString(xml, 'application/xml');
    const parserError = document.querySelector('parsererror');

    if (parserError) {
        throw new SyntaxError('The response contained invalid XML.');
    }

    return document;
}

async function readXmlResponse(response) {
    const body = await response.text();

    if (!response.ok) {
        const error = new Error(`XML request failed with HTTP ${response.status}.`);
        error.status = response.status;
        error.body = body;
        throw error;
    }

    return parseXml(body);
}

// {!# snippet: fetch-xml-request-core #!}
export async function requestXml(path, { method = 'GET', body, signal } = {}) {
    const headers = {
        Accept: 'application/xml',
        Authorization: 'Bearer {{{api_token:replace-at-runtime}}}',
    };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/xml; charset=UTF-8';
    }

    const response = await fetch(
        new URL(path, '{{{xml_api_url:https://api.example.com/v1/}}}'),
        { method, headers, body, signal },
    );

    return readXmlResponse(response);
}

// {!# snippet: build-browser-xml-request-body #!}
export function buildItemXml(name, enabled) {
    const xmlDocument = window.document.implementation.createDocument('', 'item');
    const nameElement = xmlDocument.createElement('name');
    const enabledElement = xmlDocument.createElement('enabled');
    nameElement.textContent = name;
    enabledElement.textContent = enabled ? 'true' : 'false';
    xmlDocument.documentElement.append(nameElement, enabledElement);

    return new XMLSerializer().serializeToString(xmlDocument);
}

// {!# snippet: fetch-xml-get #!}
export function getItemsXml(signal) {
    const path = new URL('items.xml', '{{{xml_api_url:https://api.example.com/v1/}}}');
    path.search = new URLSearchParams({ page: '1', limit: '20' }).toString();

    return requestXml(path.toString(), { signal });
}

// {!# snippet: fetch-xml-post #!}
export function createItemXml(item, signal) {
    return requestXml('items.xml', {
        method: 'POST',
        body: buildItemXml(item.name, item.enabled),
        signal,
    });
}

// {!# snippet: fetch-xml-abort-timeout #!}
export async function getItemsXmlWithTimeout(timeoutMilliseconds = 10_000) {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), timeoutMilliseconds);

    try {
        return await getItemsXml(controller.signal);
    } finally {
        window.clearTimeout(timeout);
    }
}
JAVASCRIPT,
        ];
    }
}
