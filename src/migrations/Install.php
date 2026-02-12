<?php
namespace dowleydeveloped\cookieconsent\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * Install migration for Cookie Consent.
 *
 * NOTE:
 * - These tables are NOT element-backed, so they must NOT FK to craft_elements.
 * - Uses normal primary keys (auto-increment) and inserts default rows.
 */
class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropProjectConfig();
        $this->dropTables();

        return true;
    }

    private function createTables(): void
    {
        /**
         * Tracked totals (single-row counter table)
         */
        $trackedTable = '{{%dowley_cookies_tracked}}';
        $this->archiveTableIfExists($trackedTable);

        $this->createTable($trackedTable, [
            'id' => $this->primaryKey(), // normal auto-increment PK (NOT element id)
            'accepted' => $this->integer()->notNull()->defaultValue(0),
            'rejected' => $this->integer()->notNull()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Insert a single default row
        $this->insert($trackedTable, [
            'accepted' => 0,
            'rejected' => 0,
            'dateCreated' => (new \DateTime())->format('Y-m-d H:i:s'),
            'dateUpdated' => (new \DateTime())->format('Y-m-d H:i:s'),
            'uid' => Craft::$app->getSecurity()->generateRandomString(36),
        ]);

        /**
         * Enabled cookies list
         */
        $enabledTable = '{{%dowley_cookies_enabled}}';
        $this->archiveTableIfExists($enabledTable);

        $this->createTable($enabledTable, [
            'id' => $this->primaryKey(), // normal auto-increment PK (NOT element id)
            'type' => $this->string(255),
            'cookieId' => $this->string(255),
            'domain' => $this->string(255),
            'duration' => $this->string(255),
            'description' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        /**
         * Popup / preferences content (single-row config table)
         */
        $contentTable = '{{%dowley_cookies_content}}';
        $this->archiveTableIfExists($contentTable);

        $this->createTable($contentTable, [
            'id' => $this->primaryKey(), // normal auto-increment PK
            'popupTitle' => $this->text()->null(),
            'popupDescription' => $this->text()->null(),
            'popupFooter' => $this->text()->null(),
            'popupLayout' => $this->string(255)->defaultValue('box inline'),
            'popupPosition' => $this->string(255)->defaultValue('bottom right'),
            'preferencesTitle' => $this->text()->null(),
            'preferencesDescription' => $this->text()->null(),
            'requiredCookies' => $this->text()->null(),
            'functionalCookies' => $this->text()->null(),
            'analyticsCookies' => $this->text()->null(),
            'performanceCookies' => $this->text()->null(),
            'advertisingCookies' => $this->text()->null(),
            'securityCookies' => $this->text()->null(),
            'preferencesLayout' => $this->string(255)->defaultValue('bar'),
            'preferencesPosition' => $this->string(255)->defaultValue('right'),
            'triggerIcon' => $this->integer(),
            'triggerPosition' => $this->string(255)->defaultValue('left'),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Insert a single default row
        $now = (new \DateTime())->format('Y-m-d H:i:s');
        $this->insert($contentTable, [
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

    private function createIndexes(): void
    {
        // Helpful indexes (optional but good)
        $this->createIndex(null, '{{%dowley_cookies_enabled}}', ['cookieId'], false);
        $this->createIndex(null, '{{%dowley_cookies_enabled}}', ['type'], false);
        $this->createIndex(null, '{{%dowley_cookies_enabled}}', ['domain'], false);
    }

    private function dropTables(): void
    {
        $this->dropTableIfExists('{{%dowley_cookies_content}}');
        $this->dropTableIfExists('{{%dowley_cookies_enabled}}');
        $this->dropTableIfExists('{{%dowley_cookies_tracked}}');
    }

    private function dropProjectConfig(): void
    {
        Craft::$app->getProjectConfig()->remove('dowley-cookieconsent');
    }
}
