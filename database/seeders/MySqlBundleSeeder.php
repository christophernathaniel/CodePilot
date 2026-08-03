<?php

namespace Database\Seeders;

use App\Models\Folder;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\Tag;
use App\Models\User;
use App\Support\Snippets\SnippetLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MySqlBundleSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', 'dev@dev.dev')->firstOrFail();

        DB::transaction(function () use ($user): void {
            $project = Project::query()->withTrashed()->firstOrCreate(
                ['user_id' => $user->id, 'name' => 'MySQL'],
                [
                    'kind' => Project::KIND_BUNDLE,
                    'description' => 'A progressive MySQL 8 reference from safe setup and inspection through schema design, advanced queries, JSON, and query-plan diagnostics.',
                    'position' => 10,
                ],
            );
            $project->restore();
            $project->update([
                'kind' => Project::KIND_BUNDLE,
                'description' => 'A progressive MySQL 8 reference from safe setup and inspection through schema design, advanced queries, JSON, and query-plan diagnostics.',
            ]);

            $folders = $this->folders($project);
            $tags = $this->tags($user);

            foreach ($this->files() as $file) {
                $folder = $folders[$file['folder']];
                $locationKey = SnippetLocation::key($project->id, $folder->id);
                $snippet = Snippet::query()->withTrashed()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'location_key' => $locationKey,
                        'filename' => $file['filename'],
                    ],
                    [
                        'project_id' => $project->id,
                        'folder_id' => $folder->id,
                        'content_type' => Snippet::CONTENT_TYPE_SNIPPET,
                        'title' => $file['title'],
                        'language' => 'sql',
                        'description' => $file['description'],
                        'position' => $file['position'],
                    ],
                );
                $snippet->restore();

                $variation = $snippet->variations()->updateOrCreate(
                    ['name' => 'MySQL 8 default'],
                    [
                        'created_by_id' => $user->id,
                        'content' => $file['content'],
                        'position' => 0,
                        'is_default' => true,
                    ],
                );
                $snippet->variations()
                    ->whereKeyNot($variation->id)
                    ->update(['is_default' => false]);

                $snippet->tags()->sync(
                    collect(['mysql', 'sql', ...$file['tags']])
                        ->unique()
                        ->map(fn (string $slug): int => $tags[$slug]->id)
                        ->all(),
                );
            }
        });
    }

    /** @return array<string, Folder> */
    private function folders(Project $project): array
    {
        $definitions = [
            'setup' => ['01-setup-and-inspection', 0],
            'schema' => ['02-schema-and-data', 1],
            'queries' => ['03-querying-and-analysis', 2],
            'performance' => ['04-json-and-performance', 3],
        ];

        return collect($definitions)->mapWithKeys(function (array $definition, string $key) use ($project): array {
            $folder = Folder::query()->withTrashed()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'parent_id' => null,
                    'name' => $definition[0],
                ],
                ['position' => $definition[1]],
            );
            $folder->restore();

            return [$key => $folder];
        })->all();
    }

    /** @return array<string, Tag> */
    private function tags(User $user): array
    {
        $definitions = [
            'mysql' => ['MySQL', '#4479a1'],
            'sql' => ['SQL', '#60a5fa'],
            'database' => ['Database', '#0f766e'],
            'security' => ['Security', '#b42318'],
            'administration' => ['Administration', '#475569'],
            'inspection' => ['Inspection', '#64748b'],
            'information-schema' => ['Information Schema', '#0891b2'],
            'schema-design' => ['Schema Design', '#4f46e5'],
            'constraints' => ['Constraints', '#6366f1'],
            'indexing' => ['Indexing', '#0284c7'],
            'crud' => ['CRUD', '#14b8a6'],
            'upsert' => ['Upsert', '#0d9488'],
            'transactions' => ['Transactions', '#7c3aed'],
            'locking' => ['Locking', '#9333ea'],
            'joins' => ['Joins', '#2563eb'],
            'aggregation' => ['Aggregation', '#0369a1'],
            'cte' => ['CTE', '#8b5cf6'],
            'window-functions' => ['Window Functions', '#a855f7'],
            'pagination' => ['Pagination', '#0ea5e9'],
            'json' => ['JSON', '#22c55e'],
            'performance' => ['Performance', '#0f766e'],
            'diagnostics' => ['Diagnostics', '#f59e0b'],
        ];

        return collect($definitions)->mapWithKeys(function (array $definition, string $slug) use ($user): array {
            $tag = Tag::query()->updateOrCreate(
                ['user_id' => $user->id, 'slug' => $slug],
                ['name' => $definition[0], 'color' => $definition[1]],
            );

            return [$slug => $tag];
        })->all();
    }

    /**
     * @return list<array{
     *     folder: string,
     *     filename: string,
     *     title: string,
     *     description: string,
     *     position: int,
     *     tags: list<string>,
     *     content: string
     * }>
     */
    private function files(): array
    {
        return [
            [
                'folder' => 'setup',
                'filename' => '01-database-user-and-grants.sql',
                'title' => 'Database, User and Least-Privilege Grants',
                'description' => 'Create a UTF-8 database and placeholder runtime account, then grant and verify only the privileges the application needs.',
                'position' => 0,
                'tags' => ['database', 'security', 'administration'],
                'content' => <<<'SQL'
-- Replace every template value before execution. Never commit a real password.

-- {!# snippet: create_utf8mb4_database #!}
CREATE DATABASE IF NOT EXISTS `{{{database_name:app_data}}}`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_0900_ai_ci;

-- {!# snippet: create_placeholder_runtime_user #!}
-- Use a secret manager-generated password and the narrowest practical host pattern.
CREATE USER IF NOT EXISTS
    '{{{app_user:app_runtime}}}'@'{{{app_host:10.%}}}'
    IDENTIFIED BY '{{{app_password:CHANGE_ME_WITH_A_RANDOM_SECRET}}}';

-- {!# snippet: grant_least_privilege_access #!}
-- Runtime code normally needs data privileges, not CREATE, ALTER, DROP, or GRANT OPTION.
GRANT SELECT, INSERT, UPDATE, DELETE
    ON `{{{database_name:app_data}}}`.*
    TO '{{{app_user:app_runtime}}}'@'{{{app_host:10.%}}}';

-- {!# snippet: verify_and_revoke_grants #!}
SHOW GRANTS FOR '{{{app_user:app_runtime}}}'@'{{{app_host:10.%}}}';

-- DESTRUCTIVE ACCESS CHANGE: review dependencies before uncommenting a revoke.
-- REVOKE ALL PRIVILEGES, GRANT OPTION
--     FROM '{{{app_user:app_runtime}}}'@'{{{app_host:10.%}}}';
SQL,
            ],
            [
                'folder' => 'setup',
                'filename' => '02-inspect-server-and-schema.sql',
                'title' => 'Inspect the MySQL Server and Schema',
                'description' => 'Use SHOW, DESCRIBE, SHOW CREATE, and information_schema to understand an unfamiliar MySQL 8 database safely.',
                'position' => 1,
                'tags' => ['inspection', 'information-schema', 'administration'],
                'content' => <<<'SQL'
-- These statements are read-only and are safe starting points on an unfamiliar server.

-- {!# snippet: inspect_server_context #!}
SELECT
    VERSION() AS mysql_version,
    DATABASE() AS selected_database,
    CURRENT_USER() AS authenticated_account,
    USER() AS client_identity,
    @@transaction_isolation AS transaction_isolation;

-- {!# snippet: show_and_describe_objects #!}
SHOW DATABASES;
SHOW FULL TABLES FROM `{{{database_name:app_data}}}`;
DESCRIBE `{{{database_name:app_data}}}`.`orders`;

-- {!# snippet: inspect_canonical_table_ddl #!}
SHOW CREATE TABLE `{{{database_name:app_data}}}`.`orders`;

-- {!# snippet: inventory_information_schema #!}
SELECT
    table_name,
    engine,
    table_rows,
    data_length,
    index_length,
    table_collation
FROM information_schema.tables
WHERE table_schema = '{{{database_name:app_data}}}'
ORDER BY table_name;
SQL,
            ],
            [
                'folder' => 'schema',
                'filename' => '01-commerce-schema-and-indexes.sql',
                'title' => 'Commerce Tables, Foreign Keys and Indexes',
                'description' => 'Build a normalized MySQL 8 example schema with constraints, deliberate foreign-key actions, and query-led indexes.',
                'position' => 0,
                'tags' => ['schema-design', 'constraints', 'indexing'],
                'content' => <<<'SQL'
USE `{{{database_name:app_data}}}`;

-- {!# snippet: create_customers_table #!}
CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(254) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_email (email)
) ENGINE=InnoDB;

-- {!# snippet: create_products_table #!}
CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sku VARCHAR(64) NOT NULL,
    name VARCHAR(180) NOT NULL,
    category VARCHAR(80) NOT NULL,
    price_cents INT UNSIGNED NOT NULL,
    stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    attributes JSON NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_sku (sku),
    CONSTRAINT chk_products_price CHECK (price_cents > 0)
) ENGINE=InnoDB;

-- {!# snippet: create_orders_table_with_foreign_key #!}
CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'paid', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_orders_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

-- {!# snippet: create_order_items_table_with_composite_key #!}
CREATE TABLE IF NOT EXISTS order_items (
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL,
    unit_price_cents INT UNSIGNED NOT NULL,
    PRIMARY KEY (order_id, product_id),
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
    CONSTRAINT chk_order_items_quantity CHECK (quantity > 0)
) ENGINE=InnoDB;

-- {!# snippet: add_query_led_indexes #!}
CREATE INDEX idx_orders_customer_created
    ON orders (customer_id, created_at DESC, id DESC);
CREATE INDEX idx_orders_status_created
    ON orders (status, created_at DESC, id DESC);
CREATE INDEX idx_products_category_active
    ON products (category, is_active, id);
SQL,
            ],
            [
                'folder' => 'schema',
                'filename' => '02-crud-upsert-and-transactions.sql',
                'title' => 'CRUD, Upserts, Transactions and Savepoints',
                'description' => 'Create and change data safely with bounded predicates, MySQL 8 row-alias upserts, atomic transactions, locks, and savepoints.',
                'position' => 1,
                'tags' => ['crud', 'upsert', 'transactions', 'locking', 'security'],
                'content' => <<<'SQL'
USE `{{{database_name:app_data}}}`;

-- {!# snippet: insert_example_rows #!}
INSERT INTO customers (email, display_name)
VALUES ('person@example.test', 'Example Person');

INSERT INTO products (sku, name, category, price_cents, stock_quantity)
VALUES ('DEMO-001', 'Demonstration product', 'examples', 2500, 25);

-- {!# snippet: read_and_update_by_stable_key #!}
SELECT id, sku, name, price_cents, stock_quantity
FROM products
WHERE sku = 'DEMO-001';

UPDATE products
SET price_cents = 2750
WHERE sku = 'DEMO-001';

-- {!# snippet: upsert_with_mysql_8_row_alias #!}
INSERT INTO products (sku, name, category, price_cents, stock_quantity)
VALUES ('DEMO-001', 'Updated demonstration product', 'examples', 2750, 30) AS incoming
ON DUPLICATE KEY UPDATE
    name = incoming.name,
    category = incoming.category,
    price_cents = incoming.price_cents,
    stock_quantity = incoming.stock_quantity;

-- {!# snippet: delete_with_preview_and_rollback #!}
-- DESTRUCTIVE: preview the exact rows and keep ROLLBACK until the result is verified.
START TRANSACTION;
SELECT id, sku, name FROM products WHERE sku = 'DEMO-001' FOR UPDATE;
DELETE FROM products WHERE sku = 'DEMO-001' AND id = {{{product_id:1}}};
SELECT ROW_COUNT() AS deleted_rows;
ROLLBACK;
-- Replace ROLLBACK with COMMIT only after review and a usable backup.

-- {!# snippet: create_order_atomically #!}
START TRANSACTION;
INSERT INTO orders (customer_id, status)
VALUES ({{{customer_id:1}}}, 'pending');
SET @new_order_id = LAST_INSERT_ID();
INSERT INTO order_items (order_id, product_id, quantity, unit_price_cents)
VALUES (@new_order_id, {{{product_id:1}}}, 2, 2750);
UPDATE products
SET stock_quantity = stock_quantity - 2
WHERE id = {{{product_id:1}}} AND stock_quantity >= 2;
COMMIT;

-- {!# snippet: lock_and_use_savepoint #!}
START TRANSACTION;
SELECT id, stock_quantity
FROM products
WHERE id = {{{product_id:1}}}
FOR UPDATE;
SAVEPOINT before_stock_change;
UPDATE products SET stock_quantity = stock_quantity - 1 WHERE id = {{{product_id:1}}};
ROLLBACK TO SAVEPOINT before_stock_change;
RELEASE SAVEPOINT before_stock_change;
COMMIT;
SQL,
            ],
            [
                'folder' => 'queries',
                'filename' => '01-joins-and-aggregation.sql',
                'title' => 'Joins and Aggregation',
                'description' => 'Combine normalized commerce data with inner joins, left joins, grouped totals, HAVING, and conditional aggregation.',
                'position' => 0,
                'tags' => ['joins', 'aggregation'],
                'content' => <<<'SQL'
USE `{{{database_name:app_data}}}`;

-- {!# snippet: inner_join_orders_to_customers #!}
SELECT o.id, o.status, o.created_at, c.email, c.display_name
FROM orders AS o
INNER JOIN customers AS c ON c.id = o.customer_id
ORDER BY o.created_at DESC, o.id DESC;

-- {!# snippet: left_join_find_customers_without_orders #!}
SELECT c.id, c.email
FROM customers AS c
LEFT JOIN orders AS o ON o.customer_id = c.id
WHERE o.id IS NULL
ORDER BY c.id;

-- {!# snippet: aggregate_order_totals_with_having #!}
SELECT
    o.id,
    c.email,
    SUM(oi.quantity * oi.unit_price_cents) AS total_cents
FROM orders AS o
JOIN customers AS c ON c.id = o.customer_id
JOIN order_items AS oi ON oi.order_id = o.id
GROUP BY o.id, c.email
HAVING total_cents >= {{{minimum_total_cents:5000}}}
ORDER BY total_cents DESC, o.id DESC;

-- {!# snippet: conditional_status_aggregation #!}
SELECT
    customer_id,
    COUNT(*) AS order_count,
    SUM(status = 'paid') AS paid_count,
    SUM(status = 'cancelled') AS cancelled_count,
    MAX(created_at) AS latest_order_at
FROM orders
GROUP BY customer_id
ORDER BY order_count DESC, customer_id;
SQL,
            ],
            [
                'folder' => 'queries',
                'filename' => '02-ctes-windows-top-n-and-keyset.sql',
                'title' => 'CTEs, Window Functions, Top-N and Keyset Pagination',
                'description' => 'Use MySQL 8 CTEs and windows for reusable analytics, top-N-per-group queries, running totals, and stable keyset pagination.',
                'position' => 1,
                'tags' => ['cte', 'window-functions', 'pagination', 'performance'],
                'content' => <<<'SQL'
USE `{{{database_name:app_data}}}`;

-- {!# snippet: cte_order_totals #!}
WITH order_totals AS (
    SELECT order_id, SUM(quantity * unit_price_cents) AS total_cents
    FROM order_items
    GROUP BY order_id
)
SELECT o.id, o.status, ot.total_cents
FROM orders AS o
JOIN order_totals AS ot ON ot.order_id = o.id
WHERE ot.total_cents >= {{{minimum_total_cents:5000}}}
ORDER BY ot.total_cents DESC, o.id DESC;

-- {!# snippet: running_customer_spend_window #!}
SELECT
    o.customer_id,
    o.id AS order_id,
    o.created_at,
    SUM(oi.quantity * oi.unit_price_cents) AS order_total_cents,
    SUM(SUM(oi.quantity * oi.unit_price_cents)) OVER (
        PARTITION BY o.customer_id
        ORDER BY o.created_at, o.id
    ) AS running_total_cents
FROM orders AS o
JOIN order_items AS oi ON oi.order_id = o.id
GROUP BY o.customer_id, o.id, o.created_at;

-- {!# snippet: top_three_products_per_category #!}
WITH ranked_products AS (
    SELECT
        p.category,
        p.id,
        p.name,
        SUM(oi.quantity) AS units_sold,
        ROW_NUMBER() OVER (
            PARTITION BY p.category
            ORDER BY SUM(oi.quantity) DESC, p.id
        ) AS sales_rank
    FROM products AS p
    JOIN order_items AS oi ON oi.product_id = p.id
    GROUP BY p.category, p.id, p.name
)
SELECT category, id, name, units_sold, sales_rank
FROM ranked_products
WHERE sales_rank <= 3
ORDER BY category, sales_rank;

-- {!# snippet: first_keyset_page #!}
SELECT id, customer_id, status, created_at
FROM orders
ORDER BY created_at DESC, id DESC
LIMIT {{{page_size:50}}};

-- {!# snippet: next_keyset_page #!}
-- Pass the final created_at and id from the previous page; do not use OFFSET at scale.
SELECT id, customer_id, status, created_at
FROM orders
WHERE (created_at, id) < (
    '{{{cursor_created_at:2026-01-01 00:00:00}}}',
    {{{cursor_id:1000}}}
)
ORDER BY created_at DESC, id DESC
LIMIT {{{page_size:50}}};
SQL,
            ],
            [
                'folder' => 'performance',
                'filename' => '01-json-operations-and-indexable-fields.sql',
                'title' => 'JSON Operations and Indexable Generated Fields',
                'description' => 'Write, query, tabularize, and selectively index MySQL JSON data without turning every relational field into JSON.',
                'position' => 0,
                'tags' => ['json', 'indexing', 'schema-design'],
                'content' => <<<'SQL'
USE `{{{database_name:app_data}}}`;

-- {!# snippet: write_json_object #!}
UPDATE products
SET attributes = JSON_OBJECT(
    'colour', 'blue',
    'dimensions', JSON_OBJECT('width_mm', 120, 'height_mm', 80),
    'labels', JSON_ARRAY('featured', 'summer')
)
WHERE sku = 'DEMO-001';

-- {!# snippet: patch_and_remove_json_paths #!}
UPDATE products
SET attributes = JSON_REMOVE(
    JSON_SET(attributes, '$.colour', 'navy', '$.material', 'cotton'),
    '$.legacy_code'
)
WHERE sku = 'DEMO-001';

-- {!# snippet: filter_and_expand_json #!}
SELECT id, sku, attributes->>'$.colour' AS colour
FROM products
WHERE JSON_CONTAINS(attributes->'$.labels', JSON_QUOTE('featured'));

SELECT p.id, p.sku, label.value AS label
FROM products AS p
JOIN JSON_TABLE(
    p.attributes,
    '$.labels[*]' COLUMNS (value VARCHAR(80) PATH '$')
) AS label
WHERE p.attributes IS NOT NULL;

-- {!# snippet: add_indexable_generated_json_field #!}
-- SCHEMA CHANGE: inspect existing columns/indexes and test on a production-sized copy first.
ALTER TABLE products
    ADD COLUMN attribute_colour VARCHAR(32)
        GENERATED ALWAYS AS (
            JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.colour'))
        ) STORED,
    ADD INDEX idx_products_attribute_colour (attribute_colour);
SQL,
            ],
            [
                'folder' => 'performance',
                'filename' => '02-explain-analyze-and-index-diagnostics.sql',
                'title' => 'EXPLAIN ANALYZE and Index Diagnostics',
                'description' => 'Read estimated and actual MySQL 8 query plans, inspect index order/cardinality, and use sys and Performance Schema evidence before changing indexes.',
                'position' => 1,
                'tags' => ['performance', 'diagnostics', 'indexing', 'inspection'],
                'content' => <<<'SQL'
USE `{{{database_name:app_data}}}`;

-- {!# snippet: explain_tree_without_execution #!}
EXPLAIN FORMAT=TREE
SELECT id, customer_id, status, created_at
FROM orders
WHERE customer_id = {{{customer_id:1}}}
ORDER BY created_at DESC, id DESC
LIMIT 50;

-- {!# snippet: explain_analyze_actual_work #!}
-- EXPLAIN ANALYZE executes the SELECT. Never apply it blindly to a mutating statement.
EXPLAIN ANALYZE
SELECT id, customer_id, status, created_at
FROM orders
WHERE customer_id = {{{customer_id:1}}}
ORDER BY created_at DESC, id DESC
LIMIT 50;

-- {!# snippet: inspect_index_columns_and_cardinality #!}
SELECT
    index_name,
    seq_in_index,
    column_name,
    cardinality,
    non_unique,
    is_visible
FROM information_schema.statistics
WHERE table_schema = '{{{database_name:app_data}}}'
  AND table_name = 'orders'
ORDER BY index_name, seq_in_index;

-- {!# snippet: inspect_unused_and_redundant_indexes #!}
-- Treat sys-schema results as evidence to investigate, not an automatic DROP list.
SELECT *
FROM sys.schema_unused_indexes
WHERE object_schema = '{{{database_name:app_data}}}';

SELECT *
FROM sys.schema_redundant_indexes
WHERE table_schema = '{{{database_name:app_data}}}';

-- {!# snippet: inspect_expensive_statement_digests #!}
SELECT
    digest_text,
    count_star,
    ROUND(sum_timer_wait / 1000000000000, 3) AS total_seconds,
    sum_rows_examined,
    sum_rows_sent
FROM performance_schema.events_statements_summary_by_digest
WHERE schema_name = '{{{database_name:app_data}}}'
ORDER BY sum_timer_wait DESC
LIMIT 20;

-- DESTRUCTIVE: never DROP an index from a single snapshot. Verify query plans,
-- peak-period evidence, replicas, foreign-key requirements, and rollback first.
SQL,
            ],
        ];
    }
}
