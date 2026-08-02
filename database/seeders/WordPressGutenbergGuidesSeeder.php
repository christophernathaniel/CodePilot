<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Snippet;
use App\Models\Tag;
use App\Models\User;
use App\Support\Snippets\FrameworkCatalog;
use App\Support\Snippets\SnippetLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WordPressGutenbergGuidesSeeder extends Seeder
{
    /**
     * Seed the WordPress 7.0 production and 7.1 compatibility guide collection.
     */
    public function run(): void
    {
        $user = User::query()->where('email', 'dev@dev.dev')->firstOrFail();

        DB::transaction(function () use ($user): void {
            FrameworkCatalog::seedFor($user);

            $project = $this->guideProject($user);
            $framework = $user->frameworks()->where('slug', 'wordpress')->firstOrFail();
            $tags = $this->tags($user);

            foreach ($this->guides() as $position => $guide) {
                $snippet = Snippet::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'location_key' => SnippetLocation::key($project->id, null),
                        'filename' => $guide['filename'],
                    ],
                    [
                        'project_id' => $project->id,
                        'folder_id' => null,
                        'content_type' => 'guide',
                        'title' => $guide['title'],
                        'language' => 'markdown',
                        'description' => $guide['description'],
                        'position' => $position,
                    ],
                );

                $snippet->variations()->updateOrCreate(
                    ['name' => 'WordPress 7.0 stable / 7.1 aware'],
                    [
                        'created_by_id' => $user->id,
                        'content' => $guide['content'],
                        'position' => 0,
                        'is_default' => true,
                    ],
                );

                $snippet->variations()
                    ->where('name', '!=', 'WordPress 7.0 stable / 7.1 aware')
                    ->update(['is_default' => false]);

                $snippet->frameworks()->syncWithoutDetaching([$framework->id]);
                $snippet->tags()->sync(collect($guide['tags'])
                    ->prepend('guide')
                    ->prepend('wordpress')
                    ->prepend('accessibility')
                    ->unique()
                    ->map(fn (string $slug): int => $tags[$slug]->id)
                    ->all());
            }
        });
    }

    private function guideProject(User $user): Project
    {
        $project = $user->projects()->firstOrCreate(
            ['name' => 'WordPress Gutenberg'],
            [
                'kind' => 'guide',
                'description' => 'Step-by-step WordPress 7.0 production guides with explicit WordPress 7.1 compatibility notes, accessibility checks, and enterprise/VIP guardrails.',
                'position' => ((int) $user->projects()->max('position')) + 1,
            ],
        );

        $project->update([
            'kind' => 'guide',
            'description' => 'Step-by-step WordPress 7.0 production guides with explicit WordPress 7.1 compatibility notes, accessibility checks, and enterprise/VIP guardrails.',
        ]);

        return $project;
    }

    /** @return array<string, Tag> */
    private function tags(User $user): array
    {
        $definitions = [
            'wordpress' => ['WordPress', '#60a5fa'],
            'guide' => ['Guide', '#93c5fd'],
            'accessibility' => ['Accessibility', '#67e8f9'],
            'gutenberg' => ['Gutenberg', '#818cf8'],
            'docker' => ['Docker', '#38bdf8'],
            'wp-cli' => ['WP-CLI', '#7dd3fc'],
            'block-theme' => ['Block theme', '#a5b4fc'],
            'patterns' => ['Patterns', '#c4b5fd'],
            'blocks' => ['Blocks', '#a78bfa'],
            'plugin-development' => ['Plugin development', '#94a3b8'],
            'wp-cron' => ['WP-Cron', '#64748b'],
            'headless' => ['Headless', '#22d3ee'],
            'rest-api' => ['REST API', '#2dd4bf'],
            'javascript' => ['JavaScript', '#7dd3fc'],
            'database' => ['Database', '#94a3b8'],
            'custom-fields' => ['Custom fields', '#a5b4fc'],
            'security' => ['Security', '#60a5fa'],
            'wordpress-vip' => ['WordPress VIP', '#38bdf8'],
        ];

        return collect($definitions)->mapWithKeys(function (array $definition, string $slug) use ($user): array {
            $tag = $user->tags()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $definition[0], 'color' => $definition[1]],
            );

            return [$slug => $tag];
        })->all();
    }

    /**
     * @return list<array{filename: string, title: string, description: string, tags: list<string>, content: string}>
     */
    private function guides(): array
    {
        return [
            $this->dockerGuide(),
            $this->patternGuide(),
            $this->blockGuide(),
            $this->pluginGuide(),
            $this->headlessGuide(),
            $this->restApiGuide(),
            $this->customTableGuide(),
        ];
    }

    /** @return array{filename: string, title: string, description: string, tags: list<string>, content: string} */
    private function dockerGuide(): array
    {
        return [
            'filename' => '01-docker-wordpress-gutenberg.guide.md',
            'title' => 'Docker, Gutenberg, Ollie and WP-CLI',
            'description' => 'Build a reproducible WordPress 7.0 environment with WP-CLI, the Ollie block theme, Yoast SEO, and host-appropriate caching.',
            'tags' => ['gutenberg', 'docker', 'wp-cli', 'block-theme', 'wordpress-vip', 'security'],
            'content' => <<<'GUIDE'
{!# guide-step: baseline | Set the supported production baseline #!}
Build production against WordPress 7.0.x. WordPress 7.1 is still a pre-release target until its scheduled 19 August 2026 release, so validate this stack against 7.1 release candidates separately before upgrading production. Use PHP 8.3 for a conservative modern baseline, pin container versions, and commit the Compose file rather than generated credentials.

The Gutenberg plugin is optional early-access software. Core already contains the stable block editor, so do not activate Gutenberg on production merely to obtain normal block editing.

```text
Production: WordPress 7.0.x + PHP 8.3
Compatibility lane: latest WordPress 7.1 release candidate
Database: MariaDB 11.4 LTS
Theme source: https://github.com/olliewp/ollie
```

{!# guide-step: environment | Create local environment values #!}
Create `.env` beside `compose.yaml`. Keep real secrets out of source control and use unique credentials outside local development.

```dotenv
WP_PORT=8080
WP_DB_NAME=wordpress
WP_DB_USER=wordpress
WP_DB_PASSWORD=local-wordpress-only
WP_DB_ROOT_PASSWORD=local-root-only
WP_SITE_URL=http://localhost:8080
WP_SITE_TITLE=Gutenberg Theme Lab
WP_ADMIN_USER=editorial-admin
WP_ADMIN_PASSWORD=replace-this-local-password
WP_ADMIN_EMAIL=dev@example.test
```

```gitignore
.env
wp-content/uploads/
wp-content/cache/
wp-content/upgrade/
```

{!# guide-step: compose | Add WordPress, MariaDB, and WP-CLI services #!}
Bind-mount `wp-content` so themes and plugins remain visible to the host. The WP-CLI service shares the same files and database network. The database health check prevents installation racing the database startup.

```yaml
services:
  database:
    image: mariadb:11.4
    restart: unless-stopped
    environment:
      MARIADB_DATABASE: ${WP_DB_NAME}
      MARIADB_USER: ${WP_DB_USER}
      MARIADB_PASSWORD: ${WP_DB_PASSWORD}
      MARIADB_ROOT_PASSWORD: ${WP_DB_ROOT_PASSWORD}
    volumes:
      - database:/var/lib/mysql
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 20

  wordpress:
    image: wordpress:7.0-php8.3-apache
    restart: unless-stopped
    depends_on:
      database:
        condition: service_healthy
    ports:
      - "${WP_PORT}:80"
    environment: &wordpress-environment
      WORDPRESS_DB_HOST: database:3306
      WORDPRESS_DB_NAME: ${WP_DB_NAME}
      WORDPRESS_DB_USER: ${WP_DB_USER}
      WORDPRESS_DB_PASSWORD: ${WP_DB_PASSWORD}
      WORDPRESS_CONFIG_EXTRA: |
        define( 'WP_ENVIRONMENT_TYPE', 'local' );
        define( 'WP_DEBUG', true );
        define( 'WP_DEBUG_LOG', true );
        define( 'WP_DEBUG_DISPLAY', false );
    volumes:
      - wordpress:/var/www/html
      - ./wp-content:/var/www/html/wp-content

  wpcli:
    image: wordpress:cli-php8.3
    depends_on:
      database:
        condition: service_healthy
      wordpress:
        condition: service_started
    environment: *wordpress-environment
    user: "33:33"
    working_dir: /var/www/html
    volumes:
      - wordpress:/var/www/html
      - ./wp-content:/var/www/html/wp-content
    entrypoint: ["wp", "--allow-root"]

volumes:
  database:
  wordpress:
```

{!# guide-step: install | Start and install WordPress with WP-CLI #!}
Start the services, wait until WordPress responds, then install only if it is not already installed. The second command makes the process safe to repeat.

```bash
mkdir -p wp-content/themes wp-content/plugins wp-content/uploads
docker compose up -d
docker compose run --rm wpcli core is-installed || docker compose run --rm wpcli core install \
  --url="$WP_SITE_URL" \
  --title="$WP_SITE_TITLE" \
  --admin_user="$WP_ADMIN_USER" \
  --admin_password="$WP_ADMIN_PASSWORD" \
  --admin_email="$WP_ADMIN_EMAIL" \
  --skip-email
```

{!# guide-step: ollie | Source and activate Ollie from GitHub #!}
Clone Ollie into the mounted theme directory so its Git origin is retained. Pin a reviewed tag or commit in real projects rather than following `main` indefinitely.

```bash
git clone https://github.com/olliewp/ollie.git wp-content/themes/ollie
git -C wp-content/themes/ollie checkout <reviewed-tag-or-commit>
docker compose run --rm wpcli theme activate ollie
docker compose run --rm wpcli theme status ollie
```

Create custom work in a child theme or your own block theme so upstream Ollie updates do not overwrite it.

{!# guide-step: plugins | Install SEO and select a caching strategy #!}
Install Yoast SEO by its WordPress.org slug. WP Super Cache is appropriate for conventional hosting and production-like testing, but leave it disabled while actively editing. On WordPress VIP, do not install a page-cache plugin: the platform provides full-page and object caching and a cache plugin can conflict with it.

```bash
docker compose run --rm wpcli plugin install wordpress-seo --activate
docker compose run --rm wpcli plugin install wp-super-cache
docker compose run --rm wpcli rewrite structure '/%postname%/' --hard
docker compose run --rm wpcli option update timezone_string 'Europe/London'
docker compose run --rm wpcli cache flush
```

For a conventional production environment only:

```bash
docker compose run --rm wpcli plugin activate wp-super-cache
docker compose run --rm wpcli wp-super-cache enable
```

{!# guide-step: verify | Verify security, blocks, SEO, and accessibility #!}
Confirm exact versions, remove unused defaults, and check that a keyboard-only user can reach the skip link, navigation, editor controls, and all interactive components. Test at 200% zoom, with reduced motion, and with meaningful image alternative text. Run the compatibility lane against WordPress 7.1 RC before upgrading.

```bash
docker compose run --rm wpcli core version
docker compose run --rm wpcli core verify-checksums
docker compose run --rm wpcli plugin list
docker compose run --rm wpcli theme list
docker compose run --rm wpcli cron event list
docker compose logs --tail=100 wordpress
```

```text
Acceptance checks
- HTTPS and non-default secrets in shared environments
- no PHP notices in wp-content/debug.log
- Site Health has no critical issues
- block editor and front end both render theme.json tokens
- headings remain sequential and landmarks have accessible names
- focus is visible and colour contrast meets WCAG 2.2 AA
- XML sitemap and canonical output are present once, not duplicated
```
GUIDE,
        ];
    }

    /** @return array{filename: string, title: string, description: string, tags: list<string>, content: string} */
    private function patternGuide(): array
    {
        return [
            'filename' => '02-create-theme-pattern.guide.md',
            'title' => 'Create an accessible theme pattern',
            'description' => 'Register a semantic block pattern from a block theme and keep its colours, typography, and spacing in theme.json.',
            'tags' => ['gutenberg', 'block-theme', 'patterns'],
            'content' => <<<'GUIDE'
{!# guide-step: baseline | Decide what belongs in the pattern #!}
Target WordPress 7.0 stable and test the finished pattern in the WordPress 7.1 release-candidate lane. A pattern is composed of existing blocks; use it for a reusable editorial layout, not for behaviour that requires a custom block.

Give the pattern a clear title and description. WordPress exposes the description to assistive technology in the inserter, so explain the purpose rather than its appearance alone.

```text
Theme: cn-studio
Pattern slug: cn-studio/feature-callout
Category: featured
Viewport width: 1280
Purpose: a heading, explanatory copy, and a clearly named call to action
```

{!# guide-step: tokens | Define colour and font-size presets in theme.json #!}
Define design decisions once and consume their generated preset classes. Do not hard-code one-off hex values and pixel sizes throughout pattern markup. Keep text/background pairs at WCAG 2.2 AA contrast and avoid disabling user-controlled custom colours unless the product requires it.

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 3,
  "settings": {
    "color": {
      "palette": [
        { "slug": "canvas", "name": "Canvas", "color": "#f4f6f8" },
        { "slug": "ink", "name": "Ink", "color": "#172033" },
        { "slug": "accent", "name": "Accent", "color": "#2457c5" }
      ]
    },
    "typography": {
      "fontSizes": [
        { "slug": "small", "name": "Small", "size": "clamp(0.875rem, 0.84rem + 0.15vw, 0.95rem)" },
        { "slug": "body", "name": "Body", "size": "clamp(1rem, 0.96rem + 0.2vw, 1.125rem)" },
        { "slug": "display", "name": "Display", "size": "clamp(2.25rem, 1.8rem + 2vw, 4.5rem)" }
      ]
    }
  }
}
```

{!# guide-step: file | Add the auto-registered pattern file #!}
Create `patterns/feature-callout.php`. Block themes automatically register valid PHP files in this directory. Use a namespaced slug, translatable strings, semantic heading order, and descriptive link text.

```php
<?php
/**
 * Title: Feature callout
 * Slug: cn-studio/feature-callout
 * Categories: featured, call-to-action
 * Description: Introduces a featured service with supporting copy and a clearly named action.
 * Viewport Width: 1280
 */
?>
<!-- wp:group {"tagName":"section","metadata":{"name":"Feature callout"},"backgroundColor":"canvas","textColor":"ink","layout":{"type":"constrained"}} -->
<section class="wp-block-group has-ink-color has-canvas-background-color has-text-color has-background">
    <!-- wp:heading {"fontSize":"display"} -->
    <h2 class="wp-block-heading has-display-font-size"><?php echo esc_html_x( 'Build clearer digital services', 'Pattern heading', 'cn-studio' ); ?></h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"fontSize":"body"} -->
    <p class="has-body-font-size"><?php echo esc_html_x( 'Plan, design, and ship an experience people can understand and use.', 'Pattern supporting copy', 'cn-studio' ); ?></p>
    <!-- /wp:paragraph -->

    <!-- wp:buttons -->
    <div class="wp-block-buttons">
        <!-- wp:button {"backgroundColor":"accent"} -->
        <div class="wp-block-button"><a class="wp-block-button__link has-accent-background-color has-background wp-element-button"><?php echo esc_html_x( 'Explore our delivery approach', 'Pattern call to action', 'cn-studio' ); ?></a></div>
        <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
</section>
<!-- /wp:group -->
```

{!# guide-step: style | Style through block selectors, not fragile DOM depth #!}
Use the pattern slug only as an editor identity; add a purposeful class through the editor when the layout needs scoped styling. Prefer global block styles and theme.json first. Preserve visible focus and reduced-motion preferences.

```css
.cn-feature-callout {
  container-type: inline-size;
}

.cn-feature-callout .wp-block-button__link:focus-visible {
  outline: 3px solid currentColor;
  outline-offset: 4px;
}

@media (prefers-reduced-motion: reduce) {
  .cn-feature-callout *,
  .cn-feature-callout *::before,
  .cn-feature-callout *::after {
    scroll-behavior: auto;
    transition-duration: 0.01ms;
  }
}
```

{!# guide-step: translate | Load translations and keep starter copy editable #!}
Pattern text is starter content and remains editable after insertion. Load the theme text domain and extract translations during the release build.

```php
<?php
add_action( 'after_setup_theme', static function (): void {
    load_theme_textdomain( 'cn-studio', get_template_directory() . '/languages' );
} );
```

```bash
wp i18n make-pot . languages/cn-studio.pot --exclude=node_modules,vendor
```

{!# guide-step: verify | Test insertion, transformation, and accessibility #!}
Insert the pattern into a blank page and into a page with an existing H1. Confirm it does not introduce a second H1, its text remains editable, and deleting/reinserting it does not create validation errors.

```bash
wp theme status cn-studio
wp eval 'print_r( WP_Block_Patterns_Registry::get_instance()->get_registered( "cn-studio/feature-callout" ) );'
```

```text
- Pattern title and description are understandable out of context
- keyboard focus is visible on every link
- link wording describes the destination
- text survives 200% zoom without clipping
- foreground/background combinations meet WCAG 2.2 AA
- layout works in both editor iframe and front end
- PHP, block markup, translations, and escaping pass project checks
```
GUIDE,
        ];
    }

    /** @return array{filename: string, title: string, description: string, tags: list<string>, content: string} */
    private function blockGuide(): array
    {
        return [
            'filename' => '03-create-theme-block.guide.md',
            'title' => 'Create an accessible custom block',
            'description' => 'Create a metadata-driven, API v3 dynamic block that works in the iframed editor and on the front end.',
            'tags' => ['gutenberg', 'block-theme', 'blocks', 'javascript', 'security'],
            'content' => <<<'GUIDE'
{!# guide-step: baseline | Confirm the block belongs to the theme #!}
Target WordPress 7.0 stable and test against the WordPress 7.1 release-candidate lane. WordPress 7.1 always iframes the post editor, so use Block API version 3 and never assume editor styles or DOM globals belong to the parent window.

Reusable content functionality normally belongs in a plugin so it survives a theme switch. Keep this block in the theme only because its markup and lifecycle are deliberately theme-specific.

```text
Block: cn-studio/feature-notice
Rendering: dynamic PHP
Block API: 3
Editor: React via @wordpress packages
Front end: semantic <aside> with an accessible heading
```

{!# guide-step: scaffold | Create the block source and build structure #!}
Keep source and generated output separate. Commit source; whether `build` is committed depends on the deployment pipeline. Use the project-pinned Node version and lockfile in CI.

```text
cn-studio/
├── blocks/
│   └── feature-notice/
│       ├── block.json
│       ├── edit.js
│       ├── editor.css
│       ├── index.js
│       ├── render.php
│       └── style.css
├── build/
├── functions.php
└── package.json
```

```json
{
  "private": true,
  "scripts": {
    "build": "wp-scripts build --webpack-copy-php",
    "start": "wp-scripts start --webpack-copy-php",
    "lint:js": "wp-scripts lint-js",
    "lint:css": "wp-scripts lint-style"
  },
  "devDependencies": {
    "@wordpress/scripts": "latest"
  }
}
```

{!# guide-step: metadata | Declare an API v3 block in block.json #!}
Use `block.json` as the single source of metadata. A namespaced block name prevents collisions. Declare only supports the block genuinely implements, and give every translatable UI string a text domain.

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "cn-studio/feature-notice",
  "version": "1.0.0",
  "title": "Feature notice",
  "category": "design",
  "icon": "info-outline",
  "description": "Highlights an important, non-urgent message.",
  "textdomain": "cn-studio",
  "attributes": {
    "heading": { "type": "string", "default": "Important information" },
    "message": { "type": "string", "default": "Add the information readers need." }
  },
  "supports": {
    "anchor": true,
    "align": ["wide", "full"],
    "color": { "background": true, "text": true },
    "html": false,
    "spacing": { "margin": true, "padding": true }
  },
  "editorScript": "file:../../build/feature-notice/index.js",
  "editorStyle": "file:../../build/feature-notice/index.css",
  "style": "file:../../build/feature-notice/style-index.css",
  "render": "file:../../build/feature-notice/render.php"
}
```

{!# guide-step: editor | Build the editor component with block primitives #!}
Use `useBlockProps` so core can apply selection, class, style, and accessibility behaviour. `RichText` provides editor keyboard behaviour that a custom contenteditable implementation would need to recreate.

```js
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({ className: 'cn-feature-notice' });

    return (
        <aside {...blockProps}>
            <RichText
                tagName="h2"
                value={attributes.heading}
                allowedFormats={[]}
                placeholder={__('Notice heading', 'cn-studio')}
                onChange={(heading) => setAttributes({ heading })}
            />
            <RichText
                tagName="p"
                value={attributes.message}
                placeholder={__('Notice text', 'cn-studio')}
                onChange={(message) => setAttributes({ message })}
            />
        </aside>
    );
}
```

```js
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './editor.css';
import './style.css';

registerBlockType(metadata.name, {
    edit: Edit,
    save: () => null,
});
```

{!# guide-step: render | Render trusted markup on the server #!}
Dynamic rendering allows markup changes without invalidating saved content. Escape plain-text attributes at output and use `get_block_wrapper_attributes()` so core supports classes, anchors, and style attributes.

```php
<?php
/**
 * @var array{heading?: string, message?: string} $attributes
 */

$heading = isset( $attributes['heading'] ) ? $attributes['heading'] : '';
$message = isset( $attributes['message'] ) ? $attributes['message'] : '';

if ( '' === trim( $heading ) && '' === trim( $message ) ) {
    return;
}
?>
<aside <?php echo get_block_wrapper_attributes( array( 'class' => 'cn-feature-notice' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core returns escaped attributes. ?>>
    <?php if ( '' !== trim( $heading ) ) : ?>
        <h2 class="cn-feature-notice__heading"><?php echo esc_html( $heading ); ?></h2>
    <?php endif; ?>
    <?php if ( '' !== trim( $message ) ) : ?>
        <p class="cn-feature-notice__message"><?php echo esc_html( $message ); ?></p>
    <?php endif; ?>
</aside>
```

Do not add `role="alert"` to ordinary static content: it would announce the notice unexpectedly. Use a live region only when new urgent content is injected after page load.

{!# guide-step: register | Register metadata on init #!}
Register blocks on `init`. In WordPress 7.0, metadata-based registration allows core to optimise asset loading. Point registration at the built block directory produced by the build.

```php
<?php
add_action( 'init', static function (): void {
    register_block_type( get_template_directory() . '/build/feature-notice' );
} );
```

```bash
npm ci
npm run build
wp cache flush
wp block list | grep 'cn-studio/feature-notice'
```

{!# guide-step: iframe | Keep code safe in the iframed editor #!}
Do not query the top-level `window.document`, inject global editor CSS, or append overlays to the parent body. Prefer WordPress components and block-editor hooks. When DOM access is unavoidable, derive the document and window from the block element.

```js
const ownerDocument = blockElement.ownerDocument;
const ownerWindow = ownerDocument.defaultView;

ownerWindow?.requestAnimationFrame(() => {
    blockElement.scrollIntoView({ block: 'nearest' });
});
```

{!# guide-step: verify | Run block and accessibility acceptance checks #!}
Test insertion, editing, duplication, undo/redo, alignment, Global Styles, reusable content, and serialization. Test both the editor iframe and front-end document in WordPress 7.0 and the latest 7.1 RC.

```bash
npm run lint:js
npm run lint:css
wp i18n make-pot . languages/cn-studio.pot --exclude=node_modules,build
```

```text
- block.json validates and apiVersion is 3
- no editor console errors or block validation warnings
- heading level fits the surrounding page outline
- content is readable at 200% zoom and narrow widths
- visible focus is retained for any links or controls
- colours meet WCAG 2.2 AA in every supported style variation
- editor and front-end presentation remain recognisably equivalent
```
GUIDE,
        ];
    }

    /** @return array{filename: string, title: string, description: string, tags: list<string>, content: string} */
    private function pluginGuide(): array
    {
        return [
            'filename' => '04-create-wordpress-plugin.guide.md',
            'title' => 'Create a plugin with hooks, assets, and five-minute cron',
            'description' => 'Build a namespaced plugin with activation safety, scoped assets, actions and filters, and a non-overlapping WP-Cron task.',
            'tags' => ['plugin-development', 'wp-cron', 'javascript', 'security', 'wordpress-vip'],
            'content' => <<<'GUIDE'
{!# guide-step: baseline | Define the plugin contract #!}
Target WordPress 7.0 stable and maintain a WordPress 7.1 release-candidate compatibility lane. Keep functionality in the plugin, prefix public identifiers, namespace PHP code, and require the oldest PHP version the product has actually tested.

```text
Plugin: CN Editorial Sync
Text domain: cn-editorial-sync
Cron hook: cn_editorial_sync_five_minutes
Custom action: cn_editorial_sync_completed
Production runtime: WordPress 7.0.x
```

{!# guide-step: bootstrap | Create a small, guarded plugin bootstrap #!}
Create `cn-editorial-sync/cn-editorial-sync.php`. Exit when accessed directly. Register activation and deactivation from the main file so WordPress can find them reliably.

```php
<?php
/**
 * Plugin Name: CN Editorial Sync
 * Description: Runs a small editorial synchronisation task on a five-minute schedule.
 * Version: 1.0.0
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Text Domain: cn-editorial-sync
 */

namespace CN\EditorialSync;

defined( 'ABSPATH' ) || exit;

const VERSION   = '1.0.0';
const CRON_HOOK = 'cn_editorial_sync_five_minutes';

require_once __DIR__ . '/includes/class-plugin.php';

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );

Plugin::boot( __FILE__ );
```

{!# guide-step: hooks | Wire actions and filters in one service #!}
Use actions for side effects and filters for values that callers can modify. Load browser assets only on the page that uses them, and use the modern script strategy arguments.

```php
<?php
namespace CN\EditorialSync;

final class Plugin {
    private static string $plugin_file;

    public static function boot( string $plugin_file ): void {
        self::$plugin_file = $plugin_file;

        add_filter( 'cron_schedules', array( self::class, 'add_schedule' ) );
        add_action( 'init', array( self::class, 'ensure_scheduled' ) );
        add_action( CRON_HOOK, array( self::class, 'run_sync' ) );
        add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
    }

    /** @param array<string, array{interval: int, display: string}> $schedules */
    public static function add_schedule( array $schedules ): array {
        $schedules['cn_every_five_minutes'] = array(
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => __( 'Every five minutes', 'cn-editorial-sync' ),
        );

        return $schedules;
    }

    public static function enqueue_assets(): void {
        if ( ! is_page_template( 'templates/editorial-dashboard.html' ) ) {
            return;
        }

        wp_enqueue_style(
            'cn-editorial-sync',
            plugins_url( 'assets/editorial-sync.css', self::$plugin_file ),
            array(),
            VERSION
        );

        wp_enqueue_script(
            'cn-editorial-sync',
            plugins_url( 'assets/editorial-sync.js', self::$plugin_file ),
            array(),
            VERSION,
            array( 'strategy' => 'defer', 'in_footer' => true )
        );
    }
}
```

{!# guide-step: schedule | Schedule once and clean up on deactivation #!}
Always check before scheduling so ordinary requests do not create duplicates. Clear every event for the hook on deactivation. WP-Cron is traffic-driven and approximate; five minutes is a recurrence, not a real-time guarantee.

```php
<?php
namespace CN\EditorialSync;

public static function activate(): void {
    self::ensure_scheduled();
}

public static function ensure_scheduled(): void {
    if ( false === wp_next_scheduled( CRON_HOOK ) ) {
        wp_schedule_event( time() + MINUTE_IN_SECONDS, 'cn_every_five_minutes', CRON_HOOK );
    }
}

public static function deactivate(): void {
    wp_clear_scheduled_hook( CRON_HOOK );
    delete_transient( 'cn_editorial_sync_lock' );
}
```

Place these methods inside the `Plugin` class from the previous step.

{!# guide-step: task | Make the task bounded and non-overlapping #!}
Cron callbacks can overlap or retry. Use a short lock, cap work per run, use the HTTP API with timeouts, and log only operational context—not tokens or personal data.

```php
<?php
namespace CN\EditorialSync;

public static function run_sync(): void {
    if ( get_transient( 'cn_editorial_sync_lock' ) ) {
        return;
    }

    set_transient( 'cn_editorial_sync_lock', '1', 4 * MINUTE_IN_SECONDS );

    try {
        $processed_ids = self::synchronise_next_batch( 25 );

        /**
         * Fires after a bounded editorial sync batch.
         *
         * @param list<int> $processed_ids Updated post IDs.
         */
        do_action( 'cn_editorial_sync_completed', $processed_ids );
    } finally {
        delete_transient( 'cn_editorial_sync_lock' );
    }
}

/** @return list<int> */
private static function synchronise_next_batch( int $limit ): array {
    $post_ids = get_posts(
        array(
            'fields'         => 'ids',
            'post_status'    => 'publish',
            'posts_per_page' => min( 25, max( 1, $limit ) ),
            'no_found_rows'  => true,
        )
    );

    return array_map( 'intval', $post_ids );
}
```

{!# guide-step: enterprise | Adapt scheduling for enterprise hosting #!}
On WordPress VIP, Core's `wp-cron.php` request path is disabled and the platform runs Core-compatible hooks through Cron Control. Continue using `wp_schedule_event`; do not repeatedly emulate recurrence with `wp_schedule_single_event`. Keep jobs idempotent and observable.

On conventional hosting, invoke WP-Cron from the system scheduler for dependable traffic-independent execution and disable request spawning only after that scheduler is proven.

```cron
*/5 * * * * cd /srv/www && wp cron event run --due-now --quiet
```

```php
<?php
// wp-config.php, only when a real scheduler invokes due events.
define( 'DISABLE_WP_CRON', true );
```

{!# guide-step: verify | Activate and exercise the lifecycle #!}
Verify that activation creates one event, manual execution completes, repeated page requests do not duplicate it, and deactivation clears it. Run coding standards, unit/integration tests, and asset linting in CI.

```bash
wp plugin activate cn-editorial-sync
wp cron event list --fields=hook,next_run_gmt,recurrence | grep cn_editorial_sync
wp cron event run cn_editorial_sync_five_minutes
wp cron event list --fields=hook,next_run_gmt,recurrence | grep cn_editorial_sync
wp plugin deactivate cn-editorial-sync
wp cron event list | grep cn_editorial_sync || true
```

```text
- exactly one recurring event exists after repeated requests
- the lock clears after success and exceptions
- the batch has a fixed upper bound
- scripts/styles load only where used
- no secret, nonce, or personal data reaches logs
- plugin remains usable with caching and multisite constraints documented
```
GUIDE,
        ];
    }
