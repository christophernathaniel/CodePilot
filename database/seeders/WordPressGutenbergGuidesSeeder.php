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
            $project->frameworks()->syncWithoutDetaching([$framework->id]);

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
                    ->prepend('wordpress-vip')
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
                'description' => 'Step-by-step WordPress 7.0.2 production guides with explicit WordPress 7.1 compatibility notes, accessibility checks, and enterprise/VIP guardrails.',
                'position' => ((int) $user->projects()->max('position')) + 1,
            ],
        );

        $project->update([
            'kind' => 'guide',
            'description' => 'Step-by-step WordPress 7.0.2 production guides with explicit WordPress 7.1 compatibility notes, accessibility checks, and enterprise/VIP guardrails.',
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
            'description' => 'Build a reproducible WordPress 7.0.2 environment with WP-CLI, the Ollie block theme, Yoast SEO, and host-appropriate caching.',
            'tags' => ['gutenberg', 'docker', 'wp-cli', 'block-theme', 'wordpress-vip', 'security'],
            'content' => <<<'GUIDE'
{!# guide-step: baseline | Set the supported production baseline #!}
Build production against WordPress 7.0.2. WordPress 7.1 is still a pre-release target until its scheduled 19 August 2026 release, so validate this stack against 7.1 release candidates separately before upgrading production. Use PHP 8.3 for a conservative modern baseline, pin container versions, and commit the Compose file rather than generated credentials.

The Gutenberg plugin is optional early-access software. Core already contains the stable block editor, so do not activate Gutenberg on production merely to obtain normal block editing.

```text
Production: WordPress 7.0.2 + PHP 8.3
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
WP_SITE_TITLE="Gutenberg Theme Lab"
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
    image: wordpress:7.0.2-php8.3-apache
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
set -a
. ./.env
set +a
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
```

Then choose **Simple** caching in Settings → WP Super Cache and verify the anonymous response; do not enable page caching merely because the plugin is installed.

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
<!-- wp:group {"tagName":"section","className":"cn-feature-callout","metadata":{"name":"Feature callout"},"backgroundColor":"canvas","textColor":"ink","layout":{"type":"constrained"}} -->
<section class="wp-block-group cn-feature-callout has-ink-color has-canvas-background-color has-text-color has-background">
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
│       ├── build/
│       ├── src/
│       │   ├── block.json
│       │   ├── edit.js
│       │   ├── editor.scss
│       │   ├── index.js
│       │   ├── render.php
│       │   └── style.scss
│       └── package.json
└── functions.php
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
  "editorScript": "file:./index.js",
  "editorStyle": "file:./index.css",
  "style": "file:./style-index.css",
  "render": "file:./render.php"
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
    register_block_type( get_template_directory() . '/blocks/feature-notice/build' );
} );
```

```bash
npm ci
npm run build
wp cache flush
wp eval 'var_export( WP_Block_Type_Registry::get_instance()->is_registered( "cn-studio/feature-notice" ) );'
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

    /** @return array{filename: string, title: string, description: string, tags: list<string>, content: string} */
    private function headlessGuide(): array
    {
        return [
            'filename' => '05-headless-wordpress.guide.md',
            'title' => 'Set up headless WordPress',
            'description' => 'Use WordPress as the editorial CMS and a separate accessible front end with secure previews, SEO, and cache revalidation.',
            'tags' => ['headless', 'rest-api', 'javascript', 'gutenberg', 'security'],
            'content' => <<<'GUIDE'
{!# guide-step: architecture | Define the headless boundary #!}
This guide treats “headerless” as **headless WordPress**: WordPress remains the authenticated CMS and content authority, while a separate application renders the public site. Target WordPress 7.0.2 in production and run WordPress 7.1 compatibility separately until 7.1 is released.

Choose explicitly how Gutenberg content will render. Rendering `content.rendered` preserves block markup but requires matching block/theme styles. Mapping parsed blocks into application components offers more control but increases compatibility work.

```text
Editors -> HTTPS -> WordPress admin
Public browser -> CDN/frontend -> server-rendered application
Frontend server -> WordPress REST API
WordPress publish hook -> signed revalidation endpoint
```

{!# guide-step: wordpress | Prepare WordPress content for REST #!}
Use public REST data for published content and require authentication for previews and private fields. Register custom post types and metadata with complete schemas. Do not disable the REST API globally because the block editor and other admin features depend on it.

```php
<?php
add_action( 'init', static function (): void {
    register_post_type(
        'case_study',
        array(
            'label'        => __( 'Case studies', 'cn-headless' ),
            'public'       => true,
            'show_in_rest' => true,
            'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
        )
    );

    register_post_meta(
        'case_study',
        'client_summary',
        array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback'     => static fn (): bool => current_user_can( 'edit_posts' ),
        )
    );
} );
```

{!# guide-step: frontend | Create the separate front-end application #!}
Create a TypeScript application and keep the WordPress origin server-only where possible. The public REST origin can be exposed, but credentials and preview tokens must never be included in browser bundles.

```bash
npx create-next-app@latest cn-headless --typescript --eslint --app
cd cn-headless
printf 'WORDPRESS_URL=https://cms.example.com\n' > .env.local
```

```ts
export type WordPressPost = {
    id: number;
    slug: string;
    link: string;
    title: { rendered: string };
    excerpt: { rendered: string };
    content: { rendered: string };
    modified_gmt: string;
};

export async function getPosts(): Promise<WordPressPost[]> {
    const origin = process.env.WORDPRESS_URL;

    if (!origin) {
        throw new Error('WORDPRESS_URL is not configured');
    }

    const url = new URL('/wp-json/wp/v2/posts', origin);
    url.searchParams.set('_fields', 'id,slug,link,title,excerpt,content,modified_gmt');
    url.searchParams.set('per_page', '20');

    const response = await fetch(url, { next: { revalidate: 300 } });

    if (!response.ok) {
        throw new Error(`WordPress returned ${response.status}`);
    }

    return response.json();
}
```

{!# guide-step: render | Render accessible routes and block content #!}
Server-render the page so meaningful HTML exists before JavaScript. Preserve landmarks, one clear page heading, useful link names, responsive image dimensions and alternative text. If privileged authors can store unfiltered HTML, add an allow-list sanitizer before using rendered HTML.

```tsx
import { getPosts } from '@/lib/wordpress';

export default async function HomePage() {
    const posts = await getPosts();

    return (
        <main id="main-content">
            <h1>Latest articles</h1>
            {posts.length === 0 ? (
                <p>No articles have been published yet.</p>
            ) : (
                <ul aria-label="Latest articles">
                    {posts.map((post) => (
                        <li key={post.id}>
                            <article>
                                <h2>
                                    <a href={`/articles/${post.slug}`}>
                                        <span dangerouslySetInnerHTML={{ __html: post.title.rendered }} />
                                    </a>
                                </h2>
                                <div dangerouslySetInnerHTML={{ __html: post.excerpt.rendered }} />
                            </article>
                        </li>
                    ))}
                </ul>
            )}
        </main>
    );
}
```

Load the Core block stylesheet and the public styles from the theme or reproduce each supported block deliberately. Do not silently ship unstyled block markup.

{!# guide-step: authentication | Keep preview authentication on the server #!}
Create one revocable Application Password per integration over HTTPS. Store it in the deployment secret manager, never in a `NEXT_PUBLIC_` value. Cookie authentication plus a REST nonce is for same-origin logged-in WordPress screens; external server-to-server clients should use an Application Password or an approved authentication provider.

```ts
function wordpressAuthorization(): string {
    const username = process.env.WORDPRESS_USERNAME;
    const applicationPassword = process.env.WORDPRESS_APPLICATION_PASSWORD;

    if (!username || !applicationPassword) {
        throw new Error('WordPress preview credentials are not configured');
    }

    return `Basic ${Buffer.from(`${username}:${applicationPassword}`).toString('base64')}`;
}

export async function getPreview(postId: number) {
    const response = await fetch(
        `${process.env.WORDPRESS_URL}/wp-json/wp/v2/posts/${postId}?context=edit`,
        { headers: { Authorization: wordpressAuthorization() }, cache: 'no-store' },
    );

    if (!response.ok) {
        throw new Error(`Preview request failed with ${response.status}`);
    }

    return response.json();
}
```

{!# guide-step: revalidate | Sign cache-revalidation requests #!}
Publish and update hooks should send a short signed message to the front end. The receiver must compare the HMAC in constant time, reject stale timestamps, and revalidate only affected paths/tags. Queue or defer remote calls so saving content does not wait on a slow front end.

```php
<?php
add_action( 'transition_post_status', static function ( string $new, string $old, WP_Post $post ): void {
    if ( 'publish' !== $new || wp_is_post_revision( $post ) ) {
        return;
    }

    wp_schedule_single_event(
        time(),
        'cn_headless_revalidate_post',
        array( $post->ID )
    );
}, 10, 3 );
```

```php
<?php
add_action( 'cn_headless_revalidate_post', static function ( int $post_id ): void {
    $payload   = wp_json_encode( array( 'postId' => $post_id, 'sentAt' => time() ) );
    $secret    = (string) getenv( 'CN_REVALIDATION_SECRET' );
    $signature = hash_hmac( 'sha256', $payload, $secret );

    wp_remote_post(
        'https://www.example.com/api/revalidate',
        array(
            'timeout'  => 3,
            'blocking' => false,
            'headers'  => array(
                'Content-Type'   => 'application/json',
                'X-CN-Signature' => $signature,
            ),
            'body'     => $payload,
        )
    );
} );
```

{!# guide-step: seo | Rebuild the WordPress SEO contract #!}
Headless removes the theme layer that normally prints canonical URLs, metadata and structured data. Preserve Yoast's canonical, robots, Open Graph and schema output through its REST data or a controlled server-side integration. Keep WordPress XML sitemaps or publish equivalent frontend sitemaps, and map legacy WordPress URLs to permanent redirects.

```text
- one canonical URL per public route
- index/noindex follows WordPress editorial intent
- XML sitemap URLs use the public frontend origin
- media uses width/height, srcset, sizes, captions, and stored alt text
- preview and draft routes are authenticated and noindex
- redirect history survives slug changes
```

{!# guide-step: verify | Test the complete decoupled journey #!}
Test the CMS and frontend as one system: publish, preview, update, unpublish, redirect, cache invalidation and failure recovery. Route changes in a client-rendered shell must update the document title, move or announce focus appropriately, and expose loading/error states without relying on colour alone.

```bash
curl --fail --silent 'https://cms.example.com/wp-json/wp/v2/posts?per_page=1&_fields=id,slug' | jq
curl --fail --silent 'https://www.example.com/sitemap.xml' >/dev/null
```

```text
- secrets exist only on trusted servers
- every endpoint has bounded fields and pagination
- preview never leaks drafts through shared caches
- publish revalidates only affected routes
- 404, empty, loading, and API-failure states are accessible
- keyboard navigation, visible focus, zoom, and reduced motion pass WCAG 2.2 AA
```
GUIDE,
        ];
    }

    /** @return array{filename: string, title: string, description: string, tags: list<string>, content: string} */
    private function restApiGuide(): array
    {
        return [
            'filename' => '06-custom-rest-api-endpoint.guide.md',
            'title' => 'Create and consume a REST API endpoint',
            'description' => 'Register versioned public and protected routes, validate arguments, and consume them with accessible JavaScript states.',
            'tags' => ['rest-api', 'javascript', 'plugin-development', 'security'],
            'content' => <<<'GUIDE'
{!# guide-step: contract | Design the resource contract first #!}
Target WordPress 7.0.2 stable and test in the WordPress 7.1 compatibility lane. Use a vendor/version namespace that you control, bound result sizes, and document which fields are public. This guide exposes published notices publicly and protects creation with the `edit_posts` capability.

```json
{
  "namespace": "cn/v1",
  "resource": "/notices",
  "GET": "public, maximum 20 records",
  "POST": "cookie + REST nonce or Application Password; edit_posts required"
}
```

{!# guide-step: controller | Create a focused REST controller #!}
Create a controller in a namespaced plugin. Register routes only on `rest_api_init`. Every route requires `permission_callback`; `__return_true` is correct only when the returned data is deliberately public.

```php
<?php
namespace CN\Notices;

final class Notices_Controller extends \WP_REST_Controller {
    public function __construct() {
        $this->namespace = 'cn/v1';
        $this->rest_base = 'notices';
    }

    public function register_routes(): void {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'index' ),
                    'permission_callback' => '__return_true',
                    'args'                => $this->collection_params(),
                ),
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'store' ),
                    'permission_callback' => static fn (): bool => current_user_can( 'edit_posts' ),
                    'args'                => $this->create_params(),
                ),
                'schema' => array( $this, 'get_public_item_schema' ),
            )
        );
    }
}
```

```php
<?php
add_action( 'init', static function (): void {
    register_post_type(
        'cn_notice',
        array(
            'label'        => __( 'Notices', 'cn-notices' ),
            'public'       => false,
            'show_ui'      => true,
            'show_in_rest' => false,
            'supports'     => array( 'title', 'editor', 'revisions' ),
        )
    );
} );

add_action( 'rest_api_init', static function (): void {
    ( new \CN\Notices\Notices_Controller() )->register_routes();
} );
```

{!# guide-step: schema | Validate and sanitize every accepted argument #!}
Validation decides whether input is acceptable; sanitization normalises accepted input. Keep the public response schema explicit so clients can request `_fields` and discovery tools can understand the endpoint.

```php
<?php
public function collection_params(): array {
    return array(
        'per_page' => array(
            'description'       => __( 'Maximum number of notices.', 'cn-notices' ),
            'type'              => 'integer',
            'default'           => 10,
            'minimum'           => 1,
            'maximum'           => 20,
            'sanitize_callback' => 'absint',
        ),
    );
}

public function create_params(): array {
    return array(
        'title' => array(
            'description'       => __( 'Short notice heading.', 'cn-notices' ),
            'type'              => 'string',
            'required'          => true,
            'minLength'         => 1,
            'maxLength'         => 120,
            'sanitize_callback' => 'sanitize_text_field',
        ),
        'message' => array(
            'description'       => __( 'Plain-text notice message.', 'cn-notices' ),
            'type'              => 'string',
            'required'          => true,
            'minLength'         => 1,
            'maxLength'         => 1000,
            'sanitize_callback' => 'sanitize_textarea_field',
        ),
    );
}

public function get_public_item_schema(): array {
    return array(
        '$schema'    => 'http://json-schema.org/draft-04/schema#',
        'title'      => 'notice',
        'type'       => 'object',
        'properties' => array(
            'id'      => array( 'type' => 'integer', 'readonly' => true ),
            'title'   => array( 'type' => 'string' ),
            'message' => array( 'type' => 'string' ),
        ),
    );
}
```

{!# guide-step: responses | Return data through REST response objects #!}
Callbacks return data; they do not `echo`, call `die`, or use `wp_send_json()`. Use `WP_Error` for actionable errors and `WP_REST_Response` when status or headers matter.

```php
<?php
public function index( \WP_REST_Request $request ): \WP_REST_Response {
    $posts = get_posts(
        array(
            'post_type'      => 'cn_notice',
            'post_status'    => 'publish',
            'posts_per_page' => (int) $request['per_page'],
            'no_found_rows'  => true,
        )
    );

    $items = array_map(
        static fn ( \WP_Post $post ): array => array(
            'id'      => $post->ID,
            'title'   => get_the_title( $post ),
            'message' => wp_strip_all_tags( $post->post_content ),
        ),
        $posts
    );

    return new \WP_REST_Response( $items, 200 );
}

public function store( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
    $post_id = wp_insert_post(
        array(
            'post_type'    => 'cn_notice',
            'post_status'  => 'publish',
            'post_title'   => (string) $request['title'],
            'post_content' => (string) $request['message'],
        ),
        true
    );

    if ( is_wp_error( $post_id ) ) {
        return new \WP_Error(
            'cn_notice_not_created',
            __( 'The notice could not be created.', 'cn-notices' ),
            array( 'status' => 500 )
        );
    }

    return new \WP_REST_Response( array( 'id' => $post_id ), 201 );
}
```

{!# guide-step: wordpress-client | Read the endpoint inside WordPress #!}
Use `@wordpress/api-fetch` for an admin or editor screen. Its nonce middleware works with the REST nonce supplied by WordPress. Present loading and failure states in text and use a polite live region for asynchronous status.

```js
import apiFetch from '@wordpress/api-fetch';

export async function loadNotices(signal) {
    return apiFetch({
        path: '/cn/v1/notices?per_page=10&_fields=id,title,message',
        signal,
    });
}

export async function createNotice(title, message) {
    return apiFetch({
        path: '/cn/v1/notices',
        method: 'POST',
        data: { title, message },
    });
}
```

```jsx
<p role="status" aria-live="polite">
    {isLoading ? 'Loading notices…' : statusMessage}
</p>
```

{!# guide-step: external-client | Read public data with browser JavaScript #!}
External public consumers can use `fetch` without credentials. Set a timeout, check the status before parsing, encode query parameters through `URL`, and avoid exposing Application Passwords in browser code.

```js
export async function fetchNotices(origin) {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 5000);
    const url = new URL('/wp-json/cn/v1/notices', origin);
    url.searchParams.set('per_page', '10');
    url.searchParams.set('_fields', 'id,title,message');

    try {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`Notices request failed with ${response.status}`);
        }

        return await response.json();
    } finally {
        window.clearTimeout(timeout);
    }
}
```

{!# guide-step: verify | Test success, denial, invalid input, and scale #!}
Exercise the endpoint as anonymous, subscriber, editor and administrator. Check the schema, maximum page size, absent records, malformed values, authentication and caching. REST nonces prevent CSRF; the capability callback performs authorization.

```bash
wp rest route list | grep '/cn/v1/notices'
curl --fail 'https://example.com/wp-json/cn/v1/notices?per_page=2&_fields=id,title'
curl --request POST 'https://example.com/wp-json/cn/v1/notices' \
  --header 'Content-Type: application/json' \
  --data '{"title":"Denied","message":"Anonymous writes must fail."}'
```

```text
- anonymous GET succeeds only with deliberately public fields
- anonymous and underprivileged POST return 401/403
- missing, oversized, and wrong-type arguments return useful 400 errors
- callbacks never print or terminate the request directly
- queries are bounded and indexed for their real access pattern
- loading, empty, error, and success states are perceivable without colour alone
```
GUIDE,
        ];
    }

    /** @return array{filename: string, title: string, description: string, tags: list<string>, content: string} */
    private function customTableGuide(): array
    {
        return [
            'filename' => '07-custom-field-database-table.guide.md',
            'title' => 'Create a custom field and database table',
            'description' => 'Create a versioned custom table, repository, protected REST API, accessible admin field UI, and dynamic front-end table.',
            'tags' => ['custom-fields', 'database', 'rest-api', 'blocks', 'plugin-development', 'security', 'wordpress-vip'],
            'content' => <<<'GUIDE'
{!# guide-step: justify | Prove that a custom table is warranted #!}
Target WordPress 7.0.2 stable and test the WordPress 7.1 compatibility lane. Ordinary fields attached to a post should normally use registered post meta. Choose a custom table only when independent row lifecycle, relationship volume, retention rules, or indexed query patterns make meta impractical.

Document the decision before writing schema. This example stores multiple structured service records per post and needs ordered, indexed queries. It uses the modern REST API rather than legacy `admin-ajax.php` for the editor UI.

```text
Table: {$wpdb->prefix}cn_service_records
Relationship: many records belong to one post
Primary read: published records by post_id, ordered by sort_order then id
Write permission: current_user_can('edit_post', $post_id)
Retention: retained on deactivation; explicit opt-in cleanup on uninstall
Personal data: none permitted in label or value fields
```

{!# guide-step: structure | Separate schema, storage, API, UI, and rendering #!}
A small separation keeps database access reviewable and makes migrations testable. Prefix WordPress-global identifiers and namespace PHP classes.

```text
cn-service-records/
├── cn-service-records.php
├── includes/
│   ├── class-schema.php
│   ├── class-records-repository.php
│   └── class-rest-controller.php
├── src/
│   └── admin.js
├── build/
│   └── admin.js
└── blocks/
    └── records-table/
        ├── block.json
        └── render.php
```

```php
<?php
/**
 * Plugin Name: CN Service Records
 * Version: 1.0.0
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Text Domain: cn-service-records
 */

namespace CN\ServiceRecords;

defined( 'ABSPATH' ) || exit;
define( 'CN_SERVICE_RECORDS_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-schema.php';
require_once __DIR__ . '/includes/class-records-repository.php';
require_once __DIR__ . '/includes/class-rest-controller.php';
```

{!# guide-step: schema | Create a prefixed, versioned table with dbDelta #!}
Use `$wpdb->prefix`, the site's charset/collation, and indexes matching the documented queries. `dbDelta()` is particular about SQL layout, including the two spaces after `PRIMARY KEY`. Store a schema version so upgrades do not rely on activation hooks, which are not run when a plugin updates.

```php
<?php
namespace CN\ServiceRecords;

final class Schema {
    private const VERSION = '1.0.0';
    private const OPTION  = 'cn_service_records_schema_version';

    public static function table_name(): string {
        global $wpdb;

        return $wpdb->prefix . 'cn_service_records';
    }

    public static function maybe_upgrade(): void {
        if ( self::VERSION === get_option( self::OPTION ) ) {
            return;
        }

        self::install();
    }

    public static function install(): void {
        global $wpdb;

        $table             = self::table_name();
        $charset_collate   = $wpdb->get_charset_collate();
        $create_table_sql  = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            label varchar(190) NOT NULL,
            value text NOT NULL,
            sort_order int(10) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY post_order (post_id, sort_order, id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $create_table_sql );

        update_option( self::OPTION, self::VERSION, false );
    }
}
```

```php
<?php
register_activation_hook( __FILE__, array( \CN\ServiceRecords\Schema::class, 'install' ) );
add_action( 'plugins_loaded', array( \CN\ServiceRecords\Schema::class, 'maybe_upgrade' ) );
```

For multisite network activation, iterate existing sites in bounded batches and switch blog context before installation; also hook new-site creation. Never assume a literal `wp_` prefix.

{!# guide-step: repository | Encapsulate prepared reads and formatted writes #!}
Keep SQL in a repository, use explicit formats, and prepare dynamic values. Cache repeat reads and invalidate the one affected post after a successful write. Do not cache permission decisions.

```php
<?php
namespace CN\ServiceRecords;

final class Records_Repository {
    /** @return list<array{id: int, label: string, value: string, sort_order: int}> */
    public function for_post( int $post_id ): array {
        global $wpdb;

        $cache_key = 'post_' . $post_id;
        $cached    = wp_cache_get( $cache_key, 'cn_service_records' );

        if ( is_array( $cached ) ) {
            return $cached;
        }

        $table = Schema::table_name();
        $sql   = $wpdb->prepare(
            "SELECT id, label, value, sort_order FROM {$table} WHERE post_id = %d ORDER BY sort_order ASC, id ASC LIMIT 100",
            $post_id
        );
        $rows  = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
        $rows  = is_array( $rows ) ? $rows : array();

        wp_cache_set( $cache_key, $rows, 'cn_service_records', HOUR_IN_SECONDS );

        return $rows;
    }

    public function create( int $post_id, string $label, string $value, int $sort_order ): int|\WP_Error {
        global $wpdb;

        $inserted = $wpdb->insert(
            Schema::table_name(),
            array(
                'post_id'    => $post_id,
                'label'      => $label,
                'value'      => $value,
                'sort_order' => $sort_order,
                'created_at' => current_time( 'mysql', true ),
                'updated_at' => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return new \WP_Error( 'cn_record_not_saved', __( 'The record could not be saved.', 'cn-service-records' ) );
        }

        wp_cache_delete( 'post_' . $post_id, 'cn_service_records' );

        return (int) $wpdb->insert_id;
    }
}
```

{!# guide-step: api | Add protected read and write REST routes #!}
Expose only the fields the UI needs. Authorize against the related post object, not a role name or login check. Argument schemas validate and sanitize before the callback. REST cookie authentication verifies its nonce before this capability callback runs.

```php
<?php
namespace CN\ServiceRecords;

final class REST_Controller extends \WP_REST_Controller {
    public function __construct( private readonly Records_Repository $repository ) {}

    public function register_routes(): void {
        register_rest_route(
            'cn/v1',
            '/posts/(?P<post_id>\d+)/service-records',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'index' ),
                    'permission_callback' => static fn ( \WP_REST_Request $request ): bool => current_user_can( 'edit_post', (int) $request['post_id'] ),
                    'args'                => array(
                        'post_id' => array( 'type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint' ),
                    ),
                ),
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'store' ),
                    'permission_callback' => static fn ( \WP_REST_Request $request ): bool => current_user_can( 'edit_post', (int) $request['post_id'] ),
                    'args'                => array(
                        'post_id'    => array( 'type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint' ),
                        'label'      => array( 'type' => 'string', 'required' => true, 'maxLength' => 190, 'sanitize_callback' => 'sanitize_text_field' ),
                        'value'      => array( 'type' => 'string', 'required' => true, 'maxLength' => 2000, 'sanitize_callback' => 'sanitize_textarea_field' ),
                        'sort_order' => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0, 'sanitize_callback' => 'absint' ),
                    ),
                ),
            )
        );
    }

    public function index( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $post_id = (int) $request['post_id'];

        if ( ! get_post( $post_id ) ) {
            return new \WP_Error( 'cn_post_not_found', __( 'The post does not exist.', 'cn-service-records' ), array( 'status' => 404 ) );
        }

        return new \WP_REST_Response( $this->repository->for_post( $post_id ), 200 );
    }

    public function store( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $record_id = $this->repository->create(
            (int) $request['post_id'],
            (string) $request['label'],
            (string) $request['value'],
            (int) $request['sort_order']
        );

        if ( is_wp_error( $record_id ) ) {
            $record_id->add_data( array( 'status' => 500 ) );

            return $record_id;
        }

        return new \WP_REST_Response( array( 'id' => $record_id ), 201 );
    }
}

add_action( 'rest_api_init', static function (): void {
    ( new REST_Controller( new Records_Repository() ) )->register_routes();
} );
```

{!# guide-step: admin-assets | Load the field UI only in the block editor #!}
Enqueue the compiled UI only for supported post-editor screens. Asset metadata generated by `@wordpress/scripts` supplies dependencies and a cache-safe version.

```php
<?php
add_action( 'enqueue_block_editor_assets', static function (): void {
    $screen = get_current_screen();

    if ( ! $screen || 'post' !== $screen->base || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
        return;
    }

    $asset = require plugin_dir_path( CN_SERVICE_RECORDS_FILE ) . 'build/admin.asset.php';

    wp_enqueue_script(
        'cn-service-records-editor',
        plugins_url( 'build/admin.js', CN_SERVICE_RECORDS_FILE ),
        $asset['dependencies'],
        $asset['version'],
        true
    );
} );
```

{!# guide-step: field-ui | Build an accessible editor field panel #!}
Use WordPress components so labels, descriptions, focus, disabled state, and error notices follow editor conventions. `apiFetch` handles the same-origin REST nonce. Keep status text in a polite live region and move focus to invalid input only when it helps the user recover.

```jsx
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, PanelBody, TextControl, TextareaControl } from '@wordpress/components';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';

function ServiceRecordPanel() {
    const postId = useSelect((select) => select('core/editor').getCurrentPostId(), []);
    const [label, setLabel] = useState('');
    const [value, setValue] = useState('');
    const [status, setStatus] = useState('');
    const [error, setError] = useState('');
    const [isSaving, setIsSaving] = useState(false);

    async function saveRecord() {
        setError('');

        if (!label.trim() || !value.trim()) {
            setError('Enter both a label and a value.');
            return;
        }

        setIsSaving(true);
        setStatus('Saving service record…');

        try {
            await apiFetch({
                path: `/cn/v1/posts/${postId}/service-records`,
                method: 'POST',
                data: { label, value, sort_order: 0 },
            });
            setLabel('');
            setValue('');
            setStatus('Service record saved.');
        } catch (requestError) {
            setError(requestError?.message || 'The service record could not be saved.');
            setStatus('');
        } finally {
            setIsSaving(false);
        }
    }

    return (
        <PluginDocumentSettingPanel name="cn-service-record" title="Service record">
            {error && <Notice status="error" isDismissible={false}>{error}</Notice>}
            <TextControl label="Label" help="A short public heading." value={label} onChange={setLabel} />
            <TextareaControl label="Value" help="Plain-text information shown below the label." value={value} onChange={setValue} />
            <Button variant="primary" disabled={isSaving} isBusy={isSaving} onClick={saveRecord}>
                Save record
            </Button>
            <p role="status" aria-live="polite">{status}</p>
        </PluginDocumentSettingPanel>
    );
}

registerPlugin('cn-service-records', { render: ServiceRecordPanel });
```

{!# guide-step: frontend | Render the records through a dynamic block #!}
Register a dynamic `cn/service-records-table` block with `apiVersion: 3` and a `postId` attribute. The render callback reads through the repository and escapes at the final HTML context. Use a list for label/value pairs unless real column relationships make a table the clearest structure.

```php
<?php
$post_id = isset( $attributes['postId'] ) ? absint( $attributes['postId'] ) : get_the_ID();
$repository = new \CN\ServiceRecords\Records_Repository();
$records = $repository->for_post( $post_id );

if ( array() === $records ) {
    return;
}
?>
<section <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core returns escaped attributes. ?>>
    <h2><?php echo esc_html__( 'Service information', 'cn-service-records' ); ?></h2>
    <dl class="cn-service-records">
        <?php foreach ( $records as $record ) : ?>
            <div class="cn-service-records__item">
                <dt><?php echo esc_html( $record['label'] ); ?></dt>
                <dd><?php echo esc_html( $record['value'] ); ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
</section>
```

If the product genuinely needs a comparison table, add a visible `<caption>`, use `<th scope="col">` and `<th scope="row">`, and keep cells associated at narrow zoom widths rather than replacing table semantics with anonymous divs.

{!# guide-step: operations | Plan upgrades, privacy, multisite, and removal #!}
Keep data on deactivation so a temporary plugin disable does not destroy it. If uninstall cleanup is offered, make it an explicit setting and document that deletion cannot be undone. Register privacy exporter/eraser callbacks if the schema later contains personal data.

On WordPress VIP, review custom-table creation before deployment, ensure `$wpdb->prefix` is used, validate indexes against real queries, and cache repeat reads. Avoid schema changes during high-traffic web requests; deploy controlled migrations and make them backward-compatible.

```text
Operational checklist
- schema version can migrate forward from every supported release
- migration can safely run twice
- table name follows the active site prefix
- indexes match EXPLAIN for actual reads
- deletes define what happens to rows when a post is deleted
- cache keys are invalidated after insert/update/delete
- privacy/export/erasure ownership is documented
- uninstall behaviour is explicit and tested
```

{!# guide-step: verify | Test storage, permissions, migration, and UI access #!}
Automate happy paths and failures: empty values, oversized values, duplicate submits, missing posts, insufficient capability, database failure, absent rows, cache invalidation, an older schema version, and multisite prefixes. Test keyboard, zoom, error announcements and visible labels in the editor iframe.

```bash
wp db query 'SHOW CREATE TABLE '"$(wp db prefix)"'cn_service_records'
wp rest route list | grep '/cn/v1/posts'
wp cache flush
```

```text
- subscriber cannot read or write editor-only values
- editor can save only against posts they may edit
- database writes use explicit formats and reads are prepared/bounded
- failed writes return useful REST errors without leaking SQL details
- dynamic output is escaped and contains valid semantic structure
- UI works with keyboard only, 200% zoom, and screen-reader announcements
- WordPress 7.0.2 and the latest 7.1 compatibility lane both pass
```
GUIDE,
        ];
    }
}
