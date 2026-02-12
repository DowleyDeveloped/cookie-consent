<?php
namespace dowleydeveloped\cookieconsent\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;

/**
 * Updates legacy installs to the current schema:
 * - Removes any (incorrect) FKs to craft_elements
 * - Ensures normal auto-increment primary keys
 * - Adds dateCreated/dateUpdated/uid columns (and backfills them)
 * - Adds useful indexes
 * - Ensures required “single-row” defaults exist for tracked + content tables
 */
class m260212_000001_normalize_cookie_tables extends Migration
{
    public function safeUp(): bool
    {
        $trackedTable = '{{%dowley_cookies_tracked}}';
        $enabledTable = '{{%dowley_cookies_enabled}}';
        $contentTable = '{{%dowley_cookies_content}}';

        // If this is an install where tables don't exist yet, nothing to do.
        if (
            !$this->db->tableExists($trackedTable) &&
            !$this->db->tableExists($enabledTable) &&
            !$this->db->tableExists($contentTable)
        ) {
            return true;
        }

        /**
         * 1) Drop incorrect foreign keys (if any)
         */
        foreach ([$trackedTable, $enabledTable] as $table) {
            if ($this->db->tableExists($table)) {
                // Drops all FKs on the table (safe even if there are none)
                MigrationHelper::dropAllForeignKeysOnTable($table, $this);
            }
        }

        /**
         * 2) Bring tables to “normal” (non-element) PKs and standard columns
         */
        if ($this->db->tableExists($trackedTable)) {
            $this->normalizePrimaryKey($trackedTable);
            $this->addStandardColumns($trackedTable);
            $this->normalizeTrackedSingleRow($trackedTable);
        }

        if ($this->db->tableExists($enabledTable)) {
            $this->normalizePrimaryKey($enabledTable);
            $this->addStandardColumns($enabledTable);
            $this->addEnabledIndexes($enabledTable);
        }

        if ($this->db->tableExists($contentTable)) {
            $this->normalizePrimaryKey($contentTable);
            $this->addStandardColumns($contentTable);
            $this->normalizeContentSingleRow($contentTable);
        }

        return true;
    }

    public function safeDown(): bool
    {
        // Generally you don’t “down” schema normalization migrations on plugin upgrades.
        // Leave as a no-op.
        return true;
    }

    /**
     * Ensures `id` is a standard AUTO_INCREMENT primary key.
     * - If `id` is already a primary key auto-inc, this is effectively a no-op.
     * - If `id` is NOT a PK (common in your intermediate migration), it will be fixed.
     */
    private function normalizePrimaryKey(string $table): void
    {
        // If id column doesn't exist, we can't normalize safely
        if (!$this->db->columnExists($table, 'id')) {
            return;
        }

        $schema = $this->db->getTableSchema($table, true);

        // Detect if `id` is already the (only) primary key
        $pkCols = $schema?->primaryKey ?? [];
        $idIsPk = (count($pkCols) === 1 && $pkCols[0] === 'id');

        // If id is already the PK, still ensure it's auto-increment-ish by converting to PK type.
        // Craft migration helper doesn't expose "isAutoIncrement", so use a safe ALTER.
        if ($idIsPk) {
            // Make sure id is an int PK (MySQL will preserve auto inc if already there).
            // If id isn't auto inc, this will make it so on MySQL.
            $this->alterColumn($table, 'id', $this->primaryKey());
            return;
        }

        // If id is not the PK, we need to:
        // - Ensure there are no NULL ids (fix them)
        // - Add PK
        // - Convert to primaryKey (auto increment)
        $this->db->createCommand()->update($table, ['id' => 0], ['id' => null])->execute();

        // Drop existing PK if there is one (rare, but safe)
        if (!empty($pkCols)) {
            // Drop PK constraint name differs by driver; easiest is raw SQL.
            // Works on MySQL; if you're on Postgres, you'd need a different approach.
            // Craft installs on MySQL for most cases—adjust if you’re on Postgres.
            try {
                $this->execute("ALTER TABLE {$this->db->quoteTableName($table)} DROP PRIMARY KEY");
            } catch (\Throwable $e) {
                // ignore if not supported / already absent
            }
        }

        // Convert id column to proper PK type
        $this->alterColumn($table, 'id', $this->primaryKey());
    }

    /**
     * Adds Craft-standard columns dateCreated/dateUpdated/uid and backfills them.
     */
    private function addStandardColumns(string $table): void
    {
        // Add columns if missing
        if (!$this->db->columnExists($table, 'dateCreated')) {
            $this->addColumn($table, 'dateCreated', $this->dateTime());
        }
        if (!$this->db->columnExists($table, 'dateUpdated')) {
            $this->addColumn($table, 'dateUpdated', $this->dateTime());
        }
        if (!$this->db->columnExists($table, 'uid')) {
            $this->addColumn($table, 'uid', $this->uid());
        }

        // Backfill dateCreated/dateUpdated for any existing rows where null
        $now = (new \DateTime())->format('Y-m-d H:i:s');
        if ($this->db->columnExists($table, 'dateCreated')) {
            $this->db->createCommand()->update($table, ['dateCreated' => $now], ['dateCreated' => null])->execute();
        }
        if ($this->db->columnExists($table, 'dateUpdated')) {
            $this->db->createCommand()->update($table, ['dateUpdated' => $now], ['dateUpdated' => null])->execute();
        }

        // Backfill uid row-by-row where empty/null
        if ($this->db->columnExists($table, 'uid')) {
            $rows = (new Query())
                ->select(['id', 'uid'])
                ->from($table)
                ->all();

            foreach ($rows as $row) {
                $uid = $row['uid'] ?? null;
                if (!$uid) {
                    $this->update($table, [
                        'uid' => Craft::$app->getSecurity()->generateRandomString(36),
                    ], [
                        'id' => (int)$row['id'],
                    ]);
                }
            }
        }

        // Make dateCreated/dateUpdated NOT NULL once backfilled (optional but recommended)
        // Only do this if the columns exist (they do) and the DB supports it.
        try {
            $this->alterColumn($table, 'dateCreated', $this->dateTime()->notNull());
            $this->alterColumn($table, 'dateUpdated', $this->dateTime()->notNull());
        } catch (\Throwable $e) {
            // ignore driver limitations
        }
    }

    /**
     * Ensures dowley_cookies_tracked is effectively a single-row counter table.
     * - If empty: inserts a default row (accepted/rejected = 0)
     * - If multiple rows: consolidates into one row (sums accepted/rejected) and deletes extras
     */
    private function normalizeTrackedSingleRow(string $table): void
    {
        $count = (int)(new Query())->from($table)->count();

        if ($count === 0) {
            $this->insert($table, [
                'accepted' => 0,
                'rejected' => 0,
                'dateCreated' => (new \DateTime())->format('Y-m-d H:i:s'),
                'dateUpdated' => (new \DateTime())->format('Y-m-d H:i:s'),
                'uid' => Craft::$app->getSecurity()->generateRandomString(36),
            ]);
            return;
        }

        if ($count === 1) {
            return;
        }

        // Consolidate
        $sum = (new Query())
            ->select([
                'accepted' => 'SUM([[accepted]])',
                'rejected' => 'SUM([[rejected]])',
                'keepId' => 'MIN([[id]])',
            ])
            ->from($table)
            ->one();

        $keepId = (int)$sum['keepId'];
        $accepted = (int)$sum['accepted'];
        $rejected = (int)$sum['rejected'];

        $this->update($table, [
            'accepted' => $accepted,
            'rejected' => $rejected,
        ], [
            'id' => $keepId,
        ]);

        $this->delete($table, ['not', ['id' => $keepId]]);
    }

    /**
     * Ensures dowley_cookies_content has one row, inserting defaults if empty.
     * (Does NOT overwrite existing content.)
     */
    private function normalizeContentSingleRow(string $table): void
    {
        $count = (int)(new Query())->from($table)->count();
        if ($count > 0) {
            return;
        }

        $now = (new \DateTime())->format('Y-m-d H:i:s');

        $this->insert($table, [
            'popupTitle' => 'We value your privacy',
            'popupDescription' => "We use cookies to enhance your browsing experience, serve personalised ads or content, and analyse our traffic. By clicking 'Accept', you consent to our use of cookies.",
            'popupFooter' => '<a href="/privacy-policy">Privacy Policy</a>',
            'preferencesTitle' => 'Customise Consent Preferences',
            'preferencesDescription' => 'A cookie is a small text file sent to your browser and stored on your device by a website you visit. Cookies may save information about the pages you visit and the devices you use, which in return can give us more insight about how you use our website so we can improve its usability and deliver more relevant content.',
            'requiredCookies' => 'Necessary cookies are required to enable the basic features of this site, such as providing secure log-in or adjusting your consent preferences. These cookies do not store any personally identifiable data.',
            'functionalCookies' => 'Functional cookies help perform certain functionalities like sharing the content of the website on social media platforms, collecting feedback, and other third-party features.',
            'analyticsCookies' => 'Analytical cookies are used to understand how visitors interact with the website. These cookies help provide information on metrics such as the number of visitors, bounce rate, traffic source, etc.',
            'performanceCookies' => 'Performance cookies are used to understand and analyse the key performance indexes of the website which helps in delivering a better user experience for the visitors.',
            'advertisingCookies' => 'Advertisement cookies are used to provide visitors with customised advertisements based on the pages you visited previously and to analyse the effectiveness of the ad campaigns.',
            'securityCookies' => 'Cookies used for security authenticate users, prevent fraud, and protect users as they interact with a service.',
            'dateCreated' => $now,
            'dateUpdated' => $now,
            'uid' => Craft::$app->getSecurity()->generateRandomString(36),
        ]);
    }

    /**
     * Adds indexes for enabled cookies table (safe to run repeatedly).
     */
    private function addEnabledIndexes(string $table): void
    {
        // createIndex() is safe to call with null name; Craft will generate one.
        // If you're worried about duplicates on some DBs, you can wrap in try/catch.
        try {
            $this->createIndex(null, $table, ['cookieId'], false);
        } catch (\Throwable $e) {
        }
        try {
            $this->createIndex(null, $table, ['type'], false);
        } catch (\Throwable $e) {
        }
        try {
            $this->createIndex(null, $table, ['domain'], false);
        } catch (\Throwable $e) {
        }
    }
}
