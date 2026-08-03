<?php

namespace Database\Seeders;

use App\Models\Folder;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\Tag;
use App\Models\User;
use App\Support\Snippets\FrameworkCatalog;
use App\Support\Snippets\SnippetLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassesBundleSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', 'dev@dev.dev')->firstOrFail();

        DB::transaction(function () use ($user): void {
            FrameworkCatalog::seedFor($user);

            $project = Project::query()->withTrashed()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => 'CLASSES',
                ],
                [
                    'kind' => Project::KIND_BUNDLE,
                    'description' => 'Copy-ready object-oriented class patterns for PHP, JavaScript, TypeScript, and WordPress.',
                    'position' => 8,
                ],
            );
            $project->restore();
            $project->update([
                'kind' => Project::KIND_BUNDLE,
                'description' => 'Copy-ready object-oriented class patterns for PHP, JavaScript, TypeScript, and WordPress.',
            ]);
            $folders = $this->folders($project);
            $tags = $this->tags($user);
            $frameworkIds = $user->frameworks()->pluck('id', 'slug');

            $project->frameworks()->sync([
                $frameworkIds->get('wordpress'),
            ]);

            foreach ($this->files() as $file) {
                $folder = $folders[$file['folder']];
                $snippet = Snippet::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'project_id' => $project->id,
                        'filename' => $file['filename'],
                    ],
                    [
                        'folder_id' => $folder->id,
                        'location_key' => SnippetLocation::key($project->id, $folder->id),
                        'title' => $file['title'],
                        'content_type' => Snippet::CONTENT_TYPE_SNIPPET,
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
                $snippet->tags()->sync(
                    collect($file['tags'])
                        ->map(fn (string $slug): int => $tags[$slug]->id)
                        ->all(),
                );
                $snippet->frameworks()->sync(
                    collect($file['frameworks'])
                        ->map(fn (string $slug): int => (int) $frameworkIds->get($slug))
                        ->all(),
                );
            }
        });
    }

    /** @return array<string, Folder> */
    private function folders(Project $project): array
    {
        $folders = [];

        foreach (['PHP', 'JavaScript', 'TypeScript', 'WordPress'] as $position => $name) {
            $folders[$name] = $project->folders()->updateOrCreate(
                ['parent_id' => null, 'name' => $name],
                ['position' => $position],
            );
        }

        return $folders;
    }

    /** @return array<string, Tag> */
    private function tags(User $user): array
    {
        $definitions = [
            'classes' => ['Classes', '#93c5fd'],
            'oop' => ['Object-oriented programming', '#64748b'],
            'php' => ['PHP', '#818cf8'],
            'javascript' => ['JavaScript', '#facc15'],
            'typescript' => ['TypeScript', '#60a5fa'],
            'wordpress' => ['WordPress', '#38bdf8'],
            'value-object' => ['Value object', '#a5b4fc'],
            'dto' => ['DTO', '#c4b5fd'],
            'interface' => ['Interface', '#7dd3fc'],
            'service' => ['Service', '#67e8f9'],
            'inheritance' => ['Inheritance', '#94a3b8'],
            'trait' => ['Trait', '#a3a3a3'],
            'private-fields' => ['Private fields', '#fde68a'],
            'factory' => ['Factory', '#86efac'],
            'error-handling' => ['Error handling', '#fca5a5'],
            'generics' => ['Generics', '#7dd3fc'],
            'repository' => ['Repository', '#5eead4'],
            'abstract-class' => ['Abstract class', '#c4b5fd'],
            'plugin-development' => ['Plugin development', '#94a3b8'],
            'hooks' => ['Hooks', '#67e8f9'],
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
     * @return list<array{
     *     folder: string,
     *     title: string,
     *     filename: string,
     *     language: string,
     *     description: string,
     *     position: int,
     *     tags: list<string>,
     *     frameworks: list<string>,
     *     content: string
     * }>
     */
    private function files(): array
    {
        return [
            $this->phpValueObjects(),
            $this->phpServices(),
            $this->phpInheritance(),
            $this->javascriptClasses(),
            $this->typescriptRepository(),
            $this->typescriptFactory(),
            $this->wordpressBootstrap(),
            $this->wordpressHookSubscriber(),
        ];
    }

    /** @return array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string} */
    private function phpValueObjects(): array
    {
        return [
            'folder' => 'PHP',
            'title' => 'Value Object and DTO',
            'filename' => 'ValueObjectAndDto.php',
            'language' => 'php',
            'description' => 'Immutable PHP value object and request DTO patterns with validation at construction boundaries.',
            'position' => 0,
            'tags' => ['classes', 'oop', 'php', 'value-object', 'dto'],
            'frameworks' => [],
            'content' => <<<'PHP'
<?php
// {!# snippet: money_value_object #!}
declare(strict_types=1);

namespace App\Domain\Billing;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(
        public int $minorUnits,
        public string $currency,
    ) {
        if ($minorUnits < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency must be an ISO 4217 code.');
        }
    }

    public function add(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currencies must match.');
        }

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }
}

// {!# snippet: create_order_dto #!}

namespace App\Application\Orders;

use InvalidArgumentException;

final readonly class CreateOrderData
{
    /** @param list<positive-int> $productIds */
    public function __construct(
        public int $customerId,
        public array $productIds,
        public string $email,
    ) {
        if ($customerId < 1 || $productIds === []) {
            throw new InvalidArgumentException('An order needs a customer and products.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid email is required.');
        }
    }

    /** @param array{customer_id: int, product_ids: list<int>, email: string} $input */
    public static function fromArray(array $input): self
    {
        return new self(
            customerId: $input['customer_id'],
            productIds: $input['product_ids'],
            email: $input['email'],
        );
    }
}

// {!# snippet: value_object_dto_usage #!}
namespace App\Examples;

use App\Application\Orders\CreateOrderData;
use App\Domain\Billing\Money;

$subtotal = (new Money(2500, 'GBP'))->add(new Money(499, 'GBP'));
$order = CreateOrderData::fromArray([
    'customer_id' => 42,
    'product_ids' => [7, 11],
    'email' => 'customer@example.test',
]);
PHP,
        ];
    }

    /** @return array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string} */
    private function phpServices(): array
    {
        return [
            'folder' => 'PHP',
            'title' => 'Interface and Service',
            'filename' => 'NotifierService.php',
            'language' => 'php',
            'description' => 'A small contract, infrastructure implementation, and service composed through constructor injection.',
            'position' => 1,
            'tags' => ['classes', 'oop', 'php', 'interface', 'service'],
            'frameworks' => [],
            'content' => <<<'PHP'
<?php
// {!# snippet: notifier_interface #!}
declare(strict_types=1);

namespace App\Contracts;

interface Notifier
{
    public function send(string $recipient, string $message): void;
}

// {!# snippet: mail_notifier #!}

namespace App\Infrastructure\Notifications;

use App\Contracts\Notifier;
use RuntimeException;

final class MailNotifier implements Notifier
{
    public function send(string $recipient, string $message): void
    {
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('The notification recipient is invalid.');
        }

        // Hand the validated message to the application's configured mail transport.
    }
}

// {!# snippet: welcome_service #!}

namespace App\Application\Accounts;

use App\Contracts\Notifier;

final readonly class WelcomeService
{
    public function __construct(private Notifier $notifier) {}

    public function welcome(string $email, string $displayName): void
    {
        $message = sprintf('Welcome, %s.', trim($displayName));

        $this->notifier->send($email, $message);
    }
}
PHP,
        ];
    }

    /** @return array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string} */
    private function phpInheritance(): array
    {
        return [
            'folder' => 'PHP',
            'title' => 'Inheritance and Trait',
            'filename' => 'ContentHierarchy.php',
            'language' => 'php',
            'description' => 'A constrained inheritance hierarchy with reusable timestamp behavior composed as a trait.',
            'position' => 2,
            'tags' => ['classes', 'oop', 'php', 'inheritance', 'trait', 'abstract-class'],
            'frameworks' => [],
            'content' => <<<'PHP'
<?php
// {!# snippet: timestamp_trait #!}
declare(strict_types=1);

namespace App\Domain\Content;

use DateTimeImmutable;

trait HasTimestamps
{
    private DateTimeImmutable $updatedAt;

    public function touch(?DateTimeImmutable $at = null): void
    {
        $this->updatedAt = $at ?? new DateTimeImmutable;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

// {!# snippet: abstract_content #!}

namespace App\Domain\Content;

abstract class Content
{
    use HasTimestamps;

    public function __construct(
        protected string $title,
        protected string $body,
    ) {
        $this->touch();
    }

    abstract public function type(): string;

    public function summary(int $length = 120): string
    {
        return mb_strimwidth(strip_tags($this->body), 0, $length, '…');
    }
}

// {!# snippet: article_inheritance #!}

namespace App\Domain\Content;

final class Article extends Content
{
    public function type(): string
    {
        return 'article';
    }
}
PHP,
        ];
    }

    /** @return array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string} */
    private function javascriptClasses(): array
    {
        return [
            'folder' => 'JavaScript',
            'title' => 'Private Fields, Factory, and Custom Error',
            'filename' => 'api-client.js',
            'language' => 'javascript',
            'description' => 'Modern JavaScript classes with private state, typed failure context, and a dependency-aware factory.',
            'position' => 0,
            'tags' => ['classes', 'oop', 'javascript', 'private-fields', 'factory', 'error-handling'],
            'frameworks' => [],
            'content' => <<<'JAVASCRIPT'
// {!# snippet: http_error_class #!}
export class HttpError extends Error {
    constructor(message, { status, url, cause } = {}) {
        super(message, { cause });
        this.name = 'HttpError';
        this.status = status ?? 0;
        this.url = url ?? null;
    }
}

// {!# snippet: api_client_private_fields #!}
export class ApiClient {
    #baseUrl;
    #fetch;

    constructor({ baseUrl, fetchImplementation = globalThis.fetch }) {
        this.#baseUrl = new URL(baseUrl);
        this.#fetch = fetchImplementation;
    }

    async get(path, { signal } = {}) {
        const url = new URL(path, this.#baseUrl);
        const response = await this.#fetch(url, {
            headers: { Accept: 'application/json' },
            signal,
        });

        if (!response.ok) {
            throw new HttpError(`Request failed with ${response.status}.`, {
                status: response.status,
                url: url.toString(),
            });
        }

        return response.json();
    }
}

// {!# snippet: api_client_factory #!}
export function createApiClient(environment = 'production') {
    const origins = {
        development: 'http://localhost:8080/api/',
        production: 'https://api.example.test/',
    };
    const baseUrl = origins[environment];

    if (!baseUrl) {
        throw new RangeError(`Unknown API environment: ${environment}`);
    }

    return new ApiClient({ baseUrl });
}
JAVASCRIPT,
        ];
    }

    /** @return array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string} */
    private function typescriptRepository(): array
    {
        return [
            'folder' => 'TypeScript',
            'title' => 'Generic Repository',
            'filename' => 'repository.ts',
            'language' => 'typescript',
            'description' => 'A typed repository contract and in-memory implementation constrained to identifiable entities.',
            'position' => 0,
            'tags' => ['classes', 'oop', 'typescript', 'generics', 'repository', 'interface'],
            'frameworks' => [],
            'content' => <<<'TYPESCRIPT'
// {!# snippet: identifiable_entity #!}
export interface Identifiable<TKey extends string | number = string> {
    id: TKey;
}

// {!# snippet: generic_repository_interface #!}
export interface Repository<
    TEntity extends Identifiable<TKey>,
    TKey extends string | number = string,
> {
    find(id: TKey): Promise<TEntity | null>;
    save(entity: TEntity): Promise<void>;
    remove(id: TKey): Promise<boolean>;
    all(): Promise<readonly TEntity[]>;
}

// {!# snippet: in_memory_repository #!}
export class InMemoryRepository<
    TEntity extends Identifiable<TKey>,
    TKey extends string | number = string,
> implements Repository<TEntity, TKey> {
    readonly #entities = new Map<TKey, TEntity>();

    async find(id: TKey): Promise<TEntity | null> {
        return this.#entities.get(id) ?? null;
    }

    async save(entity: TEntity): Promise<void> {
        this.#entities.set(entity.id, structuredClone(entity));
    }

    async remove(id: TKey): Promise<boolean> {
        return this.#entities.delete(id);
    }

    async all(): Promise<readonly TEntity[]> {
        return [...this.#entities.values()].map((entity) => structuredClone(entity));
    }
}

// {!# snippet: typed_repository_usage #!}
type Product = Identifiable<number> & { name: string; price: number };

const products: Repository<Product, number> = new InMemoryRepository();
await products.save({ id: 1, name: 'Keyboard', price: 9900 });
TYPESCRIPT,
        ];
    }

    /** @return array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string} */
    private function typescriptFactory(): array
    {
        return [
            'folder' => 'TypeScript',
            'title' => 'Abstract Base and Typed Factory',
            'filename' => 'handler-factory.ts',
            'language' => 'typescript',
            'description' => 'An abstract handler base class and discriminated factory whose return type follows the requested kind.',
            'position' => 1,
            'tags' => ['classes', 'oop', 'typescript', 'abstract-class', 'factory', 'generics'],
            'frameworks' => [],
            'content' => <<<'TYPESCRIPT'
// {!# snippet: abstract_handler_base #!}
export abstract class Handler<TInput, TOutput> {
    async execute(input: TInput): Promise<TOutput> {
        this.validate(input);

        return this.handle(input);
    }

    protected abstract validate(input: TInput): void;
    protected abstract handle(input: TInput): Promise<TOutput>;
}

// {!# snippet: concrete_handlers #!}
export class UppercaseHandler extends Handler<string, string> {
    protected validate(input: string): void {
        if (input.trim() === '') throw new TypeError('Text is required.');
    }

    protected async handle(input: string): Promise<string> {
        return input.toUpperCase();
    }
}

export class LengthHandler extends Handler<string, number> {
    protected validate(input: string): void {
        if (input.trim() === '') throw new TypeError('Text is required.');
    }

    protected async handle(input: string): Promise<number> {
        return input.length;
    }
}

// {!# snippet: typed_handler_factory #!}
type HandlerMap = {
    uppercase: UppercaseHandler;
    length: LengthHandler;
};

export function createHandler<TKey extends keyof HandlerMap>(
    kind: TKey,
): HandlerMap[TKey] {
    const handlers: HandlerMap = {
        uppercase: new UppercaseHandler(),
        length: new LengthHandler(),
    };

    return handlers[kind];
}

const uppercase = createHandler('uppercase');
const result = await uppercase.execute('copy-ready');
TYPESCRIPT,
        ];
    }

    /** @return array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string} */
    private function wordpressBootstrap(): array
    {
        return [
            'folder' => 'WordPress',
            'title' => 'Namespaced Plugin Bootstrap',
            'filename' => 'Plugin.php',
            'language' => 'php',
            'description' => 'A namespaced WordPress plugin composition root with explicit dependency registration.',
            'position' => 0,
            'tags' => ['classes', 'oop', 'php', 'wordpress', 'plugin-development', 'service'],
            'frameworks' => ['wordpress'],
            'content' => <<<'PHP'
<?php
// {!# snippet: wordpress_plugin_bootstrap_class #!}
declare(strict_types=1);

namespace Acme\Classes;

final readonly class Plugin
{
    /** @param list<HookSubscriber> $subscribers */
    public function __construct(private array $subscribers) {}

    public function register(): void
    {
        foreach ($this->subscribers as $subscriber) {
            $subscriber->subscribe();
        }
    }
}

// {!# snippet: wordpress_plugin_composition_root #!}

namespace Acme\Classes;

function plugin(): Plugin
{
    static $plugin;

    return $plugin ??= new Plugin([
        new ContentHookSubscriber,
    ]);
}

// {!# snippet: wordpress_plugin_entrypoint #!}
/**
 * Plugin Name: Acme Classes
 * Description: Demonstrates a small namespaced plugin composition root.
 * Version: 1.0.0
 * Requires PHP: 8.1
 */

namespace Acme\Classes;

defined('ABSPATH') || exit;

add_action('plugins_loaded', static function (): void {
    plugin()->register();
});
PHP,
        ];
    }

    /** @return array{folder: string, title: string, filename: string, language: string, description: string, position: int, tags: list<string>, frameworks: list<string>, content: string} */
    private function wordpressHookSubscriber(): array
    {
        return [
            'folder' => 'WordPress',
            'title' => 'Hook Subscriber Class',
            'filename' => 'HookSubscriber.php',
            'language' => 'php',
            'description' => 'A WordPress hook subscriber contract and implementation with late escaping and focused registrations.',
            'position' => 1,
            'tags' => ['classes', 'oop', 'php', 'wordpress', 'plugin-development', 'hooks', 'interface'],
            'frameworks' => ['wordpress'],
            'content' => <<<'PHP'
<?php
// {!# snippet: hook_subscriber_interface #!}
declare(strict_types=1);

namespace Acme\Classes;

interface HookSubscriber
{
    public function subscribe(): void;
}

// {!# snippet: content_hook_subscriber #!}

namespace Acme\Classes;

final class ContentHookSubscriber implements HookSubscriber
{
    public function subscribe(): void
    {
        add_action('init', [$this, 'registerExcerptSupport']);
        add_filter('the_title', [$this, 'prefixPrivateTitle'], 10, 2);
    }

    public function registerExcerptSupport(): void
    {
        add_post_type_support('page', 'excerpt');
    }

    public function prefixPrivateTitle(string $title, int $postId): string
    {
        if (get_post_status($postId) !== 'private') {
            return $title;
        }

        return sprintf(
            /* translators: %s is the original post title. */
            __('Private: %s', 'acme-classes'),
            $title,
        );
    }
}

// {!# snippet: register_hook_subscriber #!}

namespace Acme\Classes;

$subscriber = new ContentHookSubscriber;
$subscriber->subscribe();
PHP,
        ];
    }
}
