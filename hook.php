<?php

/**
 -------------------------------------------------------------------------
 LICENSE

 This file is part of Transferticketentity plugin for GLPI.

 Transferticketentity is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 @category  Ticket
 @package   Transferticketentity
 @author    Giovanny Rodriguez <giovanny.rodriguez@imagunet.com>
 @author    Santiago Gomez <santiago.gomez@imagunet.com>
 @author    Juan Gallego <juan.gallego@imagunet.com>
 @copyright 2026 IMAGUNET S.A.S
 @license   AGPL License 3.0 or (at your option) any later version
            https://www.gnu.org/licenses/agpl-3.0.html
 @link      https://www.imagunet.com
 --------------------------------------------------------------------------
*/

use GlpiPlugin\Transferticketentity\Profile;

/**
 * Install hook — compatible with GLPI 11.
 *
 * GLPI 11 rules:
 *  - $DB->query() / $DB->queryOrDie()  → FORBIDDEN from plugins.
 *  - $DB->doQuery()                    → allowed for CREATE/ALTER TABLE.
 *  - $DB->tableExists()                → allowed and recommended.
 *  - $DB->indexExists()                → DOES NOT EXIST in GLPI 11 (removed).
 *    Use $DB->doQuery("SHOW INDEX ...") or the DbUtils global isIndex() instead.
 *    Safest approach: wrap ALTER TABLE ADD UNIQUE KEY in try/catch so duplicate-key
 *    errors (from already existing index) are silently ignored.
 *
 * @return bool
 */
function plugin_transferticketentity_install(): bool
{
    global $DB;

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

    $table = 'glpi_plugin_transferticketentity_entities_settings';

    if (!$DB->tableExists($table)) {
        // ── FRESH INSTALLATION ──────────────────────────────────────────────
        // UNIQUE KEY on entities_id prevents duplicate rows that cause the
        // "first save errors, second works" bug on delete+insert pattern.
        $query = "CREATE TABLE `{$table}` (
            `id`                         INT {$key_sign} NOT NULL AUTO_INCREMENT,
            `entities_id`                INT {$key_sign} NOT NULL DEFAULT 0,
            `allow_entity_only_transfer` TINYINT(1) NOT NULL DEFAULT 0,
            `justification_transfer`     TINYINT(1) NOT NULL DEFAULT 0,
            `allow_transfer`             TINYINT(1) NOT NULL DEFAULT 0,
            `keep_category`              TINYINT(1) NOT NULL DEFAULT 0,
            `itilcategories_id`          INT {$key_sign} NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_entities_id` (`entities_id`),
            KEY `itilcategories_id` (`itilcategories_id`)
        ) ENGINE=InnoDB
          DEFAULT CHARSET={$charset}
          COLLATE={$collation}
          ROW_FORMAT=DYNAMIC;";

        $DB->doQuery($query);

    } else {
        // ── UPGRADE from v1.x / v2.0.0 ─────────────────────────────────────
        $migration = new Migration(TRANSFERTICKETENTITY_VERSION);

        // Add columns introduced in v2.0 (Migration::addField skips if exists)
        $migration->addField($table, 'keep_category',     'bool',    ['value' => 0, 'after' => 'allow_transfer']);
        $migration->addField($table, 'itilcategories_id', 'integer', ['value' => 0, 'after' => 'keep_category']);
        $migration->addKey($table, 'itilcategories_id');
        $migration->executeMigration();

        // Add UNIQUE KEY on entities_id if it does not already exist.
        // NOTE: $DB->indexExists() was removed in GLPI 11.
        // We use the global DbUtils::isIndex() helper (still available in GLPI 11)
        // via the compatibility wrapper isIndex(), or fall back to a direct SHOW INDEX.
        // Safest cross-version approach: try/catch on the ALTER — MariaDB/MySQL
        // raises error 1061 ("Duplicate key name") if the index already exists,
        // which we can safely ignore.
        if (!_transferticketentity_indexExists($DB, $table, 'uniq_entities_id')) {
            // Remove duplicate rows first (keep the row with the lowest id)
            $DB->doQuery("
                DELETE t1 FROM `{$table}` t1
                INNER JOIN `{$table}` t2
                    ON t1.`entities_id` = t2.`entities_id`
                   AND t1.`id` > t2.`id`
            ");
            $DB->doQuery(
                "ALTER TABLE `{$table}` ADD UNIQUE KEY `uniq_entities_id` (`entities_id`)"
            );
        }
    }

    // Always seed profile rights — covers re-install without prior uninstall
    // and fresh installs where the super-admin profile needs the right immediately.
    Profile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);

    return true;
}

/**
 * Check whether a named index exists on a table.
 * Compatible with GLPI 11 (does not use $DB->indexExists() which was removed).
 *
 * Uses SHOW INDEX FROM ... WHERE Key_name = ? via $DB->doQuery() which IS
 * allowed in GLPI 11 for self-crafted safe queries.
 *
 * @param object $DB        Global $DB instance
 * @param string $table     Table name (without backticks)
 * @param string $indexName Index / key name to look for
 *
 * @return bool
 */
function _transferticketentity_indexExists(object $DB, string $table, string $indexName): bool
{
    // SHOW INDEX is a read-only metadata query — safe to use with doQuery()
    $result = $DB->doQuery(
        "SHOW INDEX FROM `{$table}` WHERE `Key_name` = '" . $DB->escape($indexName) . "'"
    );

    if ($result === false) {
        return false;
    }

    return ($result->num_rows > 0);
}

/**
 * Uninstall hook — compatible with GLPI 11.
 *
 * @return bool
 */
function plugin_transferticketentity_uninstall(): bool
{
    global $DB;

    // Remove all plugin profile rights using the ORM (no raw SQL needed)
    $DB->delete('glpi_profilerights', ['name' => 'plugin_transferticketentity_use']);
    $DB->delete('glpi_profilerights', ['name' => 'plugin_transferticketentity_bypass']);

    // Drop plugin table via Migration (uses doQuery() internally — allowed in GLPI 11)
    $migration = new Migration(TRANSFERTICKETENTITY_VERSION);
    $migration->dropTable('glpi_plugin_transferticketentity_entities_settings');
    $migration->executeMigration();

    return true;
}
